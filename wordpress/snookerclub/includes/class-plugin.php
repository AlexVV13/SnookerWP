<?php
if (!defined('ABSPATH')) {
    exit;
}

class Snookerclub_Plugin {
    public static function init(): void {
        add_action('init', [self::class, 'maybe_upgrade'], 1);
        add_action('init', [self::class, 'register']);
        add_action('init', [self::class, 'register_block']);
        add_action('admin_notices', [self::class, 'maybe_missing_assets_notice']);
        add_action('template_redirect', [self::class, 'dispatch'], 0);
        add_action('admin_menu', [self::class, 'admin_menu']);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_init', [self::class, 'maybe_export_report']);
        add_action('wp_enqueue_scripts', [self::class, 'maybe_enqueue_front']);
        add_action('enqueue_block_assets', [self::class, 'maybe_enqueue_block_assets']);
        add_action('enqueue_block_editor_assets', [self::class, 'enqueue_board_assets']);
        add_action('admin_enqueue_scripts', [self::class, 'maybe_enqueue_admin']);
        add_action('admin_post_snookerclub_check_update', [self::class, 'handle_check_update']);
        add_shortcode('snookerclub', [self::class, 'shortcode_guest']);
        add_shortcode('snookerclub_ingeven', [self::class, 'shortcode_ingeven']);
        add_shortcode('snookerclub_match', [self::class, 'shortcode_ingeven']);
        add_shortcode('snookerclub_live', [self::class, 'shortcode_live']);
        add_shortcode('snookerclub_players', [self::class, 'shortcode_players']);
        add_shortcode('snookerclub_ranking', [self::class, 'shortcode_players']);
        add_shortcode('snookerclub_results', [self::class, 'shortcode_results']);
        add_shortcode('snookerclub_kpis', [self::class, 'shortcode_kpis']);
        add_shortcode('snookerclub_break', [self::class, 'shortcode_break']);
        add_shortcode('snookerclub_next', [self::class, 'shortcode_next']);
        add_shortcode('snookerclub_agenda', [self::class, 'shortcode_agenda']);
        add_shortcode('snookerclub_admin', [self::class, 'shortcode_admin']);
        add_shortcode('snookerclub_rapport', [self::class, 'shortcode_rapport']);
        add_shortcode('snookerclub_dossier', [self::class, 'shortcode_dossier']);
        add_shortcode('snookerclub_h2h', [self::class, 'shortcode_h2h']);
        add_filter('query_vars', [self::class, 'query_vars']);
    }

