<?php
if (!defined('ABSPATH')) {
    exit;
}

class Snookerclub_Rest {
    public const NS = 'snookerclub/v1';

    public static function init(): void {
        add_action('rest_api_init', [self::class, 'register']);
    }

    public static function root(): string {
        return untrailingslashit(rest_url(self::NS));
    }

    public static function register(): void {
        $public_get = [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
        ];
        register_rest_route(self::NS, '/meta', array_merge($public_get, [
            'callback' => [self::class, 'meta'],
        ]));
        register_rest_route(self::NS, '/overview', array_merge($public_get, [
            'callback' => [self::class, 'overview'],
        ]));
        register_rest_route(self::NS, '/brand', array_merge($public_get, [
            'callback' => [self::class, 'brand'],
        ]));
        register_rest_route(self::NS, '/live', array_merge($public_get, [
            'callback' => [self::class, 'live'],
        ]));
        register_rest_route(self::NS, '/players', array_merge($public_get, [
            'callback' => [self::class, 'players'],
        ]));
        register_rest_route(self::NS, '/roster', array_merge($public_get, [
            'callback' => [self::class, 'roster'],
        ]));
        register_rest_route(self::NS, '/tournaments', array_merge($public_get, [
            'callback' => [self::class, 'tournaments'],
        ]));
        register_rest_route(self::NS, '/agenda', array_merge($public_get, [
            'callback' => [self::class, 'agenda'],
        ]));
        register_rest_route(self::NS, '/rapport', array_merge($public_get, [
            'callback' => [self::class, 'rapport'],
            'args' => [
                'season' => ['type' => 'string', 'required' => false],
                'format' => ['type' => 'string', 'required' => false],
            ],
        ]));
        register_rest_route(self::NS, '/dossier', array_merge($public_get, [
            'callback' => [self::class, 'dossier'],
            'args' => [
                'player' => ['type' => 'string', 'required' => false],
                'season' => ['type' => 'string', 'required' => false],
                'format' => ['type' => 'string', 'required' => false],
            ],
        ]));
        register_rest_route(self::NS, '/h2h', array_merge($public_get, [
            'callback' => [self::class, 'h2h'],
        ]));
        register_rest_route(self::NS, '/matches', [
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => [self::class, 'create_match'],
        ]);

        $admin_read = [
            'methods' => 'GET',
            'permission_callback' => [self::class, 'can_manage'],
        ];
        $admin_write = [
            'permission_callback' => [self::class, 'can_manage'],
        ];
        register_rest_route(self::NS, '/admin/me', array_merge($admin_read, [
            'callback' => [self::class, 'admin_me'],
        ]));
        register_rest_route(self::NS, '/admin/players', [
            ['methods' => 'GET', 'permission_callback' => [self::class, 'can_manage'], 'callback' => [self::class, 'admin_players']],
            ['methods' => 'POST', 'permission_callback' => [self::class, 'can_manage'], 'callback' => [self::class, 'admin_create_player']],
        ]);
        register_rest_route(self::NS, '/admin/players/(?P<id>[^/]+)', [
            ['methods' => 'PUT', 'permission_callback' => [self::class, 'can_manage'], 'callback' => [self::class, 'admin_update_player']],
            ['methods' => 'DELETE', 'permission_callback' => [self::class, 'can_manage'], 'callback' => [self::class, 'admin_delete_player']],
        ]);
        register_rest_route(self::NS, '/admin/matches', [
            ['methods' => 'GET', 'permission_callback' => [self::class, 'can_manage'], 'callback' => [self::class, 'admin_matches']],
            ['methods' => 'POST', 'permission_callback' => [self::class, 'can_manage'], 'callback' => [self::class, 'admin_create_match']],
        ]);
        register_rest_route(self::NS, '/admin/paper', array_merge($admin_write, [
            'methods' => 'POST',
            'callback' => [self::class, 'admin_import_paper'],
        ]));
        register_rest_route(self::NS, '/admin/rapport', array_merge($admin_read, [
            'callback' => [self::class, 'admin_rapport'],
        ]));
        register_rest_route(self::NS, '/admin/dossier', array_merge($admin_read, [
            'callback' => [self::class, 'admin_dossier'],
        ]));
        register_rest_route(self::NS, '/admin/h2h', array_merge($admin_read, [
            'callback' => [self::class, 'admin_h2h'],
        ]));
        register_rest_route(self::NS, '/admin/matches/(?P<id>[^/]+)', [
            ['methods' => 'GET', 'permission_callback' => [self::class, 'can_manage'], 'callback' => [self::class, 'admin_get_match']],
            ['methods' => 'PUT', 'permission_callback' => [self::class, 'can_manage'], 'callback' => [self::class, 'admin_update_match']],
            ['methods' => 'DELETE', 'permission_callback' => [self::class, 'can_manage'], 'callback' => [self::class, 'admin_delete_match']],
        ]);
        register_rest_route(self::NS, '/admin/events', [
            ['methods' => 'GET', 'permission_callback' => [self::class, 'can_manage'], 'callback' => [self::class, 'admin_events']],
            ['methods' => 'POST', 'permission_callback' => [self::class, 'can_manage'], 'callback' => [self::class, 'admin_create_event']],
        ]);
        register_rest_route(self::NS, '/admin/events/(?P<id>[^/]+)', [
            ['methods' => 'PUT', 'permission_callback' => [self::class, 'can_manage'], 'callback' => [self::class, 'admin_update_event']],
            ['methods' => 'DELETE', 'permission_callback' => [self::class, 'can_manage'], 'callback' => [self::class, 'admin_delete_event']],
        ]);
        register_rest_route(self::NS, '/admin/brand', [
            ['methods' => 'GET', 'permission_callback' => [self::class, 'can_manage'], 'callback' => [self::class, 'admin_brand']],
            ['methods' => 'PUT', 'permission_callback' => [self::class, 'can_manage'], 'callback' => [self::class, 'admin_save_brand']],
        ]);
        register_rest_route(self::NS, '/admin/hero', array_merge($admin_write, [
            'methods' => 'PUT',
            'callback' => [self::class, 'admin_hero'],
        ]));
        register_rest_route(self::NS, '/admin/embed', array_merge($admin_read, [
            'callback' => [self::class, 'admin_embed'],
        ]));
    }

