<?php
if (!defined('ABSPATH')) {
    exit;
}

class Snookerclub_Render {
    public static function esc($value): string {
        return esc_html((string) ($value ?? ''));
    }

    public static function views(): array {
        return [
            'live' => 'Live overzicht',
            'ranking' => 'Live ranking',
            'players' => 'Spelerslijst',
            'results' => 'Recente uitslagen',
            'agenda' => 'Clubagenda',
            'kpis' => 'Clubcijfers',
            'break' => 'Hoogste break',
            'next' => 'Volgende clubavond',
            'ingeven' => 'Uitslag invoeren',
            'app' => 'Club-app',
            'rapport' => 'Ranglijst-rapport',
            'dossier' => 'Spelersdossier',
            'h2h' => 'Head-to-head',
        ];
    }

    public static function skins(): array {
        return [
            'site' => 'WordPress-thema',
            'club' => 'Clubdashboard',
        ];
    }

    public static function normalize_opts(array $opts = []): array {
        $view = (string) ($opts['view'] ?? 'live');
        if ($view === 'match') {
            $view = 'ingeven';
        }
        if ($view === 'players') {
            $view = 'ranking';
        }
        if (!isset(self::views()[$view])) {
            $view = 'live';
        }
        $skin = (($opts['skin'] ?? 'site') === 'club') ? 'club' : 'site';
        $limit = (int) ($opts['limit'] ?? 0);
        if ($limit < 0) {
            $limit = 0;
        }
        if ($limit > 50) {
            $limit = 50;
        }
        return [
            'view' => $view,
            'skin' => $skin,
            'limit' => $limit,
            'player' => trim((string) ($opts['player'] ?? '')),
            'season' => trim((string) ($opts['season'] ?? '')),
            'a' => trim((string) ($opts['a'] ?? '')),
            'b' => trim((string) ($opts['b'] ?? '')),
        ];
    }

    public static function trophy_kind($player, $index = null): string {
        if (is_string($player)) {
            return $player;
        }
        $kind = is_array($player) ? (string) ($player['trophy'] ?? '') : '';
        if ($kind !== '') {
            return $kind;
        }
        $rank = is_array($player) ? (int) ($player['rank'] ?? 0) : 0;
        if ($rank >= 1 && $rank <= 3) {
            return ['', 'gold', 'silver', 'bronze'][$rank];
        }
        if (is_int($index) && $index >= 0 && $index < 3) {
            return ['gold', 'silver', 'bronze'][$index];
        }
        return '';
    }

    public static function trophy(string $kind): string {
        $fill = ['gold' => '#e4c25a', 'silver' => '#c5cdd4', 'bronze' => '#c47a3a'][$kind] ?? '';
        if ($fill === '') {
            return '';
        }
        return '<svg class="snooker-trophy snooker-trophy--' . $kind . '" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path fill="' . $fill . '" d="M7 3h10v2h3a1 1 0 0 1 1 1v2a5 5 0 0 1-4.1 4.9A6 6 0 0 1 13 16.9V18h3v2H8v-2h3v-1.1A6 6 0 0 1 7.1 12.9 5 5 0 0 1 3 8V6a1 1 0 0 1 1-1h3z"/></svg>';
    }

    public static function wrap(string $kind, string $src, string $inner, array $opts = []): string {
        $opts = self::normalize_opts($opts + ['view' => $kind]);
        $attr = 'data-snooker-' . ($kind === 'ranking' ? 'players' : $kind);
        if ($kind === 'live') {
            $attr = 'data-snooker-live';
        }
        if ($kind === 'results') {
            $attr = 'data-snooker-results';
        }
        if ($kind === 'kpis') {
            $attr = 'data-snooker-kpis';
        }
        if ($kind === 'break') {
            $attr = 'data-snooker-break';
        }
        if ($kind === 'next') {
            $attr = 'data-snooker-next';
        }
        $extra = '';
        foreach (['player', 'season', 'a', 'b'] as $key) {
            if (($opts[$key] ?? '') !== '') {
                $extra .= ' data-' . $key . '="' . esc_attr($opts[$key]) . '"';
            }
        }
        if (in_array($opts['view'], ['rapport', 'dossier', 'h2h'], true)) {
            $extra .= ' data-static="1"';
        }
        return '<div class="snooker-live-embed snookerclub-board snookerclub-board--' . esc_attr($opts['skin'])
            . ' snookerclub-board--' . esc_attr($opts['view']) . '" ' . $attr
            . ' data-view="' . esc_attr($opts['view']) . '" data-skin="' . esc_attr($opts['skin'])
            . '" data-src="' . esc_url($src) . '"' . $extra . '>' . $inner . '</div>';
    }

