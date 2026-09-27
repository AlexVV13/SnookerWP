<?php
/**
 * Optional self-hosted updates. No feed is required; the plugin works offline.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Snookerclub_Updater {
    public const DEFAULT_FEED = '';
    public const SLUG = 'snookerclub';

    public static function init(): void {
        add_filter('pre_set_site_transient_update_plugins', [self::class, 'inject_update']);
        add_filter('plugins_api', [self::class, 'plugins_api'], 10, 3);
        add_filter('auto_update_plugin', [self::class, 'maybe_auto_update'], 10, 2);
        $host = wp_parse_url(self::feed_url(), PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            add_filter('update_plugins_' . $host, [self::class, 'host_update'], 10, 4);
        }
        add_action('upgrader_process_complete', [self::class, 'clear_cache'], 10, 2);
    }

    public static function feed_url(): string {
        $custom = get_option('snookerclub_update_url', '');
        $url = is_string($custom) ? trim($custom) : '';
        return apply_filters('snookerclub_update_url', $url);
    }

    public static function auto_updates_enabled(): bool {
        return get_option('snookerclub_auto_update', '0') === '1' && self::feed_url() !== '';
    }

    public static function fetch_feed(): ?array {
        $url = self::feed_url();
        if ($url === '') {
            return null;
        }
        $cached = get_site_transient('snookerclub_update_feed');
        if (is_array($cached) && isset($cached['version'])) {
            return $cached;
        }
        $response = wp_remote_get($url, [
            'timeout' => 8,
            'headers' => ['Accept' => 'application/json'],
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
        set_site_transient('snookerclub_update_feed', $body, 6 * HOUR_IN_SECONDS);
        return $body;
    }

    public static function plugin_file(): string {
        return plugin_basename(SNOOKERCLUB_FILE);
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
        if (version_compare($feed['version'], $current, '<=')) {
            return $transient;
        }
        $item = (object) [
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
        if (empty($transient->response)) {
            $transient->response = [];
        }
        $transient->response[self::plugin_file()] = $item;
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
        if (version_compare($feed['version'], $current, '<=')) {
            return $update;
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
            'author' => $feed['author'] ?? 'parkData',
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
        $file = is_object($item) ? ($item->plugin ?? '') : '';
        if ($file !== self::plugin_file()) {
            return (bool) $update;
        }
        return self::auto_updates_enabled();
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
        return self::fetch_feed();
    }
}
