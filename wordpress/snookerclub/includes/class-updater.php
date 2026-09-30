<?php
/**
 * Self-hosted updates via Update URI / optional custom feed.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Snookerclub_Updater {
    public const DEFAULT_FEED = 'https://alexvvught.com/webhost/snooker/api/public/wp-plugin';
    public const SLUG = 'snookerclub';

    public static function init(): void {
        add_filter('pre_set_site_transient_update_plugins', [self::class, 'inject_update']);
        add_filter('plugins_api', [self::class, 'plugins_api'], 10, 3);
        add_filter('auto_update_plugin', [self::class, 'maybe_auto_update'], 10, 2);
        add_filter('http_request_args', [self::class, 'prefer_https_package'], 10, 2);
        foreach (self::update_hosts() as $host) {
            add_filter('update_plugins_' . $host, [self::class, 'host_update'], 10, 4);
        }
        add_action('upgrader_process_complete', [self::class, 'clear_cache'], 10, 2);
    }

    /** Hostnames that WordPress may call via Update URI. */
    public static function update_hosts(): array {
        $hosts = [];
        foreach ([self::feed_url(), self::header_update_uri(), self::DEFAULT_FEED] as $url) {
            $host = wp_parse_url((string) $url, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $hosts[$host] = $host;
            }
        }
        return array_values($hosts);
    }

    public static function header_update_uri(): string {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (!defined('SNOOKERCLUB_FILE') || !is_readable(SNOOKERCLUB_FILE)) {
            return self::DEFAULT_FEED;
        }
        $data = get_plugin_data(SNOOKERCLUB_FILE, false, false);
        $uri = trim((string) ($data['UpdateURI'] ?? ''));
        if ($uri === '' || strtolower($uri) === 'false') {
            return self::DEFAULT_FEED;
        }
        return self::https_url($uri);
    }

    public static function feed_url(): string {
        $custom = get_option('snookerclub_update_url', '');
        $url = is_string($custom) ? trim($custom) : '';
        if ($url === '') {
            $url = self::header_update_uri();
        }
        return apply_filters('snookerclub_update_url', self::https_url($url));
    }

    public static function https_url(string $url): string {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (stripos($url, 'http://') === 0) {
            $url = 'https://' . substr($url, 7);
        }
        return $url;
    }

    public static function auto_updates_enabled(): bool {
        return get_option('snookerclub_auto_update', '0') === '1';
    }

    /** Keep WordPress core auto-update list in sync with our checkbox. */
    public static function sync_wp_auto_update_flag(bool $enabled): void {
        $file = self::plugin_file();
        $current = (array) get_site_option('auto_update_plugins', []);
        $current = array_values(array_filter(array_map('strval', $current)));
        if ($enabled) {
            if (!in_array($file, $current, true)) {
                $current[] = $file;
            }
        } else {
            $current = array_values(array_diff($current, [$file]));
        }
        update_site_option('auto_update_plugins', $current);
    }

    public static function normalize_feed(array $body): array {
        foreach (['package', 'download_url', 'url', 'homepage'] as $key) {
            if (!empty($body[$key]) && is_string($body[$key])) {
                $body[$key] = self::https_url($body[$key]);
            }
        }
        if (empty($body['package']) && !empty($body['download_url'])) {
            $body['package'] = $body['download_url'];
        }
        if (empty($body['download_url']) && !empty($body['package'])) {
            $body['download_url'] = $body['package'];
        }
        return $body;
    }

    public static function fetch_feed(bool $force = false): ?array {
        $url = self::feed_url();
        if ($url === '') {
            return null;
        }
        if (!$force) {
            $cached = get_site_transient('snookerclub_update_feed');
            if (is_array($cached) && isset($cached['version'])) {
                return self::normalize_feed($cached);
            }
        }
        $response = wp_remote_get($url, [
            'timeout' => 12,
            'redirection' => 3,
            'headers' => [
                'Accept' => 'application/json',
            ],
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; Snookerclub/' . (defined('SNOOKERCLUB_VERSION') ? SNOOKERCLUB_VERSION : '0'),
        ]);
        if (is_wp_error($response)) {
            return null;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return null;
        }
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['version'])) {
            return null;
        }
        $body = self::normalize_feed($body);
        set_site_transient('snookerclub_update_feed', $body, 6 * HOUR_IN_SECONDS);
        return $body;
    }

    public static function plugin_file(): string {
        return plugin_basename(SNOOKERCLUB_FILE);
    }

    public static function build_update_item(array $feed): object {
        return (object) [
            'id' => self::plugin_file(),
            'slug' => self::SLUG,
            'plugin' => self::plugin_file(),
            'new_version' => $feed['version'],
            'url' => $feed['url'] ?? $feed['homepage'] ?? '',
            'package' => $feed['package'] ?? $feed['download_url'] ?? '',
            'tested' => $feed['tested'] ?? '',
            'requires' => $feed['requires'] ?? '6.2',
            'requires_php' => $feed['requires_php'] ?? '8.1',
            'icons' => $feed['icons'] ?? [],
        ];
    }

    public static function inject_update($transient) {
        if (!is_object($transient)) {
            return $transient;
        }
        $feed = self::fetch_feed();
        if (!$feed) {
            return $transient;
        }
        $current = defined('SNOOKERCLUB_VERSION') ? SNOOKERCLUB_VERSION : '0';
        $file = self::plugin_file();
        if (version_compare((string) $feed['version'], $current, '<=')) {
            if (!isset($transient->no_update) || !is_array($transient->no_update)) {
                $transient->no_update = [];
            }
            $transient->no_update[$file] = self::build_update_item($feed);
            unset($transient->response[$file]);
            return $transient;
        }
        if (empty($transient->response) || !is_array($transient->response)) {
            $transient->response = [];
        }
        $transient->response[$file] = self::build_update_item($feed);
        return $transient;
    }

    public static function host_update($update, $plugin_data, $plugin_file, $locales) {
        unset($plugin_data, $locales);
        if ($plugin_file !== self::plugin_file()) {
            return $update;
        }
        $feed = self::fetch_feed();
        if (!$feed) {
            return $update;
        }
        $current = defined('SNOOKERCLUB_VERSION') ? SNOOKERCLUB_VERSION : '0';
        if (version_compare((string) $feed['version'], $current, '<=')) {
            return false;
        }
        return [
            'version' => $feed['version'],
            'package' => $feed['package'] ?? $feed['download_url'] ?? '',
            'url' => $feed['url'] ?? $feed['homepage'] ?? '',
            'requires' => $feed['requires'] ?? '6.2',
            'requires_php' => $feed['requires_php'] ?? '8.1',
            'tested' => $feed['tested'] ?? '',
        ];
    }

    public static function plugins_api($result, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== self::SLUG) {
            return $result;
        }
        $feed = self::fetch_feed();
        if (!$feed) {
            return $result;
        }
        return (object) [
            'name' => $feed['name'] ?? 'Snookerclub',
            'slug' => self::SLUG,
            'version' => $feed['version'],
            'author' => $feed['author'] ?? 'Alex van Vught',
            'homepage' => $feed['homepage'] ?? $feed['url'] ?? '',
            'requires' => $feed['requires'] ?? '6.2',
            'tested' => $feed['tested'] ?? '',
            'requires_php' => $feed['requires_php'] ?? '8.1',
            'last_updated' => $feed['last_updated'] ?? '',
            'sections' => $feed['sections'] ?? [
                'description' => 'Snookerclub: uitslagen, spelers, agenda en liveblokken in WordPress.',
            ],
            'download_link' => $feed['package'] ?? $feed['download_url'] ?? '',
            'trunk' => $feed['package'] ?? $feed['download_url'] ?? '',
        ];
    }

    public static function maybe_auto_update($update, $item): bool {
        $file = '';
        if (is_object($item)) {
            $file = (string) ($item->plugin ?? '');
        } elseif (is_string($item)) {
            $file = $item;
        }
        if ($file !== self::plugin_file()) {
            return (bool) $update;
        }
        return self::auto_updates_enabled();
    }

    /** WordPress may request the package over http; upgrade to https. */
    public static function prefer_https_package($args, $url) {
        if (!is_array($args) || !is_string($url)) {
            return $args;
        }
        if (stripos($url, 'snookerclub.zip') === false && stripos($url, '/api/public/wp-plugin') === false) {
            return $args;
        }
        return $args;
    }

    public static function clear_cache($upgrader, $options): void {
        unset($upgrader);
        if (($options['type'] ?? '') === 'plugin') {
            delete_site_transient('snookerclub_update_feed');
        }
    }

    public static function check_now(): ?array {
        delete_site_transient('snookerclub_update_feed');
        delete_site_transient('update_plugins');
        $feed = self::fetch_feed(true);
        // Refresh WP update transient so Dashboard → Updates sees us.
        if (function_exists('wp_update_plugins')) {
            wp_update_plugins();
        }
        return $feed;
    }
}