    public static function can_manage(): bool {
        return is_user_logged_in() && Snookerclub_Plugin::can_manage();
    }

    public static function store(): Snookerclub_Store {
        return Snookerclub_Plugin::store();
    }

    public static function fail(Throwable $err, int $status = 400) {
        $code = $err instanceof Snookerclub_Invalid ? 400 : $status;
        return new WP_Error('snookerclub', $err->getMessage() ?: 'Interne fout.', ['status' => $code]);
    }

    public static function body(WP_REST_Request $request): array {
        $json = $request->get_json_params();
        return is_array($json) ? $json : [];
    }

    public static function meta() {
        return [
            'app' => ['name' => 'snookerclub', 'version' => SNOOKERCLUB_VERSION, 'platform' => 'wordpress'],
        ];
    }

    public static function overview() {
        return self::store()->overview(Snookerclub_Plugin::base_path());
    }

    public static function brand() {
        return self::store()->get_brand();
    }

    public static function live() {
        $overview = self::overview();
        return [
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
        ];
    }

    public static function players() {
        $overview = self::overview();
        return [
            'brand' => $overview['brand'],
            'players' => $overview['players'],
            'top3' => $overview['top3'],
        ];
    }

    public static function roster() {
        return ['players' => self::store()->list_players()];
    }

    public static function tournaments() {
        $overview = self::overview();
        $names = array_values(array_unique(array_merge($overview['brand']['tournaments'] ?? [], $overview['tournaments'] ?? [])));
        return ['tournaments' => $names];
    }