    public static function rest_href(string $path, array $args = []): string {
        $base = rtrim(Snookerclub_Rest::root(), '/') . '/' . ltrim($path, '/');
        $args = array_filter($args, static fn($value) => $value !== '' && $value !== null);
        if (!$args) {
            return $base;
        }
        if (function_exists('add_query_arg')) {
            return add_query_arg($args, $base);
        }
        $parts = [];
        foreach ($args as $key => $value) {
            $parts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
        }
        return $base . '?' . implode('&', $parts);
    }

    public static function brand_name($brand): string {
        if (is_array($brand)) {
            $name = trim((string) ($brand['clubName'] ?? ''));
            return $name !== '' ? $name : 'SC De Merodesnookers';
        }
        $name = trim((string) $brand);
        return $name !== '' ? $name : 'SC De Merodesnookers';
    }

    public static function brand_logo($brand): string {
        return is_array($brand) ? trim((string) ($brand['logoUrl'] ?? '')) : '';
    }

    public static function head($brand, string $title, string $kicker = ''): string {
        $name = self::brand_name($brand);
        $kicker = $kicker !== '' ? $kicker : $name;
        $logo = self::brand_logo($brand);
        $mark = $logo !== '' ? '<img class="snooker-board-logo" alt="" src="' . self::esc($logo) . '" />' : '';
        return '<header class="snooker-board-head">' . $mark . '<p class="snooker-live-kicker">' . self::esc($kicker)
            . '</p><h3 class="snooker-live-title">' . self::esc($title) . '</h3></header>';
    }

    public static function hc($player): string {
        return !empty($player['framesPlayed'])
            ? number_format((float) ($player['handicap'] ?? 0), 1, '.', '')
            : '—';
    }

    public static function board(string $view, array $data, array $opts = []): string {
        $opts = self::normalize_opts($opts + ['view' => $view]);
        switch ($opts['view']) {
            case 'ranking':
                return self::ranking($data, $opts);
            case 'results':
                return self::results($data, $opts);
            case 'agenda':
                return self::agenda($data, $opts);
            case 'kpis':
                return self::kpis($data, $opts);
            case 'break':
                return self::highest_break($data, $opts);
            case 'next':
                return self::next_event($data, $opts);
            case 'rapport':
                return self::rapport($data, $opts);
            case 'dossier':
                return self::dossier($data, $opts);
            case 'h2h':
                return self::h2h($data, $opts);
            default:
                return self::live($data, $opts);
        }
    }

    public static function live(array $data, array $opts = []): string {
        $opts = self::normalize_opts($opts + ['view' => 'live']);
        $brand = $data['brand'] ?? [];
        $high = $data['highestBreak'] ?? [];
        $latest = $data['latest'] ?? ($data['recent'][0] ?? null);
        $score = $latest && !empty($latest['wins']) ? ((int) $latest['wins']['p1'] . '–' . (int) $latest['wins']['p2']) : '—';
        $latest_label = $latest
            ? self::esc($latest['player1'] ?? '') . ' — ' . self::esc($latest['player2'] ?? '')
            : self::esc($data['nextEventLabel'] ?? 'Nog geen wedstrijd');
        $inner = self::head($brand, 'Live overzicht')
            . '<div class="snooker-live-grid">'
            . '<div class="snooker-live-card"><p class="snooker-live-muted">Hoogste break</p><p class="snooker-live-big">'
            . self::esc($high['value'] ?? '—') . '</p><p class="snooker-live-muted">' . self::esc($high['player'] ?? 'Nog geen breaks')
            . '</p></div><div class="snooker-live-card"><p class="snooker-live-muted">Laatste wedstrijd</p><p class="snooker-live-big">'
            . self::esc($score) . '</p><p class="snooker-live-muted">' . $latest_label . '</p></div></div>'
            . self::podium($data['top3'] ?? [])
            . self::results_list(array_slice($data['recent'] ?? [], 0, $opts['limit'] ?: 4), 'Recente uitslagen');
        if (empty($data['top3']) && empty($data['recent']) && !$latest) {
            $inner .= '<p class="snooker-live-muted">Nog geen wedstrijden. Voeg spelers toe onder Snookerclub → Clubbeheer en voer daarna een uitslag in.</p>';
        }
        return self::wrap('live', Snookerclub_Rest::root() . '/live', $inner, $opts);
    }