    public static function activate(): void {
        if (get_option('snookerclub_auto_update', null) === null) {
            add_option('snookerclub_auto_update', '0');
        }
        if (get_option('snookerclub_slug', null) === null) {
            add_option('snookerclub_slug', 'snooker');
        }
        if (get_option('snookerclub_pretty_urls', null) === null) {
            add_option('snookerclub_pretty_urls', '0');
        }
        if (get_option('snookerclub_plugin_version', null) === null) {
            add_option('snookerclub_plugin_version', SNOOKERCLUB_VERSION);
        }
        self::register();
        self::ensure_pages();
        flush_rewrite_rules();
        update_option('snookerclub_plugin_version', SNOOKERCLUB_VERSION);
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    public static function slug(): string {
        $slug = sanitize_title(get_option('snookerclub_slug', 'snooker'));
        return $slug !== '' ? $slug : 'snooker';
    }

    public static function base_path(): string {
        return '/' . self::slug();
    }

    public static function pretty_urls(): bool {
        return get_option('snookerclub_pretty_urls', '0') === '1';
    }

    public static function maybe_upgrade(): void {
        $stored = (string) get_option('snookerclub_plugin_version', '');
        if ($stored === SNOOKERCLUB_VERSION) {
            return;
        }
        if (get_option('snookerclub_pretty_urls', null) === null) {
            add_option('snookerclub_pretty_urls', '0');
        }
        self::ensure_pages();
        flush_rewrite_rules(false);
        update_option('snookerclub_plugin_version', SNOOKERCLUB_VERSION);
    }

    public static function public_dir(): string {
        $bundled = SNOOKERCLUB_DIR . 'public';
        if (is_dir($bundled) && file_exists($bundled . '/guest.html')) {
            return $bundled;
        }
        $sibling = dirname(SNOOKERCLUB_DIR, 2) . '/public';
        if (is_dir($sibling) && file_exists($sibling . '/guest.html')) {
            return $sibling;
        }
        return $bundled;
    }

    public static function rest_root(): string {
        return class_exists('Snookerclub_Rest') ? Snookerclub_Rest::root() : untrailingslashit(rest_url('snookerclub/v1'));
    }

    public static function hero_url(): string {
        return add_query_arg('snookerclub', 'hero', home_url('/'));
    }

    public static function app_page_url(): string {
        $id = (int) get_option('snookerclub_page_app');
        if ($id && get_post_status($id) === 'publish') {
            return get_permalink($id);
        }
        return home_url('/' . self::slug() . '/');
    }

    public static function match_page_url(): string {
        $id = (int) get_option('snookerclub_page_match');
        if ($id && get_post_status($id) === 'publish') {
            return get_permalink($id);
        }
        return admin_url('admin.php?page=snookerclub-ingeven');
    }

    public static function sync_app_page_title(string $clubName = ''): void {
        if (!function_exists('wp_update_post') || !function_exists('get_post')) {
            return;
        }
        $clubName = trim($clubName);
        if ($clubName === '') {
            $clubName = (string) (self::store()->get_brand()['clubName'] ?? 'SC De Merodesnookers');
        }
        $id = (int) get_option('snookerclub_page_app');
        if (!$id) {
            return;
        }
        $post = get_post($id);
        if (!$post instanceof WP_Post) {
            return;
        }
        $last = (string) get_option('snookerclub_synced_page_title', '');
        $generic = ['', 'Snookerclub', 'SC De Merodesnookers'];
        $can_sync = $post->post_title === $clubName
            || ($last !== '' && $post->post_title === $last)
            || in_array($post->post_title, $generic, true);
        if (!$can_sync) {
            return;
        }
        if ($post->post_title !== $clubName) {
            wp_update_post([
                'ID' => $id,
                'post_title' => $clubName,
            ]);
        }
        update_option('snookerclub_synced_page_title', $clubName, false);
    }

    public static function ensure_pages(): void {
        if (!function_exists('wp_insert_post')) {
            return;
        }
        $clubName = 'SC De Merodesnookers';
        if (class_exists('Snookerclub_Store')) {
            $clubName = (string) (self::store()->get_brand()['clubName'] ?? $clubName);
        }
        $pages = [
            'snookerclub_page_app' => [
                'title' => $clubName,
                'slug' => self::slug(),
                'content' => '<!-- wp:snookerclub/board {"view":"app"} /-->',
            ],
            'snookerclub_page_match' => [
                'title' => 'Uitslag invoeren',
                'slug' => self::slug() . '-ingeven',
                'content' => '<!-- wp:snookerclub/board {"view":"ingeven"} /-->',
            ],
        ];
        foreach ($pages as $option => $page) {
            $id = (int) get_option($option);
            if ($id && get_post($id)) {
                continue;
            }
            $existing = get_page_by_path($page['slug']);
            if ($existing instanceof WP_Post) {
                update_option($option, (int) $existing->ID, false);
                continue;
            }
            $created = wp_insert_post([
                'post_title' => $page['title'],
                'post_name' => $page['slug'],
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_content' => $page['content'],
            ]);
            if (!is_wp_error($created) && $created) {
                update_option($option, (int) $created, false);
                if ($option === 'snookerclub_page_app') {
                    update_option('snookerclub_synced_page_title', $page['title'], false);
                }
            }
        }
        self::sync_app_page_title($clubName);
    }

    public static function maybe_missing_assets_notice(): void {
        if (!self::can_manage()) {
            return;
        }
        if (is_file(self::public_dir() . '/guest.html')) {
            return;
        }
        echo '<div class="notice notice-error"><p>Snookerclub mist de frontend-bestanden. Installeer de plugin opnieuw via de zip (niet alleen de PHP-map).</p></div>';
    }

    public static function query_vars(array $vars): array {
        $vars[] = 'snookerclub';
        $vars[] = 'snookerclub_rest';
        return $vars;
    }

    public static function register_block(): void {
        load_plugin_textdomain('snookerclub', false, dirname(plugin_basename(SNOOKERCLUB_FILE)) . '/languages');
        $dir = SNOOKERCLUB_DIR . 'blocks/board';
        if (!is_dir($dir) || !function_exists('register_block_type')) {
            return;
        }
        self::register_board_style();
        wp_register_script(
            'snookerclub-board-editor',
            plugins_url('blocks/board/editor.js', SNOOKERCLUB_FILE),
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render'],
            SNOOKERCLUB_VERSION,
            true
        );
        register_block_type($dir, [
            'editor_script' => 'snookerclub-board-editor',
            'style' => 'snookerclub-board',
            'editor_style' => 'snookerclub-board',
        ]);
    }

    public static function register_board_style(): void {
        if (!function_exists('wp_register_style')) {
            return;
        }
        wp_register_style(
            'snookerclub-board',
            self::asset_url('board.css'),
            [],
            SNOOKERCLUB_VERSION
        );
    }

    public static function register(): void {
        if (!self::pretty_urls()) {
            return;
        }
        $slug = preg_quote(self::slug(), '/');
        add_rewrite_rule('^' . $slug . '/?$', 'index.php?snookerclub=guest', 'top');
        add_rewrite_rule('^' . $slug . '/ingeven/?$', 'index.php?snookerclub=ingeven', 'top');
        add_rewrite_rule('^' . $slug . '/admin/?$', 'index.php?snookerclub=admin', 'top');
        add_rewrite_rule('^' . $slug . '/live/players/?$', 'index.php?snookerclub=live-players', 'top');
        add_rewrite_rule('^' . $slug . '/live/agenda/?$', 'index.php?snookerclub=live-agenda', 'top');
        add_rewrite_rule('^' . $slug . '/live/?$', 'index.php?snookerclub=live', 'top');
        add_rewrite_rule('^' . $slug . '/health/?$', 'index.php?snookerclub=health', 'top');
        add_rewrite_rule('^' . $slug . '/readyz/?$', 'index.php?snookerclub=readyz', 'top');
        add_rewrite_rule('^' . $slug . '/embed\.js$', 'index.php?snookerclub=embed', 'top');
        add_rewrite_rule('^' . $slug . '/media/hero/?$', 'index.php?snookerclub=hero', 'top');
        add_rewrite_rule('^' . $slug . '/api/public/wp-plugin/?$', 'index.php?snookerclub=wp-plugin', 'top');
        add_rewrite_rule('^' . $slug . '/plugin/snookerclub\.zip$', 'index.php?snookerclub=plugin-zip', 'top');
        add_rewrite_rule('^' . $slug . '/api/(.+)$', 'index.php?snookerclub=api&snookerclub_rest=$matches[1]', 'top');
        add_rewrite_rule('^' . $slug . '/([a-z0-9._-]+\.(?:js|css|jpg|jpeg|png|svg|woff2?))$', 'index.php?snookerclub=asset&snookerclub_rest=$matches[1]', 'top');
    }

    public static function store(): Snookerclub_Store {
        static $store;
        if (!$store) {
            $store = new Snookerclub_Store();
        }
        return $store;
    }

    public static function can_manage(): bool {
        return current_user_can('manage_options') || current_user_can('edit_pages');
    }

    public static function dispatch(): void {
        $page = get_query_var('snookerclub');
        if (!$page) {
            return;
        }
        nocache_headers();
        switch ($page) {
            case 'guest':
                self::send_html('guest.html');
                break;
            case 'ingeven':
                self::send_html('guest.html', false, ['tab' => 'match']);
                break;
            case 'admin':
                if (!self::can_manage()) {
                    wp_safe_redirect(wp_login_url(home_url(self::base_path() . '/admin')));
                    exit;
                }
                self::send_html('admin.html', true);
                break;
            case 'live':
                self::send_html('live.html');
                break;
            case 'live-players':
                self::send_html('live-players.html');
                break;
            case 'live-agenda':
                self::send_html('live-agenda.html');
                break;
            case 'embed':
                self::send_file('embed.js', 'application/javascript; charset=utf-8');
                break;
            case 'asset':
                self::send_asset((string) get_query_var('snookerclub_rest'));
                break;
            case 'hero':
                self::send_hero();
                break;
            case 'health':
                self::send_json([
                    'ok' => true,
                    'area' => 'snooker',
                    'base' => self::base_path(),
                    'name' => 'snookerclub',
                    'version' => SNOOKERCLUB_VERSION,
                    'platform' => 'wordpress',
                ]);
                break;
            case 'readyz':
                try {
                    self::store()->list_players();
                    self::send_json(['ok' => true, 'ready' => true, 'version' => SNOOKERCLUB_VERSION]);
                } catch (Throwable $e) {
                    self::send_json(['ok' => false, 'ready' => false, 'version' => SNOOKERCLUB_VERSION], 503);
                }
                break;
            case 'wp-plugin':
                self::send_json(self::local_plugin_manifest());
                break;
            case 'plugin-zip':
                self::send_json(['error' => 'Download de zip via de parkData-updatefeed.'], 404);
                break;
            case 'api':
                self::handle_api((string) get_query_var('snookerclub_rest'));
                break;
            default:
                return;
        }
        exit;
    }

    public static function send_html(string $file, bool $admin = false, array $opts = []): void {
        $path = self::public_dir() . '/' . $file;
        if (!is_file($path)) {
            wp_die('Snookerclub-bestand ontbreekt. Installeer de plugin opnieuw.');
        }
        $html = (string) file_get_contents($path);
        $assets = untrailingslashit(self::asset_base());
        $base = home_url(self::base_path());
        $base_path = untrailingslashit(wp_parse_url($base, PHP_URL_PATH) ?: self::base_path());
        foreach (['guest.css', 'guest.js', 'admin.css', 'admin.js', 'theme.js', 'embed.js', 'hero.jpg'] as $file) {
            $html = str_replace('/webhost/snooker/' . $file, $assets . '/' . $file, $html);
        }
        $html = str_replace('/webhost/snooker', $base_path, $html);
        $html = str_replace('href="/webhost"', 'href="' . esc_url(admin_url('admin.php?page=snookerclub')) . '"', $html);
        $html = preg_replace('#<a class="ghost" href="/webhost/login">Clubportaal</a>#', '', $html);
        $theme = class_exists('Snookerclub_Theme') ? Snookerclub_Theme::normalize(self::store()->get_brand()) : [];
        if ($theme) {
            $html = preg_replace(
                '/<html\b/',
                '<html data-snooker-theme="' . esc_attr($theme['themePreset']) . '" data-density="' . esc_attr($theme['density']) . '"',
                $html,
                1
            );
            $html = str_replace(
                '</head>',
                '<style id="snooker-theme">' . Snookerclub_Theme::css_text($theme) . '</style></head>',
                $html
            );
        }
        $tab = isset($opts['tab']) ? (string) $opts['tab'] : '';
        $boot = [
            'SNOOKER_WP' => true,
            'SNOOKER_BASE' => $base_path,
            'SNOOKER_REST' => self::rest_root(),
        ];
        if ($tab !== '') {
            $boot['SNOOKER_TAB'] = $tab;
        }
        if (is_user_logged_in() && function_exists('wp_create_nonce')) {
            $boot['SNOOKER_NONCE'] = wp_create_nonce('wp_rest');
        }
        $lines = [];
        foreach ($boot as $key => $value) {
            $lines[] = 'window.' . $key . '=' . wp_json_encode($value) . ';';
        }
        $html = str_replace('</head>', '<script>' . implode('', $lines) . '</script></head>', $html);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    public static function send_file(string $file, string $type): void {
        $path = self::public_dir() . '/' . $file;
        if (!is_file($path)) {
            status_header(404);
            exit;
        }
        header('Content-Type: ' . $type);
        header('Cache-Control: public, max-age=86400');
        readfile($path);
        exit;
    }

    public static function send_asset(string $name): void {
        $name = basename($name);
        $path = self::public_dir() . '/' . $name;
        if (!is_file($path)) {
            status_header(404);
            exit;
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $types = [
            'js' => 'application/javascript; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
        ];
        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=86400');
        readfile($path);
        exit;
    }

    public static function send_hero(): void {
        $buf = self::store()->read_hero();
        if (!$buf) {
            wp_safe_redirect(home_url(self::base_path() . '/hero.jpg'));
            exit;
        }
        header('Cache-Control: no-store');
        header('Content-Type: ' . (strncmp($buf, "\x89PNG", 4) === 0 ? 'image/png' : 'image/jpeg'));
        echo $buf;
        exit;
    }

    public static function send_json($data, int $status = 200, bool $cors = false): void {
        status_header($status);
        if ($cors) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
        }
        header('Content-Type: application/json; charset=utf-8');
        header('X-App-Version: ' . SNOOKERCLUB_VERSION);
        echo wp_json_encode($data);
        exit;
    }

    public static function request_json(): array {
        $raw = file_get_contents('php://input');
        $data = json_decode((string) $raw, true);
        return is_array($data) ? $data : [];
    }

    public static function handle_api(string $rest): void {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = trim($rest, '/');
        if ($method === 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, X-WP-Nonce');
            status_header(204);
            exit;
        }

        try {
            if ($path === 'public/meta' && $method === 'GET') {
                self::send_json(['app' => ['name' => 'snookerclub', 'version' => SNOOKERCLUB_VERSION, 'platform' => 'wordpress']], 200, true);
            }
            if ($path === 'public/brand' && $method === 'GET') {
                self::send_json(self::store()->get_brand(), 200, true);
            }
            if ($path === 'public/overview' && $method === 'GET') {
                self::send_json(self::store()->overview(self::base_path()), 200, true);
            }
            if ($path === 'public/players' && $method === 'GET') {
                $overview = self::store()->overview(self::base_path());
                self::send_json([
                    'brand' => $overview['brand'],
                    'players' => $overview['players'],
                    'top3' => $overview['top3'],
                ], 200, true);
            }
            if ($path === 'public/live' && $method === 'GET') {
                $overview = self::store()->overview(self::base_path());
                self::send_json([
                    'brand' => $overview['brand'],
                    'highestBreak' => $overview['highestBreak'],
                    'latest' => $overview['recent'][0] ?? null,
                    'recent' => array_slice($overview['recent'], 0, 4),
                    'top3' => $overview['top3'],
                    'players' => array_slice($overview['players'], 0, 8),
                    'matchCount' => $overview['matchCount'],
                    'playerCount' => $overview['playerCount'],
                    'centuries' => $overview['centuries'],
                    'closest' => $overview['closest'],
                    'kpis' => $overview['kpis'],
                    'nextEvent' => $overview['nextEvent'],
                    'nextEventLabel' => $overview['nextEventLabel'],
                    'agenda' => $overview['agenda'],
                ], 200, true);
            }
            if ($path === 'public/tournaments' && $method === 'GET') {
                $overview = self::store()->overview(self::base_path());
                $names = array_values(array_unique(array_merge($overview['brand']['tournaments'] ?? [], $overview['tournaments'] ?? [])));
                self::send_json(['tournaments' => $names], 200, true);
            }
            if ($path === 'public/roster' && $method === 'GET') {
                self::send_json(['players' => self::store()->list_players()], 200, true);
            }
            if ($path === 'public/agenda' && $method === 'GET') {
                self::send_json([
                    'brand' => self::store()->get_brand(),
                    'agenda' => self::store()->agenda($_GET['year'] ?? null, $_GET['month'] ?? null),
                ], 200, true);
            }
            if ($path === 'public/rapport' && $method === 'GET') {
                $report = self::store()->report((string) ($_GET['season'] ?? ''));
                if (($_GET['format'] ?? '') === 'csv') {
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="snookerclub-ranglijst.csv"');
                    echo Snookerclub_Excel::ranking_csv($report['players'] ?? []);
                    exit;
                }
                self::send_json($report, 200, true);
            }
            if ($path === 'public/dossier' && $method === 'GET') {
                $dossier = self::store()->player_dossier((string) ($_GET['player'] ?? ''), (string) ($_GET['season'] ?? ''));
                if (($_GET['format'] ?? '') === 'csv') {
                    $name = preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($dossier['player'] ?? 'dossier')) ?: 'dossier';
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="snookerclub-' . $name . '.csv"');
                    echo Snookerclub_Excel::dossier_csv($dossier);
                    exit;
                }
                self::send_json($dossier, 200, true);
            }
            if ($path === 'public/h2h' && $method === 'GET') {
                self::send_json(self::store()->head_to_head((string) ($_GET['a'] ?? ''), (string) ($_GET['b'] ?? '')), 200, true);
            }
            if ($path === 'public/wp-plugin' && $method === 'GET') {
                self::send_json(self::local_plugin_manifest(), 200, true);
            }
            if ($path === 'matches' && $method === 'POST') {
                self::guest_limit();
                $match = self::store()->create_match(self::request_json());
                self::send_json([
                    'match' => $match,
                    'wins' => $match['wins'] ?? Snookerclub_Store::frame_wins($match['frames'] ?? []),
                    'highestBreak' => $match['highestBreak'] ?? Snookerclub_Store::match_highest_break($match),
                ], 201);
            }

            if (str_starts_with($path, 'admin/')) {
                self::require_admin();
                self::handle_admin_api(substr($path, 6), $method);
            }
        } catch (Snookerclub_Invalid $err) {
            self::send_json(['error' => $err->getMessage()], 400);
        } catch (Throwable $err) {
            self::send_json(['error' => $err->getMessage() ?: 'Interne fout.'], 500);
        }
        self::send_json(['error' => 'Onbekend endpoint.'], 404);
    }

    public static function handle_admin_api(string $path, string $method): void {
        $store = self::store();
        if ($path === 'me' && $method === 'GET') {
            $user = wp_get_current_user();
            self::send_json([
                'user' => [
                    'id' => (string) $user->ID,
                    'username' => $user->user_login,
                    'role' => self::can_manage() ? 'owner' : 'guest',
                ],
                'app' => ['name' => 'snookerclub', 'version' => SNOOKERCLUB_VERSION, 'platform' => 'wordpress'],
            ]);
        }
        if ($path === 'players' && $method === 'GET') {
            self::send_json(['players' => $store->list_players()]);
        }
        if ($path === 'players' && $method === 'POST') {
            self::send_json(['player' => $store->create_player(self::request_json())], 201);
        }
        if (preg_match('#^players/([^/]+)$#', $path, $m)) {
            if ($method === 'PUT') {
                $player = $store->update_player($m[1], self::request_json());
                if (!$player) {
                    self::send_json(['error' => 'Speler niet gevonden.'], 404);
                }
                self::send_json(['player' => $player]);
            }
            if ($method === 'DELETE') {
                if (!$store->delete_player($m[1])) {
                    self::send_json(['error' => 'Speler niet gevonden.'], 404);
                }
                self::send_json(['ok' => true]);
            }
        }
        if ($path === 'matches' && $method === 'GET') {
            self::send_json(['matches' => $store->list_matches()]);
        }
        if ($path === 'matches' && $method === 'POST') {
            $body = self::request_json();
            $match = (($body['source'] ?? '') === 'paper' || isset($body['result']) || isset($body['framesFor']))
                ? $store->create_paper_match($body)
                : $store->create_match($body, ['require_signatures' => false]);
            self::send_json(['match' => $match, 'wins' => $match['wins'], 'highestBreak' => $match['highestBreak']], 201);
        }
        if ($path === 'paper' && $method === 'POST') {
            self::send_json($store->import_paper(self::request_json()), 201);
        }
        if ($path === 'rapport' && $method === 'GET') {
            self::send_json($store->report((string) ($_GET['season'] ?? '')));
        }
        if ($path === 'dossier' && $method === 'GET') {
            self::send_json($store->player_dossier((string) ($_GET['player'] ?? ''), (string) ($_GET['season'] ?? '')));
        }
        if ($path === 'h2h' && $method === 'GET') {
            self::send_json($store->head_to_head((string) ($_GET['a'] ?? ''), (string) ($_GET['b'] ?? '')));
        }
        if (preg_match('#^matches/([^/]+)$#', $path, $m)) {
            if ($method === 'GET') {
                $match = $store->get_match($m[1]);
                if (!$match) {
                    self::send_json(['error' => 'Wedstrijd niet gevonden.'], 404);
                }
                self::send_json(['match' => $match, 'wins' => $match['wins'], 'highestBreak' => $match['highestBreak']]);
            }
            if ($method === 'PUT') {
                $match = $store->update_match($m[1], self::request_json());
                if (!$match) {
                    self::send_json(['error' => 'Wedstrijd niet gevonden.'], 404);
                }
                self::send_json(['match' => $match, 'wins' => $match['wins'], 'highestBreak' => $match['highestBreak']]);
            }
            if ($method === 'DELETE') {
                if (!$store->delete_match($m[1])) {
                    self::send_json(['error' => 'Wedstrijd niet gevonden.'], 404);
                }
                self::send_json(['ok' => true]);
            }
        }
        if ($path === 'events' && $method === 'GET') {
            self::send_json(['events' => $store->list_events()]);
        }
        if ($path === 'events' && $method === 'POST') {
            self::send_json(['event' => $store->create_event(self::request_json())], 201);
        }
        if (preg_match('#^events/([^/]+)$#', $path, $m)) {
            if ($method === 'PUT') {
                $event = $store->update_event($m[1], self::request_json());
                if (!$event) {
                    self::send_json(['error' => 'Agenda-item niet gevonden.'], 404);
                }
                self::send_json(['event' => $event]);
            }
            if ($method === 'DELETE') {
                if (!$store->delete_event($m[1])) {
                    self::send_json(['error' => 'Agenda-item niet gevonden.'], 404);
                }
                self::send_json(['ok' => true]);
            }
        }
        if ($path === 'brand' && $method === 'GET') {
            self::send_json($store->get_brand());
        }
        if ($path === 'brand' && $method === 'PUT') {
            $brand = $store->save_brand(self::request_json());
            self::sync_app_page_title((string) ($brand['clubName'] ?? ''));
            self::send_json($brand);
        }
        if ($path === 'hero' && $method === 'PUT') {
            $body = self::request_json();
            self::send_json($store->save_hero((string) ($body['image'] ?? ''), self::hero_url()));
        }
        if ($path === 'embed' && $method === 'GET') {
            self::send_json(Snookerclub_Rest::admin_embed());
        }
        self::send_json(['error' => 'Onbekend admin-endpoint.'], 404);
    }

    public static function require_admin(): void {
        if (!is_user_logged_in() || !self::can_manage()) {
            self::send_json(['error' => 'Aanmelden in WordPress is vereist.'], 401);
        }
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $nonce = $_SERVER['HTTP_X_WP_NONCE'] ?? '';
            if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
                self::send_json(['error' => 'Ongeldige beveiligingscode. Vernieuw de pagina.'], 403);
            }
        }
    }

    public static function guest_limit(): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
        $key = 'snookerclub_rl_' . md5($ip);
        $hits = get_transient($key);
        $hits = is_array($hits) ? $hits : [];
        $now = time();
        $hits = array_values(array_filter($hits, fn($t) => $t > $now - 3600));
        if (count($hits) >= 30) {
            throw new Snookerclub_Invalid('Te veel inzendingen. Probeer later opnieuw.');
        }
        $hits[] = $now;
        set_transient($key, $hits, 3600);
    }

    public static function local_plugin_manifest(): array {
        return [
            'name' => 'Snookerclub',
            'slug' => 'snookerclub',
            'plugin' => 'snookerclub/snookerclub.php',
            'version' => SNOOKERCLUB_VERSION,
            'new_version' => SNOOKERCLUB_VERSION,
            'url' => home_url(self::base_path()),
            'homepage' => home_url(self::base_path()),
            'package' => '',
            'download_url' => '',
            'requires' => '6.2',
            'tested' => '6.8',
            'requires_php' => '8.1',
            'last_updated' => gmdate('Y-m-d'),
            'sections' => [
                'description' => 'Snookerclub voor WordPress: guest-uitslagen, clubbeheer, agenda en liveblokken.',
                'changelog' => 'Versie ' . SNOOKERCLUB_VERSION . ' draait in WordPress.',
            ],
        ];
    }

    public static function request_text(string $key): string {
        $value = $_GET[$key] ?? '';
        return is_string($value) ? sanitize_text_field(wp_unslash($value)) : '';
    }

    public static function shortcode_atts($atts = []): array {
        $atts = is_array($atts) ? $atts : [];
        return [
            'skin' => (($atts['skin'] ?? 'site') === 'club') ? 'club' : 'site',
            'limit' => (int) ($atts['limit'] ?? 0),
            'player' => sanitize_text_field((string) ($atts['player'] ?? self::request_text('player'))),
            'season' => sanitize_text_field((string) ($atts['season'] ?? self::request_text('season'))),
            'a' => sanitize_text_field((string) ($atts['a'] ?? self::request_text('a'))),
            'b' => sanitize_text_field((string) ($atts['b'] ?? self::request_text('b'))),
        ];
    }

    public static function render_board(string $view, array $opts = []): string {
        self::enqueue_board_assets();
        $opts = $opts + ['view' => $view];
        $norm = Snookerclub_Render::normalize_opts($opts);
        try {
            $store = self::store();
            $data = $store->overview(self::base_path());
            if ($norm['view'] === 'agenda') {
                $data = [
                    'brand' => $data['brand'] ?? $store->get_brand(),
                    'agenda' => $store->agenda(),
                ];
            }
            if ($norm['view'] === 'rapport') {
                $data = array_merge($store->report((string) ($opts['season'] ?? '')), [
                    'brand' => $data['brand'] ?? $store->get_brand(),
                ]);
            }
            if ($norm['view'] === 'dossier') {
                $player = (string) ($opts['player'] ?? '');
                $season = (string) ($opts['season'] ?? '');
                $data = array_merge($store->player_dossier($player, $season), [
                    'brand' => $data['brand'] ?? $store->get_brand(),
                    'printedAt' => gmdate('Y-m-d'),
                ]);
            }
            if ($norm['view'] === 'h2h') {
                $pair = $store->head_to_head((string) ($opts['a'] ?? ''), (string) ($opts['b'] ?? ''));
                $data = array_merge($pair, [
                    'brand' => $data['brand'] ?? $store->get_brand(),
                    'printedAt' => gmdate('Y-m-d'),
                    'season' => (string) ($opts['season'] ?? ''),
                ]);
            }
            return self::with_board_styles(Snookerclub_Render::board($norm['view'], $data, $opts + $norm));
        } catch (Throwable $err) {
            unset($err);
            return '<p class="snookerclub-empty">Snookerclub kon de clubdata niet laden.</p>';
        }
    }

    public static function embed_markup(string $kind, array $opts = []): string {
        return self::render_board($kind, $opts);
    }

    public static function iframe_markup(string $path, int $height): string {
        unset($path, $height);
        return self::shortcode_guest();
    }

    public static function asset_base(): string {
        $bundled = SNOOKERCLUB_DIR . 'public';
        if (is_dir($bundled) && file_exists($bundled . '/guest.html')) {
            return untrailingslashit(plugins_url('public', SNOOKERCLUB_FILE));
        }
        return untrailingslashit(home_url(self::base_path()));
    }

    public static function asset_url(string $file): string {
        if (is_file(SNOOKERCLUB_DIR . 'public/' . $file)) {
            return plugins_url('public/' . $file, SNOOKERCLUB_FILE);
        }
        return self::asset_base() . '/' . ltrim($file, '/');
    }

    public static function content_has_board(string $content): bool {
        if ($content === '') {
            return false;
        }
        if (str_contains($content, 'wp:snookerclub/board') || str_contains($content, '[snookerclub')) {
            return true;
        }
        if (!function_exists('has_shortcode')) {
            return false;
        }
        foreach ([
            'snookerclub', 'snookerclub_ingeven', 'snookerclub_match', 'snookerclub_admin',
            'snookerclub_live', 'snookerclub_players', 'snookerclub_ranking', 'snookerclub_results',
            'snookerclub_kpis', 'snookerclub_break', 'snookerclub_next', 'snookerclub_agenda',
            'snookerclub_rapport', 'snookerclub_dossier', 'snookerclub_h2h',
        ] as $tag) {
            if (has_shortcode($content, $tag)) {
                return true;
            }
        }
        return false;
    }

    public static function content_has_guest(string $content): bool {
        if ($content === '') {
            return false;
        }
        if (str_contains($content, 'wp:snookerclub/board') && (
            str_contains($content, '"view":"app"')
            || str_contains($content, '"view":"ingeven"')
            || str_contains($content, '"view":"match"')
        )) {
            return true;
        }
        if (!function_exists('has_shortcode')) {
            return str_contains($content, '[snookerclub]')
                || str_contains($content, '[snookerclub_ingeven')
                || str_contains($content, '[snookerclub_match')
                || str_contains($content, '[snookerclub_admin');
        }
        return has_shortcode($content, 'snookerclub')
            || has_shortcode($content, 'snookerclub_ingeven')
            || has_shortcode($content, 'snookerclub_match')
            || has_shortcode($content, 'snookerclub_admin');
    }

    public static function front_contents(): array {
        $chunks = [];
        $seen = [];
        $add = static function ($post) use (&$chunks, &$seen) {
            if (!$post || !isset($post->ID, $post->post_content)) {
                return;
            }
            $id = (int) $post->ID;
            if (isset($seen[$id])) {
                return;
            }
            $seen[$id] = true;
            $chunks[] = (string) $post->post_content;
        };
        if (function_exists('get_post')) {
            $add(get_post());
        }
        if (function_exists('get_queried_object')) {
            $queried = get_queried_object();
            if ($queried instanceof WP_Post) {
                $add($queried);
            }
        }
        if (function_exists('get_queried_object_id') && function_exists('get_post')) {
            $id = (int) get_queried_object_id();
            if ($id) {
                $add(get_post($id));
            }
        }
        return $chunks;
    }

    public static function maybe_enqueue_front(): void {
        if (is_admin()) {
            return;
        }
        $contents = self::front_contents();
        $content = implode("\n", $contents);
        $page_id = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
        $is_app_page = $page_id && $page_id === (int) get_option('snookerclub_page_app');
        $is_match_page = $page_id && $page_id === (int) get_option('snookerclub_page_match');
        $has_block = false;
        if (function_exists('has_block')) {
            foreach ($contents as $chunk) {
                if (has_block('snookerclub/board', $chunk)) {
                    $has_block = true;
                    break;
                }
            }
            if (!$has_block) {
                $has_block = has_block('snookerclub/board');
            }
        }
        $needs_guest = $is_app_page || $is_match_page || self::content_has_guest($content) || $has_block;
        $needs_board = $needs_guest || self::content_has_board($content) || $has_block;
        if ($needs_guest) {
            $view = '';
            if ($is_match_page || str_contains($content, 'snookerclub_ingeven') || str_contains($content, 'snookerclub_match') || str_contains($content, '"view":"ingeven"')) {
                $view = 'match';
            }
            self::enqueue_guest_assets($view);
        }
        if ($needs_board) {
            self::enqueue_board_assets();
        }
    }

    public static function maybe_enqueue_block_assets(): void {
        if (is_admin()) {
            self::enqueue_board_assets();
            return;
        }
        self::maybe_enqueue_front();
    }

    public static function maybe_enqueue_admin(string $hook): void {
        if ($hook === 'snookerclub_page_snookerclub-ingeven') {
            self::enqueue_guest_assets('match');
        }
        if ($hook === 'snookerclub_page_snookerclub-club') {
            self::enqueue_admin_assets();
        }
        if ($hook === 'snookerclub_page_snookerclub-rapporten') {
            self::enqueue_board_assets();
        }
    }

    public static function boot_js(array $extra = []): string {
        $boot = array_merge([
            'SNOOKER_WP' => true,
            'SNOOKER_BASE' => untrailingslashit(wp_parse_url(home_url(self::base_path()), PHP_URL_PATH) ?: self::base_path()),
            'SNOOKER_REST' => self::rest_root(),
        ], $extra);
        if (is_user_logged_in() && function_exists('wp_create_nonce')) {
            $boot['SNOOKER_NONCE'] = wp_create_nonce('wp_rest');
        }
        $js = '';
        foreach ($boot as $key => $value) {
            $js .= 'window.' . $key . '=' . wp_json_encode($value) . ';';
        }
        return $js;
    }

    public static function enqueue_board_assets(): void {
        self::register_board_style();
        wp_enqueue_style('snookerclub-board');
        if (class_exists('Snookerclub_Theme')) {
            wp_add_inline_style('snookerclub-board', Snookerclub_Theme::css_text(self::store()->get_brand()));
        }
        wp_enqueue_script(
            'snookerclub-embed',
            self::asset_url('embed.js'),
            [],
            SNOOKERCLUB_VERSION,
            true
        );
        wp_add_inline_script('snookerclub-embed', self::boot_js(), 'before');
    }

    public static function board_css(): string {
        $path = self::public_dir() . '/board.css';
        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    public static function board_style_tag(): string {
        static $done = false;
        if ($done) {
            return '';
        }
        $printed = function_exists('wp_style_is') && wp_style_is('snookerclub-board', 'done');
        if ($printed) {
            $done = true;
            return '';
        }
        $css = trim(self::board_css());
        if (class_exists('Snookerclub_Theme')) {
            $css .= Snookerclub_Theme::css_text(self::store()->get_brand());
        }
        if ($css === '') {
            return '';
        }
        $done = true;
        return '<style id="snookerclub-board-inline">' . $css . '</style>';
    }

    public static function with_board_styles(string $html): string {
        return self::board_style_tag() . $html;
    }

    public static function enqueue_guest_assets(string $tab = ''): void {
        wp_enqueue_style(
            'snookerclub-fonts',
            'https://fonts.googleapis.com/css2?family=Nunito:wght@500;700;800&family=Inter:wght@400;500;600;700&display=swap',
            [],
            null
        );
        wp_enqueue_style(
            'snookerclub-guest',
            self::asset_url('guest.css'),
            ['snookerclub-fonts'],
            SNOOKERCLUB_VERSION
        );
        if (class_exists('Snookerclub_Theme')) {
            wp_add_inline_style('snookerclub-guest', Snookerclub_Theme::css_text(self::store()->get_brand()));
        }
        wp_enqueue_script(
            'snookerclub-theme',
            self::asset_url('theme.js'),
            [],
            SNOOKERCLUB_VERSION,
            true
        );
        wp_enqueue_script(
            'snookerclub-guest',
            self::asset_url('guest.js'),
            ['snookerclub-theme'],
            SNOOKERCLUB_VERSION,
            true
        );
        $js = self::boot_js($tab !== '' ? ['SNOOKER_TAB' => $tab] : []);
        wp_add_inline_script('snookerclub-guest', $js, 'before');
    }

    public static function guest_inner_html(): string {
        $path = self::public_dir() . '/guest.html';
        if (!is_file($path)) {
            return '<p>Snookerclub-bestanden ontbreken.</p>';
        }
        $html = (string) file_get_contents($path);
        if (preg_match('#<body[^>]*>(.*)</body>#s', $html, $m)) {
            $html = $m[1];
        }
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
        $html = preg_replace('#<a class="ghost" href="/webhost/login">Clubportaal</a>#', '', $html);
        $html = str_replace('href="/webhost/login"', 'href="' . esc_url(wp_login_url(self::app_page_url())) . '"', $html);
        return $html;
    }

    public static function enqueue_admin_assets(): void {
        wp_enqueue_style(
            'snookerclub-fonts',
            'https://fonts.googleapis.com/css2?family=Nunito:wght@500;700;800&family=Inter:wght@400;500;600;700&display=swap',
            [],
            null
        );
        wp_enqueue_style(
            'snookerclub-admin',
            self::asset_url('admin.css'),
            ['snookerclub-fonts'],
            SNOOKERCLUB_VERSION
        );
        if (class_exists('Snookerclub_Theme')) {
            wp_add_inline_style('snookerclub-admin', Snookerclub_Theme::css_text(self::store()->get_brand()));
        }
        wp_enqueue_script(
            'snookerclub-admin',
            self::asset_url('admin.js'),
            [],
            SNOOKERCLUB_VERSION,
            true
        );
        wp_enqueue_script(
            'snookerclub-embed',
            self::asset_url('embed.js'),
            ['snookerclub-admin'],
            SNOOKERCLUB_VERSION,
            true
        );
        wp_add_inline_script('snookerclub-admin', self::boot_js(), 'before');
        wp_add_inline_style('snookerclub-admin', '.snookerclub-wp-admin,.snookerclub-admin-ingeven{max-width:none}.snookerclub-embed--admin{margin-top:12px;border:1px solid rgba(22,53,36,.1);border-radius:16px;overflow:auto}.snookerclub-embed--admin .snooker-app--admin{min-height:70vh}');
    }

    public static function admin_inner_html(): string {
        $path = self::public_dir() . '/admin.html';
        if (!is_file($path)) {
            return '<p>Snookerclub-bestanden ontbreken. Installeer de zip opnieuw.</p>';
        }
        $html = (string) file_get_contents($path);
        if (preg_match('#<body[^>]*>(.*)</body>#s', $html, $m)) {
            $html = $m[1];
        }
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
        $rest = esc_url(self::rest_root());
        $app = esc_url(self::app_page_url());
        $match = esc_url(self::match_page_url());
        $settings = esc_url(admin_url('admin.php?page=snookerclub'));
        $dash = esc_url(admin_url('index.php'));
        $html = preg_replace('#<iframe id="live-frame"[^>]*></iframe>#', '<div id="live-frame" class="live-frame" data-snooker-live data-src="' . $rest . '/live"></div>', $html);
        $html = preg_replace('#<iframe id="players-frame"[^>]*></iframe>#', '<div id="players-frame" class="live-frame live-frame-players" data-snooker-players data-src="' . $rest . '/players"></div>', $html);
        $html = preg_replace('#<iframe id="agenda-frame"[^>]*></iframe>#', '<div id="agenda-frame" class="live-frame live-frame-agenda" data-snooker-agenda data-src="' . $rest . '/agenda"></div>', $html);
        $html = str_replace('href="/webhost/desk"', 'href="' . $settings . '"', $html);
        $html = str_replace('href="/webhost/snooker/#wedstrijd"', 'href="' . $match . '"', $html);
        $html = str_replace('href="/webhost/snooker/"', 'href="' . $app . '"', $html);
        $html = preg_replace('#href="/webhost/snooker/live[^"]*"#', 'href="' . $app . '"', $html);
        $html = str_replace('href="/webhost"', 'href="' . $dash . '"', $html);
        return $html;
    }

    public static function shortcode_guest($atts = []): string {
        unset($atts);
        self::enqueue_guest_assets();
        self::enqueue_board_assets();
        return self::with_board_styles('<div class="snookerclub-embed snookerclub-embed--app">' . self::guest_inner_html() . '</div>');
    }

    public static function shortcode_ingeven($atts = []): string {
        unset($atts);
        self::enqueue_guest_assets('match');
        self::enqueue_board_assets();
        return self::with_board_styles('<div class="snookerclub-embed snookerclub-embed--ingeven">' . self::guest_inner_html() . '</div>');
    }

    public static function shortcode_live($atts = []): string {
        return self::render_board('live', self::shortcode_atts($atts));
    }

    public static function shortcode_players($atts = []): string {
        return self::render_board('ranking', self::shortcode_atts($atts));
    }

    public static function shortcode_results($atts = []): string {
        return self::render_board('results', self::shortcode_atts($atts));
    }

    public static function shortcode_kpis($atts = []): string {
        return self::render_board('kpis', self::shortcode_atts($atts));
    }

    public static function shortcode_break($atts = []): string {
        return self::render_board('break', self::shortcode_atts($atts));
    }

    public static function shortcode_next($atts = []): string {
        return self::render_board('next', self::shortcode_atts($atts));
    }

    public static function shortcode_agenda($atts = []): string {
        return self::render_board('agenda', self::shortcode_atts($atts));
    }

    public static function shortcode_rapport($atts = []): string {
        return self::render_board('rapport', self::shortcode_atts($atts));
    }

    public static function shortcode_dossier($atts = []): string {
        return self::render_board('dossier', self::shortcode_atts($atts));
    }

    public static function shortcode_h2h($atts = []): string {
        return self::render_board('h2h', self::shortcode_atts($atts));
    }

    public static function shortcode_admin(): string {
        if (!self::can_manage()) {
            return '<p>Log in als beheerder om het clubbeheer te openen. Of open <a href="' . esc_url(admin_url('admin.php?page=snookerclub-club')) . '">Snookerclub → Clubbeheer</a>.</p>';
        }
        self::enqueue_admin_assets();
        return '<div class="snookerclub-embed snookerclub-embed--admin">' . self::admin_inner_html() . '</div>';
    }

    public static function admin_menu(): void {
        add_menu_page(
            'Snookerclub',
            'Snookerclub',
            'edit_pages',
            'snookerclub',
            [self::class, 'settings_page'],
            'dashicons-awards',
            58
        );
        add_submenu_page(
            'snookerclub',
            'Clubbeheer',
            'Clubbeheer',
            'edit_pages',
            'snookerclub-club',
            [self::class, 'club_admin_page']
        );
        add_submenu_page(
            'snookerclub',
            'Uitslag invoeren',
            'Uitslag invoeren',
            'edit_pages',
            'snookerclub-ingeven',
            [self::class, 'ingeven_page']
        );
        add_submenu_page(
            'snookerclub',
            'Rapporten',
            'Rapporten',
            'edit_pages',
            'snookerclub-rapporten',
            [self::class, 'reports_page']
        );
    }

    public static function maybe_export_report(): void {
        if (($_GET['page'] ?? '') !== 'snookerclub-rapporten') {
            return;
        }
        $export = self::request_text('export');
        if ($export === '' || !self::can_manage()) {
            return;
        }
        $season = self::request_text('season');
        $player = self::request_text('player');
        if ($export === 'ranking') {
            $report = self::store()->report($season);
            nocache_headers();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="snookerclub-ranglijst.csv"');
            echo Snookerclub_Excel::ranking_csv($report['players'] ?? []);
            exit;
        }
        if ($export === 'dossier' && $player !== '') {
            nocache_headers();
            $dossier = self::store()->player_dossier($player, $season);
            $name = preg_replace('/[^a-z0-9_-]+/i', '-', $player) ?: 'dossier';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="snookerclub-' . $name . '.csv"');
            echo Snookerclub_Excel::dossier_csv($dossier);
            exit;
        }
    }

    public static function reports_page(): void {
        if (!self::can_manage()) {
            wp_die('Geen rechten.');
        }
        self::enqueue_board_assets();
        $store = self::store();
        $season = self::request_text('season');
        $player = self::request_text('player');
        $report = $store->report($season);
        $brand = $report['brand']['clubName'] ?? 'Snookerclub';
        $export_ranking = add_query_arg([
            'page' => 'snookerclub-rapporten',
            'season' => $season,
            'export' => 'ranking',
        ], admin_url('admin.php'));
        echo '<div class="wrap snookerclub-wp-admin snookerclub-reports">';
        echo '<h1>Rapporten</h1>';
        echo '<p class="snookerclub-lead">Ranglijst en dossier uit de clubdata, in dezelfde kolommen als het Excel-blad. Filter op seizoen (augustus–juli), druk af of trek een CSV.</p>';
        echo '<form method="get" class="snooker-report-filters no-print">';
        echo '<input type="hidden" name="page" value="snookerclub-rapporten" />';
        echo '<label>Seizoen <select name="season"><option value="">Alle seizoenen</option>';
        foreach ($report['seasons'] as $item) {
            echo '<option value="' . esc_attr($item) . '"' . selected($season, $item, false) . '>' . esc_html($item) . '</option>';
        }
        echo '</select></label> <label>Speler <select name="player"><option value="">— dossier —</option>';
        $names = [];
        foreach ($store->list_players() as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name !== '') {
                $names[$name] = $name;
            }
        }
        foreach ($report['players'] as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name !== '') {
                $names[$name] = $name;
            }
        }
        ksort($names, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($names as $name) {
            echo '<option value="' . esc_attr($name) . '"' . selected($player, $name, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select></label> ';
        submit_button('Rapport tonen', 'secondary', '', false);
        echo ' <button type="button" class="button button-primary" data-snooker-print="page">Afdrukken</button>';
        echo ' <a class="button" href="' . esc_url($export_ranking) . '">CSV ranglijst</a>';
        if ($player !== '') {
            echo ' <a class="button" href="' . esc_url(add_query_arg([
                'page' => 'snookerclub-rapporten',
                'season' => $season,
                'player' => $player,
                'export' => 'dossier',
            ], admin_url('admin.php'))) . '">CSV dossier</a>';
        }
        echo '</form>';
        echo '<div class="snooker-print-root">';
        echo Snookerclub_Render::rapport($report, ['skin' => 'club', 'view' => 'rapport']);
        if ($player !== '') {
            try {
                $dossier = array_merge($store->player_dossier($player, $season), [
                    'brand' => ['clubName' => $brand],
                    'printedAt' => gmdate('Y-m-d'),
                ]);
                echo Snookerclub_Render::dossier($dossier, ['skin' => 'club', 'view' => 'dossier', 'player' => $player]);
            } catch (Throwable $err) {
                echo '<p class="snookerclub-empty">' . esc_html($err->getMessage()) . '</p>';
            }
        }
        echo '</div>';
        echo '</div>';
    }

    public static function club_admin_page(): void {
        if (!self::can_manage()) {
            wp_die('Geen rechten.');
        }
        self::enqueue_admin_assets();
        echo '<div class="wrap snookerclub-wp-admin">';
        echo '<h1>Clubbeheer</h1>';
        echo '<p>Spelers, uitslagen, agenda en vormgeving staan in deze WordPress-site. Geen externe app nodig.</p>';
        echo '<div class="snookerclub-embed snookerclub-embed--admin">' . self::admin_inner_html() . '</div>';
        echo '</div>';
    }

    public static function ingeven_page(): void {
        self::enqueue_guest_assets('match');
        echo '<div class="wrap snookerclub-admin-ingeven">';
        echo '<h1>Uitslag invoeren</h1>';
        echo '<p>Deze wizard slaat op in WordPress.</p>';
        echo '<div class="snookerclub-embed snookerclub-embed--ingeven">' . self::guest_inner_html() . '</div>';
        echo '</div>';
    }

    public static function register_settings(): void {
        register_setting('snookerclub', 'snookerclub_slug', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_title',
            'default' => 'snooker',
        ]);
        register_setting('snookerclub', 'snookerclub_pretty_urls', [
            'type' => 'string',
            'sanitize_callback' => fn($v) => $v === '1' ? '1' : '0',
            'default' => '0',
        ]);
        register_setting('snookerclub', 'snookerclub_update_url', [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => '',
        ]);
        register_setting('snookerclub', 'snookerclub_auto_update', [
            'type' => 'string',
            'sanitize_callback' => fn($v) => $v === '1' ? '1' : '0',
            'default' => '0',
        ]);
    }

    public static function handle_check_update(): void {
        if (!current_user_can('update_plugins') && !self::can_manage()) {
            wp_die('Geen rechten.');
        }
        check_admin_referer('snookerclub_check_update');
        $feed = Snookerclub_Updater::check_now();
        $notice = $feed
            ? 'Updatefeed geladen: versie ' . $feed['version']
            : 'Geen updatefeed. Vul optioneel een JSON-URL in; de plugin werkt zonder.';
        wp_safe_redirect(add_query_arg([
            'page' => 'snookerclub',
            'snookerclub_notice' => rawurlencode($notice),
        ], admin_url('admin.php')));
        exit;
    }

    public static function settings_page(): void {
        if (isset($_POST['snookerclub_brand_save'])) {
            check_admin_referer('snookerclub_brand');
            $tournaments = preg_split('/\r\n|\r|\n/', (string) wp_unslash($_POST['tournaments'] ?? ''));
            $brand = self::store()->save_brand([
                'clubName' => sanitize_text_field(wp_unslash($_POST['clubName'] ?? '')),
                'tagline' => sanitize_text_field(wp_unslash($_POST['tagline'] ?? '')),
                'venue' => sanitize_text_field(wp_unslash($_POST['venue'] ?? '')),
                'openingHours' => sanitize_text_field(wp_unslash($_POST['openingHours'] ?? '')),
                'notice' => sanitize_text_field(wp_unslash($_POST['notice'] ?? '')),
                'nextEvent' => sanitize_text_field(wp_unslash($_POST['nextEvent'] ?? '')),
                'frameFormat' => sanitize_text_field(wp_unslash($_POST['frameFormat'] ?? 'bestof:5')),
                'tournaments' => array_values(array_filter(array_map('sanitize_text_field', $tournaments ?: []))),
                'showSignatures' => !empty($_POST['showSignatures']),
            ]);
            self::sync_app_page_title((string) ($brand['clubName'] ?? ''));
            echo '<div class="notice notice-success"><p>Clubnaam en clubgegevens opgeslagen. Ze staan op de site, in rapporten en op afdrukbladen.</p></div>';
        }
        if (isset($_POST['snookerclub_theme_save'])) {
            check_admin_referer('snookerclub_theme');
            self::store()->save_brand([
                'themePreset' => sanitize_text_field(wp_unslash($_POST['themePreset'] ?? 'baize')),
                'accent' => sanitize_hex_color(wp_unslash($_POST['accent'] ?? '')) ?: '#2ea85a',
                'accentSoft' => sanitize_hex_color(wp_unslash($_POST['accentSoft'] ?? '')) ?: '#f0b429',
                'bg' => sanitize_hex_color(wp_unslash($_POST['bg'] ?? '')) ?: '#f3f7f0',
                'bgSide' => sanitize_hex_color(wp_unslash($_POST['bgSide'] ?? '')) ?: '#163524',
                'ink' => sanitize_hex_color(wp_unslash($_POST['ink'] ?? '')) ?: '#1d2a22',
                'font' => sanitize_text_field(wp_unslash($_POST['font'] ?? 'club')),
                'radius' => sanitize_text_field(wp_unslash($_POST['radius'] ?? 'club')),
                'density' => sanitize_text_field(wp_unslash($_POST['density'] ?? 'comfortable')),
                'inheritWp' => !empty($_POST['inheritWp']),
            ]);
            echo '<div class="notice notice-success"><p>Vormgeving opgeslagen.</p></div>';
        }
        if (isset($_POST['snookerclub_slug'])) {
            check_admin_referer('snookerclub_settings');
            update_option('snookerclub_slug', sanitize_title(wp_unslash($_POST['snookerclub_slug'])));
            update_option('snookerclub_update_url', esc_url_raw(wp_unslash($_POST['snookerclub_update_url'] ?? '')));
            update_option('snookerclub_auto_update', empty($_POST['snookerclub_auto_update']) ? '0' : '1');
            update_option('snookerclub_pretty_urls', empty($_POST['snookerclub_pretty_urls']) ? '0' : '1');
            self::ensure_pages();
            flush_rewrite_rules();
            echo '<div class="notice notice-success"><p>Instellingen opgeslagen.</p></div>';
        }
        if (!empty($_GET['snookerclub_notice'])) {
            echo '<div class="notice notice-info"><p>' . esc_html(wp_unslash($_GET['snookerclub_notice'])) . '</p></div>';
        }
        $slug = esc_attr(self::slug());
        $feed = esc_attr(get_option('snookerclub_update_url', ''));
        $auto = get_option('snookerclub_auto_update', '0') === '1';
        $pretty = self::pretty_urls();
        $guest = esc_url(self::app_page_url());
        $ingeven = esc_url(self::match_page_url());
        $admin = esc_url(admin_url('admin.php?page=snookerclub-club'));
        $check = wp_nonce_url(admin_url('admin-post.php?action=snookerclub_check_update'), 'snookerclub_check_update');
        echo '<div class="wrap"><h1>Snookerclub</h1>';
        echo '<p>Versie <strong>' . esc_html(SNOOKERCLUB_VERSION) . '</strong>. Uitslagen, spelers en agenda staan in deze WordPress-site. Geen externe club-app nodig.</p>';
        $brand = self::store()->get_brand();
        echo '<h2>Clubgegevens</h2>';
        echo '<p>De clubnaam verschijnt op de ranking, dossiers, liveblokken en afdrukbladen. Standaard staat SC De Merodesnookers (Turnhout) klaar; pas hem hier aan.</p>';
        echo '<form method="post">';
        wp_nonce_field('snookerclub_brand');
        echo '<input type="hidden" name="snookerclub_brand_save" value="1" />';
        echo '<table class="form-table">';
        echo '<tr><th><label for="snooker-club-name">Clubnaam</label></th><td><input id="snooker-club-name" name="clubName" class="regular-text" maxlength="80" required value="' . esc_attr($brand['clubName'] ?? '') . '" />';
        echo '<p class="description">Voorbeeld: SC De Merodesnookers. Mag een andere clubnaam zijn.</p></td></tr>';
        echo '<tr><th>Tagline</th><td><input name="tagline" class="large-text" maxlength="160" value="' . esc_attr($brand['tagline'] ?? '') . '" /></td></tr>';
        echo '<tr><th>Locatie</th><td><input name="venue" class="large-text" maxlength="120" value="' . esc_attr($brand['venue'] ?? '') . '" placeholder="Biljart Palace, Merodecenter 19, 2300 Turnhout" /></td></tr>';
        echo '<tr><th>Clubavond / uren</th><td><input name="openingHours" class="regular-text" maxlength="120" value="' . esc_attr($brand['openingHours'] ?? '') . '" placeholder="Clubavond donderdag vanaf 19u" /></td></tr>';
        echo '<tr><th>Clubbericht</th><td><input name="notice" class="large-text" maxlength="240" value="' . esc_attr($brand['notice'] ?? '') . '" /></td></tr>';
        echo '<tr><th>Volgende avond</th><td><input name="nextEvent" class="large-text" maxlength="160" value="' . esc_attr($brand['nextEvent'] ?? '') . '" /></td></tr>';
        echo '<tr><th>Frames per partij</th><td><select name="frameFormat">';
        $formats = [
            'bestof:1' => '1 frame',
            'bestof:3' => 'Best of 3',
            'bestof:5' => 'Best of 5',
            'bestof:7' => 'Best of 7',
            'bestof:9' => 'Best of 9',
            'bestof:11' => 'Best of 11',
            'bestof:17' => 'Best of 17',
            'fixed:2' => '2 frames (poule)',
            'fixed:3' => '3 frames (poule / voorronde)',
            'fixed:4' => '4 frames (poule)',
            'fixed:5' => '5 frames (vast)',
        ];
        $current_format = (($brand['frameMode'] ?? 'bestof') === 'fixed' ? 'fixed' : 'bestof') . ':' . (int) ($brand['framesCount'] ?? 5);
        foreach ($formats as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($current_format, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select><p class="description">Best of = eerste die de meerderheid wint. Poule / voorronde = vast aantal frames (bij Kersttornooi vaak 3).</p></td></tr>';
        echo '<tr><th>Tornooien</th><td><textarea name="tournaments" rows="6" class="large-text">' . esc_textarea(implode("\n", $brand['tournaments'] ?? [])) . '</textarea>';
        echo '<p class="description">Eén per regel. Bij Merode o.a. Potblack, Rankingtornooi, Kersttornooi, Handicaptornooi, 6 Red, Open Merode. Agenda-items kunnen hieraan gekoppeld worden.</p></td></tr>';
        echo '<tr><th>Handtekeningen</th><td><label><input type="checkbox" name="showSignatures" value="1"' . checked(!empty($brand['showSignatures']), true, false) . ' /> Verplicht bij uitslaginvoer (beide spelers tekenen)</label>';
        echo '<p class="description">Uitvinken = geen handtekeningvelden bij invoer. Handtekeningen verdwijnen ook bij recente uitslagen.</p></td></tr>';
        echo '</table>';
        submit_button('Clubgegevens opslaan');
        echo '</form>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=snookerclub-ingeven')) . '">Uitslag invoeren</a> ';
        echo '<a class="button" href="' . $ingeven . '">Pagina uitslag invoeren</a> ';
        echo '<a class="button" href="' . $guest . '">Clubpagina</a> ';
        echo '<a class="button" href="' . $admin . '">Clubbeheer</a> ';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=snookerclub-rapporten')) . '">Rapporten</a></p>';
        echo '<form method="post">';
        wp_nonce_field('snookerclub_settings');
        echo '<table class="form-table"><tr><th>Pad</th><td><input name="snookerclub_slug" value="' . $slug . '" class="regular-text" /> ';
        echo '<p class="description">WordPress-pagina: <code>' . $guest . '</code>. Bij activeren maakt de plugin deze pagina aan.</p></td></tr>';
        echo '<tr><th>Extra URL\'s</th><td><label><input type="checkbox" name="snookerclub_pretty_urls" value="1"' . checked($pretty, true, false) . ' /> Ook <code>/' . esc_html(self::slug()) . '/</code> als losse app-route (niet nodig voor shortcodes en blokken)</label></td></tr>';
        echo '<tr><th>Updatefeed</th><td><input name="snookerclub_update_url" value="' . $feed . '" class="large-text" placeholder="https://voorbeeld.nl/snookerclub.json" />';
        echo '<p class="description">Optioneel. JSON met <code>version</code> en <code>package</code>. Leeg = geen externe updates.</p></td></tr>';
        echo '<tr><th>Automatisch bijwerken</th><td><label><input type="checkbox" name="snookerclub_auto_update" value="1"' . checked($auto, true, false) . ' /> Installeer nieuwe pluginversies automatisch</label></td></tr>';
        echo '</table>';
        submit_button('Opslaan');
        echo '</form>';
        $theme = class_exists('Snookerclub_Theme') ? Snookerclub_Theme::normalize($brand) : $brand;
        echo '<h2>Vormgeving</h2>';
        echo '<p>Kies een clubthema of eigen kleuren. Ook beschikbaar onder <a href="' . esc_url(admin_url('customize.php?autofocus[section]=snookerclub_theme')) . '">Weergave → Customizer → Snookerclub</a>. Er komt geen link in het sitemenu.</p>';
        echo '<form method="post">';
        wp_nonce_field('snookerclub_theme');
        echo '<input type="hidden" name="snookerclub_theme_save" value="1" />';
        echo '<table class="form-table"><tr><th>Thema</th><td>';
        foreach (['baize' => 'Baize', 'midnight' => 'Nacht', 'ivory' => 'Ivoor', 'classic' => 'Club', 'ruby' => 'Ruby', 'custom' => 'Eigen kleuren'] as $id => $label) {
            echo '<label style="margin-right:12px"><input type="radio" name="themePreset" value="' . esc_attr($id) . '"' . checked($theme['themePreset'] ?? '', $id, false) . ' /> ' . esc_html($label) . '</label>';
        }
        echo '</td></tr>';
        echo '<tr><th>Kleuren</th><td>';
        echo 'Accent <input name="accent" type="color" value="' . esc_attr($theme['accent']) . '" /> ';
        echo 'Tweede <input name="accentSoft" type="color" value="' . esc_attr($theme['accentSoft']) . '" /> ';
        echo 'Achtergrond <input name="bg" type="color" value="' . esc_attr($theme['bg']) . '" /> ';
        echo 'Balk <input name="bgSide" type="color" value="' . esc_attr($theme['bgSide']) . '" /> ';
        echo 'Tekst <input name="ink" type="color" value="' . esc_attr($theme['ink']) . '" /></td></tr>';
        echo '<tr><th>Lettertype</th><td><select name="font">';
        foreach (['club' => 'Club (Nunito / Inter)', 'system' => 'Systeem', 'serif' => 'Schreef'] as $id => $label) {
            echo '<option value="' . esc_attr($id) . '"' . selected($theme['font'] ?? 'club', $id, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th>Hoeken</th><td><select name="radius">';
        foreach (['snug' => 'Strak', 'club' => 'Club', 'round' => 'Rond'] as $id => $label) {
            echo '<option value="' . esc_attr($id) . '"' . selected($theme['radius'] ?? 'club', $id, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th>Dichtheid</th><td><select name="density">';
        foreach (['comfortable' => 'Ruim', 'compact' => 'Compact'] as $id => $label) {
            echo '<option value="' . esc_attr($id) . '"' . selected($theme['density'] ?? 'comfortable', $id, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th>WordPress-thema</th><td><label><input type="checkbox" name="inheritWp" value="1"' . checked(!empty($theme['inheritWp']), true, false) . ' /> Neem kleuren en lettertype van het actieve thema over (naadloos op de site)</label></td></tr>';
        echo '</table>';
        submit_button('Vormgeving opslaan');
        echo '</form>';
        echo '<h2>Templates op de site</h2>';
        echo '<p>In Gutenberg: <strong>Patronen → Snookerclub</strong>, of het blok <strong>Snookerclub</strong>. Elk onderdeel mag op een eigen pagina. Vormgeving: WordPress-thema of clubdashboard (<code>skin="site"</code> / <code>skin="club"</code>).</p>';
        echo '<ul>';
        echo '<li><code>[snookerclub_ranking]</code> — live ranking (podium + tabel)</li>';
        echo '<li><code>[snookerclub_results]</code> — recente uitslagen</li>';
        echo '<li><code>[snookerclub_live]</code> — live overzicht</li>';
        echo '<li><code>[snookerclub_agenda]</code> — maandagenda</li>';
        echo '<li><code>[snookerclub_kpis]</code> — clubcijfers</li>';
        echo '<li><code>[snookerclub_break]</code> — hoogste break</li>';
        echo '<li><code>[snookerclub_next]</code> — volgende clubavond</li>';
        echo '<li><code>[snookerclub_rapport]</code> — afdrukbare ranglijst (Excel-kolommen)</li>';
        echo '<li><code>[snookerclub_dossier]</code> — spelersdossier (VERSUS / RESULT / SEASON)</li>';
        echo '<li><code>[snookerclub_h2h]</code> — head-to-head</li>';
        echo '<li><code>[snookerclub_ingeven]</code> — wedstrijd invoeren</li>';
        echo '<li><code>[snookerclub]</code> — volledige club-app</li>';
        echo '<li><code>[snookerclub_admin]</code> — clubbeheer (ingelogde redacteuren)</li>';
        echo '</ul>';
        echo '<p>Voorbeeld: <code>[snookerclub_ranking skin="site"]</code> op de homepage, <code>[snookerclub_results]</code> op een uitslagenpagina. Versie ' . esc_html(SNOOKERCLUB_VERSION) . '.</p>';
        echo '</div>';
    }
}