    public static function rapport(WP_REST_Request $request) {
        $report = self::store()->report((string) ($request->get_param('season') ?? ''));
        if ((string) ($request->get_param('format') ?? '') === 'csv') {
            $response = new WP_REST_Response(Snookerclub_Excel::ranking_csv($report['players'] ?? []));
            $response->header('Content-Type', 'text/csv; charset=utf-8');
            $response->header('Content-Disposition', 'attachment; filename="snookerclub-ranglijst.csv"');
            return $response;
        }
        return $report;
    }

    public static function dossier(WP_REST_Request $request) {
        try {
            $dossier = self::store()->player_dossier(
                (string) ($request->get_param('player') ?? ''),
                (string) ($request->get_param('season') ?? '')
            );
            if ((string) ($request->get_param('format') ?? '') === 'csv') {
                $response = new WP_REST_Response(Snookerclub_Excel::dossier_csv($dossier));
                $response->header('Content-Type', 'text/csv; charset=utf-8');
                $name = preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($dossier['player'] ?? 'dossier')) ?: 'dossier';
                $response->header('Content-Disposition', 'attachment; filename="snookerclub-' . $name . '.csv"');
                return $response;
            }
            return $dossier;
        } catch (Throwable $err) {
            return self::fail($err);
        }
    }

    public static function h2h(WP_REST_Request $request) {
        try {
            return self::store()->head_to_head((string) ($request->get_param('a') ?? ''), (string) ($request->get_param('b') ?? ''));
        } catch (Throwable $err) {
            return self::fail($err);
        }
    }

    public static function agenda(WP_REST_Request $request) {
        return [
            'brand' => self::store()->get_brand(),
            'agenda' => self::store()->agenda($request->get_param('year'), $request->get_param('month')),
        ];
    }

    public static function create_match(WP_REST_Request $request) {
        try {
            Snookerclub_Plugin::guest_limit();
            $brand = self::store()->get_brand();
            $match = self::store()->create_match(self::body($request), [
                'require_signatures' => !empty($brand['showSignatures']),
            ]);
            return new WP_REST_Response([
                'match' => $match,
                'wins' => $match['wins'] ?? Snookerclub_Store::frame_wins($match['frames'] ?? []),
                'highestBreak' => $match['highestBreak'] ?? Snookerclub_Store::match_highest_break($match),
            ], 201);
        } catch (Throwable $err) {
            return self::fail($err);
        }
    }

    public static function admin_me() {
        $user = wp_get_current_user();
        return [
            'user' => [
                'id' => (string) $user->ID,
                'username' => $user->user_login,
                'role' => self::can_manage() ? 'owner' : 'guest',
            ],
            'app' => ['name' => 'snookerclub', 'version' => SNOOKERCLUB_VERSION, 'platform' => 'wordpress'],
        ];
    }

    public static function admin_players() {
        return ['players' => self::store()->list_players()];
    }

    public static function admin_create_player(WP_REST_Request $request) {
        try {
            return new WP_REST_Response(['player' => self::store()->create_player(self::body($request))], 201);
        } catch (Throwable $err) {
            return self::fail($err);
        }
    }

    public static function admin_update_player(WP_REST_Request $request) {
        try {
            $player = self::store()->update_player((string) $request['id'], self::body($request));
            if (!$player) {
                return new WP_Error('snookerclub', 'Speler niet gevonden.', ['status' => 404]);
            }
            return ['player' => $player];
        } catch (Throwable $err) {
            return self::fail($err);
        }
    }

    public static function admin_delete_player(WP_REST_Request $request) {
        try {
            if (!self::store()->delete_player((string) $request['id'])) {
                return new WP_Error('snookerclub', 'Speler niet gevonden.', ['status' => 404]);
            }
            return ['ok' => true];
        } catch (Throwable $err) {
            return self::fail($err);
        }
    }

    public static function admin_matches() {
        return ['matches' => self::store()->list_matches()];
    }

    public static function admin_create_match(WP_REST_Request $request) {
        try {
            $body = self::body($request);
            $match = (($body['source'] ?? '') === 'paper' || isset($body['result']) || isset($body['framesFor']))
                ? self::store()->create_paper_match($body)
                : self::store()->create_match($body, ['require_signatures' => false]);
            return new WP_REST_Response([
                'match' => $match,
                'wins' => $match['wins'],
                'highestBreak' => $match['highestBreak'],
            ], 201);
        } catch (Throwable $err) {
            return self::fail($err);
        }
    }

    public static function admin_import_paper(WP_REST_Request $request) {
        try {
            return new WP_REST_Response(self::store()->import_paper(self::body($request)), 201);
        } catch (Throwable $err) {
            return self::fail($err);
        }
    }

    public static function admin_rapport(WP_REST_Request $request) {
        return self::rapport($request);
    }

    public static function admin_dossier(WP_REST_Request $request) {
        return self::dossier($request);
    }

    public static function admin_h2h(WP_REST_Request $request) {
        return self::h2h($request);
    }

    public static function admin_get_match(WP_REST_Request $request) {
        $match = self::store()->get_match((string) $request['id']);
        if (!$match) {
            return new WP_Error('snookerclub', 'Wedstrijd niet gevonden.', ['status' => 404]);
        }
        return ['match' => $match, 'wins' => $match['wins'], 'highestBreak' => $match['highestBreak']];
    }

    public static function admin_update_match(WP_REST_Request $request) {
        try {
            $match = self::store()->update_match((string) $request['id'], self::body($request));
            if (!$match) {
                return new WP_Error('snookerclub', 'Wedstrijd niet gevonden.', ['status' => 404]);
            }
            return ['match' => $match, 'wins' => $match['wins'], 'highestBreak' => $match['highestBreak']];
        } catch (Throwable $err) {
            return self::fail($err);
        }
    }

    public static function admin_delete_match(WP_REST_Request $request) {
        if (!self::store()->delete_match((string) $request['id'])) {
            return new WP_Error('snookerclub', 'Wedstrijd niet gevonden.', ['status' => 404]);
        }
        return ['ok' => true];
    }

    public static function admin_events() {
        return ['events' => self::store()->list_events()];
    }

    public static function admin_create_event(WP_REST_Request $request) {
        try {
            return new WP_REST_Response(['event' => self::store()->create_event(self::body($request))], 201);
        } catch (Throwable $err) {
            return self::fail($err);
        }
    }

    public static function admin_update_event(WP_REST_Request $request) {
        try {
            $event = self::store()->update_event((string) $request['id'], self::body($request));
            if (!$event) {
                return new WP_Error('snookerclub', 'Agenda-item niet gevonden.', ['status' => 404]);
            }
            return ['event' => $event];
        } catch (Throwable $err) {
            return self::fail($err);
        }
    }

    public static function admin_delete_event(WP_REST_Request $request) {
        if (!self::store()->delete_event((string) $request['id'])) {
            return new WP_Error('snookerclub', 'Agenda-item niet gevonden.', ['status' => 404]);
        }
        return ['ok' => true];
    }

    public static function admin_brand() {
        return self::store()->get_brand();
    }

    public static function admin_save_brand(WP_REST_Request $request) {
        return self::store()->save_brand(self::body($request));
    }

    public static function admin_hero(WP_REST_Request $request) {
        try {
            $body = self::body($request);
            return self::store()->save_hero((string) ($body['image'] ?? ''), Snookerclub_Plugin::hero_url());
        } catch (Throwable $err) {
            return self::fail($err);
        }
    }

    public static function admin_embed() {
        $rest = self::root();
        $page = Snookerclub_Plugin::app_page_url();
        return [
            'iframe' => '',
            'script' => '<div data-snooker-live data-src="' . esc_url($rest . '/live') . '"></div>',
            'playersIframe' => '',
            'playersScript' => '<div data-snooker-players data-src="' . esc_url($rest . '/players') . '"></div>',
            'agendaIframe' => '',
            'agendaScript' => '<div data-snooker-agenda data-src="' . esc_url($rest . '/agenda') . '"></div>',
            'liveUrl' => $page,
            'playersUrl' => $page,
            'agendaUrl' => $page,
            'embedJs' => Snookerclub_Plugin::asset_url('embed.js'),
            'rest' => $rest,
        ];
    }
}