    public static function players(array $data, array $opts = []): string {
        return self::ranking($data, $opts + ['view' => 'ranking']);
    }

    public static function ranking(array $data, array $opts = []): string {
        $opts = self::normalize_opts($opts + ['view' => 'ranking']);
        $brand = $data['brand'] ?? [];
        $players = $data['players'] ?? [];
        if ($opts['limit']) {
            $players = array_slice($players, 0, $opts['limit']);
        }
        $inner = self::head($brand, 'Ranking')
            . self::podium(array_slice($data['top3'] ?? $players, 0, 3))
            . self::league_table($players);
        return self::wrap('ranking', Snookerclub_Rest::root() . '/players', $inner, $opts);
    }

    public static function results(array $data, array $opts = []): string {
        $opts = self::normalize_opts($opts + ['view' => 'results']);
        $brand = $data['brand'] ?? [];
        $matches = $data['recent'] ?? [];
        if ($opts['limit']) {
            $matches = array_slice($matches, 0, $opts['limit']);
        }
        $inner = self::head($brand, 'Uitslagen') . self::results_list($matches, '');
        return self::wrap('results', Snookerclub_Rest::root() . '/live', $inner, $opts);
    }

    public static function kpis(array $data, array $opts = []): string {
        $opts = self::normalize_opts($opts + ['view' => 'kpis']);
        $brand = $data['brand'] ?? [];
        $kpis = $data['kpis'] ?? [];
        $progress = $kpis['progress'] ?? [];
        $cards = [
            ['Hoogste break', $data['highestBreak']['value'] ?? '—', $data['highestBreak']['player'] ?? 'Nog geen breaks', $progress['break']['pct'] ?? 0],
            ['Deze maand', $kpis['matchesMonth'] ?? ($data['matchCount'] ?? 0), ($progress['month']['value'] ?? 0) . ' van ' . ($progress['month']['goal'] ?? 0), $progress['month']['pct'] ?? 0],
            ['Actieve spelers', $kpis['activePlayers'] ?? ($data['playerCount'] ?? 0), ($kpis['rosterCount'] ?? 0) . ' op de lijst', $progress['active']['pct'] ?? 0],
            ['Nieuwe leden', $kpis['newMembersMonth'] ?? 0, '+' . ($kpis['newMembersMonth'] ?? 0) . ' deze maand', $progress['members']['pct'] ?? 0],
            ['Clubavonden', $kpis['clubNightsMonth'] ?? 0, ($kpis['clubNightsUpcoming'] ?? 0) . ' gepland', $progress['nights']['pct'] ?? 0],
        ];
        $grid = '<div class="snooker-kpi-grid">';
        foreach ($cards as $card) {
            $pct = max(0, min(100, (int) $card[3]));
            $grid .= '<article class="snooker-live-card"><p class="snooker-live-muted">' . self::esc($card[0])
                . '</p><p class="snooker-live-big">' . self::esc($card[1]) . '</p><p class="snooker-live-muted">'
                . self::esc($card[2]) . '</p><div class="snooker-meter" aria-hidden="true"><span style="width:'
                . $pct . '%"></span></div></article>';
        }
        $grid .= '</div>';
        return self::wrap('kpis', Snookerclub_Rest::root() . '/overview', self::head($brand, 'Clubcijfers') . $grid, $opts);
    }

    public static function highest_break(array $data, array $opts = []): string {
        $opts = self::normalize_opts($opts + ['view' => 'break']);
        $brand = $data['brand'] ?? [];
        $high = $data['highestBreak'] ?? [];
        $inner = self::head($brand, 'Hoogste break')
            . '<div class="snooker-live-card snooker-break-hero"><p class="snooker-live-big">'
            . self::esc($high['value'] ?? '—') . '</p><p class="snooker-live-muted">'
            . self::esc($high['player'] ?? 'Nog geen breaks')
            . (!empty($high['date']) ? ' · ' . self::esc($high['date']) : '')
            . '</p></div>';
        return self::wrap('break', Snookerclub_Rest::root() . '/live', $inner, $opts);
    }

    public static function next_event(array $data, array $opts = []): string {
        $opts = self::normalize_opts($opts + ['view' => 'next']);
        $brand = $data['brand'] ?? [];
        $event = $data['nextEvent'] ?? null;
        $label = $data['nextEventLabel'] ?? 'Nog geen clubavond gepland';
        if (is_array($event)) {
            $time = implode('–', array_filter([$event['start'] ?? '', $event['end'] ?? '']));
            $inner = self::head($brand, 'Volgende clubavond')
                . '<div class="snooker-live-card"><p class="snooker-live-muted">'
                . self::esc($event['kindLabel'] ?? $event['kind'] ?? 'Clubavond') . '</p><p class="snooker-live-big">'
                . self::esc($event['title'] ?? 'Clubavond') . '</p><p class="snooker-live-muted">'
                . self::esc($event['date'] ?? '') . ($time !== '' ? ' · ' . self::esc($time) : '')
                . (!empty($event['place']) ? ' · ' . self::esc($event['place']) : '') . '</p></div>';
        } else {
            $inner = self::head($brand, 'Volgende clubavond')
                . '<p class="snooker-live-muted">' . self::esc($label) . '. Voeg avonden toe onder Snookerclub → Clubbeheer.</p>';
        }
        return self::wrap('next', Snookerclub_Rest::root() . '/overview', $inner, $opts);
    }

    public static function agenda(array $data, array $opts = []): string {
        $opts = self::normalize_opts($opts + ['view' => 'agenda']);
        $agenda = $data['agenda'] ?? [];
        $brand = $data['brand'] ?? [];
        $week = '';
        foreach ($agenda['weekdays'] ?? ['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'] as $day) {
            $week .= '<span>' . self::esc($day) . '</span>';
        }
        $cal = '';
        foreach ($agenda['days'] ?? [] as $day) {
            if (!empty($day['empty'])) {
                $cal .= '<div class="snooker-live-day empty"></div>';
                continue;
            }
            $first = ($day['events'] ?? [])[0] ?? null;
            $cal .= '<div class="snooker-live-day' . (!empty($day['today']) ? ' today' : '') . '"><strong>'
                . (int) ($day['day'] ?? 0) . '</strong>'
                . ($first ? '<small>' . self::esc($first['title'] ?? '') . '</small>' : '') . '</div>';
        }
        $upcoming = '';
        foreach ($agenda['upcoming'] ?? [] as $event) {
            $time = implode('–', array_filter([$event['start'] ?? '', $event['end'] ?? '']));
            $upcoming .= '<div class="snooker-live-row"><span>' . self::esc($event['kindLabel'] ?? $event['kind'] ?? '')
                . ' · ' . self::esc($event['title'] ?? '')
                . (!empty($event['tournament']) ? ' · ' . self::esc($event['tournament']) : '')
                . '</span><strong>'
                . self::esc($event['date'] ?? '') . ($time !== '' ? ' · ' . self::esc($time) : '') . '</strong></div>';
        }
        if ($upcoming === '') {
            $upcoming = '<p class="snooker-live-muted">Nog geen clubavonden gepland. Voeg ze toe onder Snookerclub → Clubbeheer.</p>';
        }
        $inner = self::head($brand, $agenda['label'] ?? 'Clubagenda')
            . '<div class="snooker-live-weekdays">' . $week . '</div>'
            . '<div class="snooker-live-cal">' . $cal . '</div>' . $upcoming;
        return self::wrap('agenda', Snookerclub_Rest::root() . '/agenda', $inner, $opts);
    }

    public static function podium(array $players): string {
        $top = array_values(array_filter($players, fn($p) => !empty($p['trophy']) || !empty($p['played']) || !empty($p['points']) || (int) ($p['rank'] ?? 0) > 0));
        $top = array_slice($top, 0, 3);
        if (!$top) {
            return '';
        }
        $html = '<div class="snooker-podium" role="list">';
        foreach ($top as $index => $player) {
            $kind = self::trophy_kind($player, $index);
            $html .= '<article class="snooker-seat ' . esc_attr($kind) . '" role="listitem">'
                . self::trophy($kind)
                . '<strong class="snooker-seat-name">' . self::esc($player['name'] ?? '') . '</strong>'
                . '<p>' . (int) ($player['points'] ?? 0) . ' ptn · HC ' . self::esc(self::hc($player)) . '</p></article>';
        }
        return $html . '</div>';
    }

    public static function league_table(array $players): string {
        $rows = '';
        foreach ($players as $index => $player) {
            $kind = self::trophy_kind($player, $index);
            $match_pct = isset($player['matchPct']) ? number_format((float) $player['matchPct'], 2, ',', '') . '%' : ((int) ($player['winRate'] ?? 0)) . '%';
            $frame_pct = isset($player['framePct']) ? number_format((float) $player['framePct'], 2, ',', '') . '%' : '0,00%';
            if (!empty($player['framesPlayed']) && ($player['avgPoints'] ?? $player['handicap'] ?? null) !== null) {
                $avg = self::esc(self::hc($player));
            } elseif (($player['avgFrames'] ?? null) !== null) {
                $avg = self::esc(number_format((float) $player['avgFrames'], 2, ',', ''));
            } else {
                $avg = '—';
            }
            $rows .= '<tr>'
                . '<td class="num">' . (int) ($player['rank'] ?? 0) . '</td>'
                . '<td class="name">' . self::trophy($kind) . self::esc($player['name'] ?? '') . '</td>'
                . '<td class="num">' . (int) ($player['wins'] ?? 0) . '</td>'
                . '<td class="num">' . (int) ($player['losses'] ?? 0) . '</td>'
                . '<td class="num col-fplus">' . (int) ($player['framesFor'] ?? 0) . '</td>'
                . '<td class="num">' . (int) ($player['framesAgainst'] ?? 0) . '</td>'
                . '<td class="num">' . self::esc($match_pct) . '</td>'
                . '<td class="num">' . self::esc($frame_pct) . '</td>'
                . '<td class="num">' . self::esc(($player['highestBreak'] ?? 0) ?: '—') . '</td>'
                . '<td class="num">' . $avg . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            return '<p class="snooker-live-muted">Nog geen spelers. Open Snookerclub → Clubbeheer en voeg namen toe.</p>';
        }
        return self::table_wrap(
            '<table class="snooker-league snooker-sheet-table">'
            . '<thead><tr><th>#</th><th>Speler</th><th>W</th><th>L</th><th class="col-fplus">F+</th><th>F-</th><th>M%</th><th>F%</th><th>HB</th><th>Gem.</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>'
        );
    }

    /** Wide tables become a swipe slider on narrow screens (via CSS + embed.js). */
    public static function table_wrap(string $inner): string {
        return '<div class="snooker-slider-shell">'
            . '<div class="snooker-table-wrap snooker-slider-track" tabindex="0" role="region" aria-label="Tabel, veeg horizontaal voor meer kolommen">'
            . $inner
            . '</div>'
            . '<p class="snooker-slider-hint no-print" hidden>Veeg om meer te zien</p>'
            . '<span class="snooker-slider-fade snooker-slider-fade--left" aria-hidden="true"></span>'
            . '<span class="snooker-slider-fade snooker-slider-fade--right" aria-hidden="true"></span>'
            . '</div>';
    }

    public static function toolbar(string $title = 'Afdrukken', string $csv = ''): string {
        $csv_btn = $csv !== ''
            ? '<a class="snooker-print-btn snooker-csv-btn" href="' . esc_url($csv) . '">CSV</a>'
            : '';
        return '<div class="snooker-report-toolbar no-print">'
            . '<button type="button" class="snooker-print-btn" data-snooker-print="sheet">' . self::esc($title) . '</button>'
            . $csv_btn
            . '</div>';
    }

    public static function sheet_head($brand, string $title, string $season = '', string $printed = ''): string {
        $name = self::brand_name($brand);
        $logo = self::brand_logo($brand);
        $mark = $logo !== '' ? '<img class="snooker-sheet-logo" alt="" src="' . self::esc($logo) . '" />' : '';
        $meta = array_filter([$season !== '' ? 'Seizoen ' . $season : 'Alle seizoenen', $printed !== '' ? 'Afgedrukt ' . $printed : '']);
        return '<header class="snooker-sheet-head">' . $mark
            . '<p class="snooker-sheet-brand">' . self::esc($name) . '</p>'
            . '<h3 class="snooker-sheet-title">' . self::esc($title) . '</h3>'
            . '<p class="snooker-sheet-meta">' . self::esc(implode(' · ', $meta)) . '</p>'
            . '</header>';
    }

    public static function rapport(array $data, array $opts = []): string {
        $opts = self::normalize_opts($opts + ['view' => 'rapport']);
        $brand = $data['brand'] ?? [];
        $players = $data['players'] ?? [];
        if ($opts['limit']) {
            $players = array_slice($players, 0, $opts['limit']);
        }
        $season = (string) ($data['season'] ?? $opts['season'] ?? '');
        $csv = self::rest_href('rapport', ['format' => 'csv', 'season' => $season]);
        $src = self::rest_href('rapport', ['season' => $season]);
        $inner = self::toolbar('Ranglijst afdrukken', $csv)
            . self::sheet_head($brand, 'Ranglijst', $season, (string) ($data['printedAt'] ?? ''))
            . self::league_table($players);
        return self::wrap('rapport', $src, $inner, $opts + ['season' => $season]);
    }

    public static function career_cards(array $career): string {
        $cards = [
            ['Winst', (string) ($career['winsMatches'] ?? 0)],
            ['Verlies', (string) ($career['lossesMatches'] ?? 0)],
            ['M%', isset($career['matchPct']) ? number_format((float) $career['matchPct'], 2, ',', '') . '%' : '0,00%'],
            ['Hoogste', (string) (($career['highestBreak'] ?? 0) ?: '—')],
            ['Gem. break', isset($career['gemBreak']) ? number_format((float) $career['gemBreak'], 2, ',', '') : '—'],
            ['Gem. punten', array_key_exists('avgPoints', $career) && $career['avgPoints'] !== null ? number_format((float) $career['avgPoints'], 2, ',', '') : '—'],
            ['Gem. F+', array_key_exists('avgFrames', $career) && $career['avgFrames'] !== null ? number_format((float) $career['avgFrames'], 2, ',', '') : '—'],
            ['F+', (string) ($career['winsFrames'] ?? 0)],
            ['F-', (string) ($career['lossesFrames'] ?? 0)],
            ['F%', isset($career['framePct']) ? number_format((float) $career['framePct'], 2, ',', '') . '%' : '0,00%'],
        ];
        $html = '<div class="snooker-career-grid">';
        foreach ($cards as $card) {
            $html .= '<article class="snooker-career-card"><p class="snooker-live-muted">' . self::esc($card[0])
                . '</p><p class="snooker-live-big">' . self::esc($card[1]) . '</p></article>';
        }
        return $html . '</div>';
    }

    public static function dossier_table(array $rows): string {
        if (!$rows) {
            return '<p class="snooker-live-muted">Nog geen partijen in dit dossier.</p>';
        }
        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr>'
                . '<td>' . self::esc($row['versus'] ?? '') . '</td>'
                . '<td>' . self::esc($row['tournament'] ?? '') . '</td>'
                . '<td class="num">' . self::esc(str_replace('-', '/', (string) ($row['result'] ?? ''))) . '</td>'
                . '<td class="num">' . (int) ($row['w'] ?? 0) . '</td>'
                . '<td class="num">' . (int) ($row['l'] ?? 0) . '</td>'
                . '<td class="num">' . self::esc(($row['breaks'] ?? 0) ?: '') . '</td>'
                . '<td>' . self::esc($row['roundLabel'] ?? $row['round'] ?? '') . '</td>'
                . '<td>' . self::esc($row['season'] ?? '') . '</td>'
                . '</tr>';
        }
        return self::table_wrap(
            '<table class="snooker-league snooker-sheet-table">'
            . '<thead><tr><th>VERSUS</th><th>TOURNAMENT</th><th>RESULT</th><th>W</th><th>L</th><th>BREAKS</th><th>ROUND</th><th>SEASON</th></tr></thead>'
            . '<tbody>' . $body . '</tbody></table>'
        );
    }

    public static function h2h_table(array $rows): string {
        if (!$rows) {
            return '<p class="snooker-live-muted">Nog geen onderlinge partijen.</p>';
        }
        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr>'
                . '<td>' . self::esc($row['versus'] ?? '') . '</td>'
                . '<td class="num">' . (int) ($row['wins'] ?? 0) . '</td>'
                . '<td class="num">' . (int) ($row['losses'] ?? 0) . '</td>'
                . '<td class="num">' . (int) ($row['framesFor'] ?? 0) . '</td>'
                . '<td class="num">' . (int) ($row['framesAgainst'] ?? 0) . '</td>'
                . '<td class="num">' . self::esc($row['lastResult'] ?? '') . '</td>'
                . '</tr>';
        }
        return self::table_wrap(
            '<table class="snooker-league snooker-sheet-table">'
            . '<thead><tr><th>VERSUS</th><th>W</th><th>L</th><th>F+</th><th>F-</th><th>Laatste</th></tr></thead>'
            . '<tbody>' . $body . '</tbody></table>'
        );
    }

    public static function dossier(array $data, array $opts = []): string {
        $opts = self::normalize_opts($opts + ['view' => 'dossier']);
        $brand = $data['brand'] ?? [];
        $player = (string) ($data['player'] ?? $opts['player'] ?? '');
        $season = (string) ($data['season'] ?? $opts['season'] ?? '');
        $csv = $player !== '' ? self::rest_href('dossier', ['player' => $player, 'season' => $season, 'format' => 'csv']) : '';
        $inner = self::toolbar('Dossier afdrukken', $csv)
            . self::sheet_head($brand, $player !== '' ? $player : 'Spelersdossier', $season, (string) ($data['printedAt'] ?? ''))
            . self::career_cards($data['career'] ?? [])
            . '<h4 class="snooker-subhead">Head-to-head</h4>'
            . self::h2h_table($data['headToHead'] ?? [])
            . '<h4 class="snooker-subhead">Partijlog</h4>'
            . self::dossier_table($data['rows'] ?? []);
        return self::wrap('dossier', self::rest_href('dossier', ['player' => $player, 'season' => $season]), $inner, $opts + ['player' => $player, 'season' => $season]);
    }

    public static function h2h(array $data, array $opts = []): string {
        $opts = self::normalize_opts($opts + ['view' => 'h2h']);
        $brand = $data['brand'] ?? [];
        $title = trim(($data['a'] ?? '') . ' — ' . ($data['b'] ?? ''));
        $inner = self::toolbar()
            . self::sheet_head($brand, $title !== '—' ? $title : 'Head-to-head', (string) ($data['season'] ?? ''), (string) ($data['printedAt'] ?? ''))
            . self::h2h_table($data['rows'] ? [[
                'versus' => $data['b'] ?? '',
                'wins' => $data['wins'] ?? 0,
                'losses' => $data['losses'] ?? 0,
                'framesFor' => $data['framesFor'] ?? 0,
                'framesAgainst' => $data['framesAgainst'] ?? 0,
                'lastResult' => $data['lastResult'] ?? '',
            ]] : ($data['headToHead'] ?? []))
            . self::dossier_table($data['rows'] ?? []);
        return self::wrap('h2h', Snookerclub_Rest::root() . '/h2h', $inner, $opts);
    }

    public static function results_list(array $matches, string $title = ''): string {
        if (!$matches) {
            return $title === ''
                ? '<p class="snooker-live-muted">Nog geen uitslagen. Voer een wedstrijd in via Snookerclub → Uitslag invoeren.</p>'
                : '';
        }
        $html = $title !== '' ? '<h4 class="snooker-subhead">' . self::esc($title) . '</h4>' : '';
        $html .= '<ul class="snooker-results">';
        foreach ($matches as $match) {
            $wins = $match['wins'] ?? ['p1' => 0, 'p2' => 0];
            $html .= '<li><span class="who">' . self::esc($match['player1'] ?? '') . ' — ' . self::esc($match['player2'] ?? '')
                . '</span><strong>' . (int) $wins['p1'] . '–' . (int) $wins['p2'] . '</strong>'
                . '<span class="meta">' . self::esc($match['date'] ?? '')
                . (!empty($match['tournament']) ? ' · ' . self::esc($match['tournament']) : '')
                . '</span></li>';
        }
        return $html . '</ul>';
    }
}
