<?php
/**
 * Club state for the WordPress plugin — same rules as webhost/snooker/lib/store.js.
 */

if (!defined('SNOOKERCLUB_STORE')) {
    define('SNOOKERCLUB_STORE', true);
}
if (!class_exists('Snookerclub_Theme')) {
    require_once __DIR__ . '/class-theme.php';
}
if (!class_exists('Snookerclub_Excel')) {
    require_once __DIR__ . '/class-excel.php';
}

class Snookerclub_Invalid extends Exception {
    public $code_name = 'INVALID';
}

class Snookerclub_Store {
    public const EVENT_KINDS = ['clubavond', 'toernooi', 'les', 'overig'];
    public const EVENT_KIND_LABELS = [
        'clubavond' => 'Clubavond',
        'toernooi' => 'Toernooi',
        'les' => 'Les',
        'overig' => 'Overig',
    ];
    public const MATCH_TYPES = [
        'competitie', 'vriendschappelijk', 'beker', 'finale',
        'potblack', 'ranking', 'handicap', '6red', 'open',
    ];
    public const MATCH_TYPE_LABELS = [
        'competitie' => 'Competitie',
        'vriendschappelijk' => 'Vriendschappelijk',
        'beker' => 'Beker',
        'finale' => 'Finale',
        'potblack' => 'Potblack',
        'ranking' => 'Rankingtornooi',
        'handicap' => 'Handicaptornooi',
        '6red' => '6 Red',
        'open' => 'Open Merode',
    ];
    public const MAX_BREAK = 155;
    public const CLUB_TZ = 'Europe/Amsterdam';

    public const DEFAULT_BRAND = [
        'clubName' => 'SC De Merodesnookers',
        'tagline' => 'Clubavonden, tornooien en uitslagen in Biljart Palace.',
        'accent' => '#2ea85a',
        'accentSoft' => '#f0b429',
        'logoUrl' => '',
        'heroUrl' => 'hero.jpg',
        'showSignatures' => true,
        'framesCount' => 5,
        'tournaments' => [
            'Potblack',
            'Rankingtornooi',
            'Kersttornooi',
            'Handicaptornooi',
            '6 Red',
            'Open Merode',
            'Clubkampioenschap',
        ],
        'notice' => '',
        'venue' => 'Biljart Palace, Merodecenter 19, 2300 Turnhout',
        'openingHours' => 'Clubavond donderdag vanaf 19u',
        'nextEvent' => '',
        'goalMatchesMonth' => 12,
        'goalMatchesWeek' => 4,
        'goalFramesMonth' => 40,
        'goalCenturies' => 3,
        'goalNewMembersMonth' => 5,
        'goalClubNightsMonth' => 4,
        'themePreset' => 'baize',
        'bg' => '#f3f7f0',
        'bgSide' => '#163524',
        'surface' => '#ffffff',
        'surface2' => '#eef6ee',
        'ink' => '#1d2a22',
        'muted' => '#5b6d61',
        'topbarInk' => '#f4fff6',
        'font' => 'club',
        'radius' => 'club',
        'density' => 'comfortable',
        'inheritWp' => false,
    ];

    private string $data_dir;
    private string $file;
    private string $hero_file;
    private ?array $cache = null;
    private bool $use_wp;

    public function __construct(?string $data_dir = null) {
        $this->use_wp = $data_dir === null && function_exists('get_option');
        $this->data_dir = $data_dir ?: sys_get_temp_dir() . '/snookerclub';
        $this->file = $this->data_dir . '/club.json';
        $this->hero_file = $this->data_dir . '/hero-custom';
    }

    public static function invalid(string $message): Snookerclub_Invalid {
        return new Snookerclub_Invalid($message);
    }

    public static function clean_name($value): string {
        $name = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
        return substr($name, 0, 80);
    }

    public static function nl_cmp(string $a, string $b): int {
        if (class_exists('Collator')) {
            $cmp = (new Collator('nl'))->compare($a, $b);
            return $cmp === false ? strcasecmp($a, $b) : $cmp;
        }
        return strcasecmp($a, $b);
    }

    public static function uuid(): string {
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }

    public static function score($value, int $max = 200): ?int {
        if (!is_numeric($value) && $value !== '0' && $value !== 0) {
            $n = filter_var($value, FILTER_VALIDATE_INT);
        } else {
            $n = intval($value);
        }
        if (!is_int($n) && !is_numeric($value)) {
            return null;
        }
        $n = intval($value);
        if ($n < 0 || $n > $max) {
            return null;
        }
        return $n;
    }

    public static function club_today($now = null): string {
        $tz = new DateTimeZone(self::CLUB_TZ);
        if ($now === null) {
            return (new DateTimeImmutable('now', $tz))->format('Y-m-d');
        }
        $seconds = (is_int($now) || is_float($now)) && $now > 1e12 ? (int) ($now / 1000) : (int) $now;
        return (new DateTimeImmutable('@' . $seconds))->setTimezone($tz)->format('Y-m-d');
    }

    public static function iso_week_start(string $today = ''): string {
        $today = $today !== '' ? $today : self::club_today();
        [$year, $month, $day] = array_map('intval', explode('-', $today));
        $date = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), new DateTimeZone('UTC'));
        $weekday = ((int) $date->format('w') + 6) % 7;
        return $date->modify('-' . $weekday . ' days')->format('Y-m-d');
    }

    public static function frame_wins(array $frames): array {
        $a = 0;
        $b = 0;
        foreach ($frames as $frame) {
            $p1 = (int) ($frame['p1'] ?? 0);
            $p2 = (int) ($frame['p2'] ?? 0);
            if ($p1 > $p2) {
                $a++;
            } elseif ($p2 > $p1) {
                $b++;
            }
        }
        return ['p1' => $a, 'p2' => $b];
    }

    public static function frame_totals(array $frames): array {
        $sum = ['p1' => 0, 'p2' => 0];
        foreach ($frames as $frame) {
            $sum['p1'] += (int) ($frame['p1'] ?? 0);
            $sum['p2'] += (int) ($frame['p2'] ?? 0);
        }
        return $sum;
    }

    public static function played_frames(array $frames): array {
        return array_values(array_filter($frames, function ($frame) {
            return ((int) ($frame['p1'] ?? 0)) + ((int) ($frame['p2'] ?? 0)) > 0;
        }));
    }

    public static function average_points(array $values): float {
        if (!$values) {
            return 0;
        }
        return round(array_sum($values) / count($values) * 10) / 10;
    }

    public static function match_averages(array $frames): array {
        $played = self::played_frames($frames);
        return [
            'handicap1' => self::average_points(array_map(fn($f) => (int) ($f['p1'] ?? 0), $played)),
            'handicap2' => self::average_points(array_map(fn($f) => (int) ($f['p2'] ?? 0), $played)),
            'framesPlayed' => count($played),
        ];
    }

    public static function match_highest_break(array $match): array {
        $one = (int) ($match['break1'] ?? 0);
        $two = (int) ($match['break2'] ?? 0);
        if ($one >= $two && $one > 0) {
            return ['value' => $one, 'player' => $match['player1'] ?? ''];
        }
        if ($two > 0) {
            return ['value' => $two, 'player' => $match['player2'] ?? ''];
        }
        return ['value' => 0, 'player' => ''];
    }

    public static function decorate_match(array $match): array {
        $wins = self::frame_wins($match['frames'] ?? []);
        $totals = self::frame_totals($match['frames'] ?? []);
        $averages = self::match_averages($match['frames'] ?? []);
        $winner = $wins['p1'] === $wins['p2'] ? '' : ($wins['p1'] > $wins['p2'] ? $match['player1'] : $match['player2']);
        $centuries = count(array_filter([
            $match['break1'] ?? 0,
            $match['break2'] ?? 0,
        ], fn($v) => (int) $v >= 100));
        $points_known = Snookerclub_Excel::has_real_points($match['frames'] ?? []);
        return array_merge($match, [
            'season' => $match['season'] ?? Snookerclub_Excel::club_season($match['date'] ?? ''),
            'round' => Snookerclub_Excel::normalize_round($match['round'] ?? ''),
            'source' => $match['source'] ?? ($points_known ? 'guest' : 'paper'),
            'pointsKnown' => $points_known,
            'wins' => $wins,
            'totals' => $totals,
            'handicap1' => $points_known ? $averages['handicap1'] : 0,
            'handicap2' => $points_known ? $averages['handicap2'] : 0,
            'framesPlayed' => $averages['framesPlayed'],
            'winner' => $winner,
            'highestBreak' => self::match_highest_break($match),
            'centuries' => $centuries,
            'close' => abs($wins['p1'] - $wins['p2']) === 1 && ($wins['p1'] + $wins['p2']) > 0,
            'signature1' => $match['signature1'] ?? $match['signature'] ?? '',
            'signature2' => $match['signature2'] ?? '',
            'signed' => (bool) (($match['signature1'] ?? $match['signature'] ?? '') && ($match['signature2'] ?? '')),
        ]);
    }

    public static function resolve_roster_player(array $roster, $value): ?array {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        foreach ($roster as $row) {
            if (($row['id'] ?? '') === $raw) {
                return $row;
            }
        }
        $name = strtolower(self::clean_name($raw));
        foreach ($roster as $row) {
            if (strtolower((string) ($row['name'] ?? '')) === $name) {
                return $row;
            }
        }
        return null;
    }

    public static function normalize_frames($input, int $count = 5): array {
        $rows = is_array($input) ? $input : [];
        $frames = [];
        for ($i = 0; $i < $count; $i++) {
            $row = $rows[$i] ?? [];
            $p1 = self::score($row['p1'] ?? $row['player1'] ?? 0);
            $p2 = self::score($row['p2'] ?? $row['player2'] ?? 0);
            if ($p1 === null || $p2 === null) {
                throw self::invalid('Frame ' . ($i + 1) . ' heeft ongeldige punten.');
            }
            $frames[] = ['p1' => $p1, 'p2' => $p2];
        }
        return $frames;
    }

    public static function normalize_signature($raw): string {
        if (!$raw) {
            return '';
        }
        $value = (string) $raw;
        if (!str_starts_with($value, 'data:image/')) {
            return '';
        }
        if (strlen($value) > 140000) {
            throw self::invalid('Handtekening is te groot.');
        }
        return $value;
    }

    public static function normalize_match(array $input, array $opts = []): array {
        $existing = $opts['existing'] ?? null;
        $roster = $opts['roster'] ?? null;
        $tournament = self::clean_name($input['tournament'] ?? '');
        $date = trim((string) ($input['date'] ?? ''));
        $player1 = self::clean_name($input['player1'] ?? '');
        $player2 = self::clean_name($input['player2'] ?? '');
        if ($tournament === '') {
            throw self::invalid('Vul een toernooi in.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw self::invalid('Datum moet jjjj-mm-dd zijn.');
        }
        if ($roster !== null) {
            if (!$roster) {
                throw self::invalid('Voeg eerst spelers toe in het clubbeheer.');
            }
            $one = self::resolve_roster_player($roster, $input['player1Id'] ?? $input['player1'] ?? '');
            $two = self::resolve_roster_player($roster, $input['player2Id'] ?? $input['player2'] ?? '');
            if (!$one || !$two) {
                throw self::invalid('Kies twee spelers uit de clublijst.');
            }
            if ($one['id'] === $two['id']) {
                throw self::invalid('Kies twee verschillende spelers.');
            }
            $player1 = $one['name'];
            $player2 = $two['name'];
        } elseif ($player1 === '' || $player2 === '') {
            throw self::invalid('Vul beide spelers in.');
        }
        if (strtolower($player1) === strtolower($player2)) {
            throw self::invalid('Kies twee verschillende spelers.');
        }
        $paper = Snookerclub_Excel::parse_frame_result($input);
        $raw_frames = is_array($input['frames'] ?? null) ? $input['frames'] : [];
        $has_scored = false;
        foreach ($raw_frames as $frame) {
            if (((int) ($frame['p1'] ?? 0)) + ((int) ($frame['p2'] ?? 0)) > 0) {
                $has_scored = true;
                break;
            }
        }
        if ($has_scored) {
            $frames = Snookerclub_Excel::pad_frames(self::normalize_frames($raw_frames, max(5, count($raw_frames))), 5);
        } elseif ($paper) {
            $frames = Snookerclub_Excel::pad_frames(
                Snookerclub_Excel::synthesize_frames($paper['framesFor'], $paper['framesAgainst']),
                5
            );
        } else {
            $frames = self::normalize_frames($input['frames'] ?? [], 5);
        }
        $averages = self::match_averages($frames);
        $points_known = Snookerclub_Excel::has_real_points($frames);
        $break1 = self::score($input['break1'] ?? 0, self::MAX_BREAK);
        $break2 = self::score($input['break2'] ?? 0, self::MAX_BREAK);
        if ($break1 === null || $break2 === null) {
            throw self::invalid('Break is ongeldig (0–155). 147 is het maximum zonder free ball.');
        }
        $type = strtolower((string) ($input['matchType'] ?? $existing['matchType'] ?? 'competitie'));
        $source_raw = strtolower((string) ($input['source'] ?? $existing['source'] ?? ($paper && !$has_scored ? 'paper' : 'guest')));
        $source = in_array($source_raw, ['paper', 'admin'], true) ? $source_raw : 'guest';
        $now = gmdate('Y-m-d\TH:i:s.000\Z');
        return [
            'id' => $existing['id'] ?? self::uuid(),
            'tournament' => $tournament,
            'date' => $date,
            'player1' => $player1,
            'player2' => $player2,
            'handicap1' => $points_known ? $averages['handicap1'] : 0,
            'handicap2' => $points_known ? $averages['handicap2'] : 0,
            'framesPlayed' => $averages['framesPlayed'],
            'frames' => $frames,
            'break1' => $break1,
            'break2' => $break2,
            'matchType' => in_array($type, self::MATCH_TYPES, true) ? $type : 'competitie',
            'table' => substr(self::clean_name($input['table'] ?? $existing['table'] ?? ''), 0, 40),
            'referee' => substr(self::clean_name($input['referee'] ?? $existing['referee'] ?? ''), 0, 80),
            'note' => substr(trim((string) ($input['note'] ?? $existing['note'] ?? '')), 0, 200),
            'bestOf' => self::score($input['bestOf'] ?? $existing['bestOf'] ?? min(5, count($frames)), 17) ?: 5,
            'season' => Snookerclub_Excel::normalize_season($input['season'] ?? $existing['season'] ?? '', $date),
            'round' => Snookerclub_Excel::normalize_round($input['round'] ?? $existing['round'] ?? ''),
            'source' => $source,
            'signature1' => self::normalize_signature($input['signature1'] ?? $input['signature'] ?? '') ?: ($existing['signature1'] ?? $existing['signature'] ?? ''),
            'signature2' => self::normalize_signature($input['signature2'] ?? '') ?: ($existing['signature2'] ?? ''),
            'createdAt' => $existing['createdAt'] ?? $now,
            'updatedAt' => $now,
        ];
    }

    public static function normalize_player(array $input, array $opts = []): array {
        $existing = $opts['existing'] ?? null;
        $roster = $opts['roster'] ?? [];
        $name = self::clean_name($input['name'] ?? '');
        if ($name === '') {
            throw self::invalid('Vul een spelersnaam in.');
        }
        foreach ($roster as $row) {
            if (($row['id'] ?? '') !== ($existing['id'] ?? null) && strtolower($row['name'] ?? '') === strtolower($name)) {
                throw self::invalid('Die speler staat al op de lijst.');
            }
        }
        $now = gmdate('Y-m-d\TH:i:s.000\Z');
        return [
            'id' => $existing['id'] ?? self::uuid(),
            'name' => $name,
            'createdAt' => $existing['createdAt'] ?? $now,
            'updatedAt' => $now,
        ];
    }

    public static function normalize_time($value): string {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $raw, $m)) {
            throw self::invalid('Gebruik een tijd als 19:30.');
        }
        $hour = (int) $m[1];
        $minute = (int) $m[2];
        if ($hour > 23 || $minute > 59) {
            throw self::invalid('Gebruik een tijd als 19:30.');
        }
        return sprintf('%02d:%02d', $hour, $minute);
    }

    public static function normalize_event(array $input, array $opts = []): array {
        $existing = $opts['existing'] ?? null;
        $title = substr(trim(preg_replace('/\s+/', ' ', (string) ($input['title'] ?? '')) ?? ''), 0, 80);
        if ($title === '') {
            throw self::invalid('Vul een titel in.');
        }
        $date = trim((string) ($input['date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw self::invalid('Kies een geldige datum.');
        }
        $kind = in_array($input['kind'] ?? '', self::EVENT_KINDS, true) ? $input['kind'] : 'clubavond';
        $start = self::normalize_time($input['start'] ?? '');
        $end = self::normalize_time($input['end'] ?? '');
        if ($start && $end && $end < $start) {
            throw self::invalid('Eindtijd valt voor de start.');
        }
        $now = gmdate('Y-m-d\TH:i:s.000\Z');
        return [
            'id' => $existing['id'] ?? self::uuid(),
            'title' => $title,
            'date' => $date,
            'start' => $start,
            'end' => $end,
            'kind' => $kind,
            'kindLabel' => self::EVENT_KIND_LABELS[$kind],
            'place' => substr(trim((string) ($input['place'] ?? '')), 0, 80),
            'note' => substr(trim((string) ($input['note'] ?? '')), 0, 200),
            'createdAt' => $existing['createdAt'] ?? $now,
            'updatedAt' => $now,
        ];
    }

    public static function sort_events(array $events): array {
        usort($events, function ($a, $b) {
            $d = strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? ''));
            if ($d !== 0) {
                return $d;
            }
            $t = strcmp((string) ($a['start'] ?? '99:99'), (string) ($b['start'] ?? '99:99'));
            if ($t !== 0) {
                return $t;
            }
            return self::nl_cmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        });
        return $events;
    }

    public static function progress_meter($value, $goal): array {
        $current = (int) $value;
        $target = (int) $goal;
        $pct = $target > 0 ? min(100, (int) round(($current / $target) * 100)) : 0;
        return ['value' => $current, 'goal' => $target, 'pct' => $pct];
    }

    public static function format_next_event(?array $event, string $fallback = ''): string {
        if (!$event) {
            return $fallback;
        }
        return implode(' · ', array_filter([$event['title'] ?? '', $event['date'] ?? '', $event['start'] ?? '']));
    }

    public static function resolve_agenda_month($year, $month, string $today = ''): array {
        $today = $today !== '' ? $today : self::club_today();
        $y = intval($year);
        $m = intval($month);
        if ($y >= 2000 && $y <= 2100 && $m >= 1 && $m <= 12) {
            return ['year' => $y, 'month' => $m];
        }
        return ['year' => (int) substr($today, 0, 4), 'month' => (int) substr($today, 5, 7)];
    }

    public static function shift_month(int $year, int $month, int $delta): array {
        $date = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $date = $date->modify(($delta >= 0 ? '+' : '') . $delta . ' months');
        return ['year' => (int) $date->format('Y'), 'month' => (int) $date->format('n')];
    }

    public static function month_key($date): string {
        return substr((string) $date, 0, 7);
    }

    public static function build_month_agenda(array $events, $year = null, $month = null, string $today = ''): array {
        $today = $today !== '' ? $today : self::club_today();
        $resolved = self::resolve_agenda_month($year, $month, $today);
        $y = $resolved['year'];
        $m = $resolved['month'];
        $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m));
        $start_weekday = ((int) $first->format('w') + 6) % 7;
        $days_in_month = (int) $first->format('t');
        $prefix = sprintf('%04d-%02d', $y, $m);
        $in_month = self::sort_events(array_values(array_filter($events, fn($e) => self::month_key($e['date'] ?? '') === $prefix)));
        $by_date = [];
        foreach ($in_month as $event) {
            $by_date[$event['date']][] = $event;
        }
        $days = [];
        for ($i = 0; $i < $start_weekday; $i++) {
            $days[] = ['date' => '', 'day' => 0, 'empty' => true, 'today' => false, 'events' => []];
        }
        for ($day = 1; $day <= $days_in_month; $day++) {
            $date = sprintf('%s-%02d', $prefix, $day);
            $days[] = [
                'date' => $date,
                'day' => $day,
                'empty' => false,
                'today' => $date === $today,
                'events' => $by_date[$date] ?? [],
            ];
        }
        $label = $first->setTimezone(new DateTimeZone(self::CLUB_TZ))->format('F Y');
        $nl = [
            'January' => 'januari', 'February' => 'februari', 'March' => 'maart', 'April' => 'april',
            'May' => 'mei', 'June' => 'juni', 'July' => 'juli', 'August' => 'augustus',
            'September' => 'september', 'October' => 'oktober', 'November' => 'november', 'December' => 'december',
        ];
        $en = $first->format('F');
        $label = ($nl[$en] ?? strtolower($en)) . ' ' . $y;
        $upcoming = self::sort_events(array_values(array_filter($events, fn($e) => (string) ($e['date'] ?? '') >= $today)));
        return [
            'year' => $y,
            'month' => $m,
            'label' => $label,
            'weekdays' => ['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'],
            'days' => $days,
            'events' => $in_month,
            'upcoming' => array_slice($upcoming, 0, 10),
            'prev' => self::shift_month($y, $m, -1),
            'next' => self::shift_month($y, $m, 1),
        ];
    }

    public static function blank_player(string $name): array {
        return [
            'name' => $name,
            'played' => 0,
            'wins' => 0,
            'losses' => 0,
            'draws' => 0,
            'points' => 0,
            'framesFor' => 0,
            'framesAgainst' => 0,
            'pointsFor' => 0,
            'pointsAgainst' => 0,
            'playedPoints' => 0,
            'playedFrameCount' => 0,
            'highestBreak' => 0,
            'centuries' => 0,
            'breakSum' => 0,
            'breakCount' => 0,
            'lastPlayed' => '',
        ];
    }

    public static function rank_players(array $matches, array $roster = []): array {
        $map = [];
        $touch = function (string $name) use (&$map) {
            $key = self::clean_name($name);
            if ($key === '') {
                return null;
            }
            if (!isset($map[$key])) {
                $map[$key] = self::blank_player($key);
            }
            return $key;
        };
        foreach ($roster as $row) {
            $touch($row['name'] ?? '');
        }
        foreach ($matches as $match) {
            $decorated = self::decorate_match($match);
            $one_key = $touch($decorated['player1'] ?? '');
            $two_key = $touch($decorated['player2'] ?? '');
            if (!$one_key || !$two_key) {
                continue;
            }
            foreach ([
                [$one_key, Snookerclub_Excel::match_row_for_player($decorated, $decorated['player1'] ?? '')],
                [$two_key, Snookerclub_Excel::match_row_for_player($decorated, $decorated['player2'] ?? '')],
            ] as [$key, $row]) {
                if (!$row) {
                    continue;
                }
                $map[$key]['played']++;
                $map[$key]['framesFor'] += (int) ($row['framesFor'] ?? 0);
                $map[$key]['framesAgainst'] += (int) ($row['framesAgainst'] ?? 0);
                $map[$key]['pointsFor'] += (int) ($row['pointsFor'] ?? 0);
                $map[$key]['pointsAgainst'] += (int) ($row['pointsAgainst'] ?? 0);
                $map[$key]['playedPoints'] += (int) ($row['pointsFor'] ?? 0);
                $map[$key]['playedFrameCount'] += (int) ($row['realFrames'] ?? 0);
                $map[$key]['highestBreak'] = max($map[$key]['highestBreak'], (int) ($row['breaks'] ?? 0));
                if ((int) ($row['breaks'] ?? 0) > 0) {
                    $map[$key]['breakSum'] += (int) $row['breaks'];
                    $map[$key]['breakCount']++;
                }
                if ((int) ($row['breaks'] ?? 0) >= 100) {
                    $map[$key]['centuries']++;
                }
                if (($row['w'] ?? 0) === 1) {
                    $map[$key]['wins']++;
                    $map[$key]['points'] += 2;
                } elseif (($row['l'] ?? 0) === 1) {
                    $map[$key]['losses']++;
                } else {
                    $map[$key]['draws']++;
                    $map[$key]['points']++;
                }
                if ((string) ($row['date'] ?? '') >= $map[$key]['lastPlayed']) {
                    $map[$key]['lastPlayed'] = $row['date'];
                }
            }
        }
        $players = [];
        foreach ($map as $player) {
            $played_points = $player['playedPoints'];
            $played_frames = $player['playedFrameCount'];
            unset($player['playedPoints'], $player['playedFrameCount']);
            $player['handicap'] = $played_frames ? round(($played_points / $played_frames) * 10) / 10 : 0;
            $player['framesPlayed'] = $played_frames;
            $player['frameDiff'] = $player['framesFor'] - $player['framesAgainst'];
            $player['winRate'] = $player['played'] ? (int) round(($player['wins'] / $player['played']) * 100) : 0;
            $players[] = Snookerclub_Excel::decorate_ranked_player($player);
        }
        usort($players, function ($a, $b) {
            return $b['points'] <=> $a['points']
                ?: $b['wins'] <=> $a['wins']
                ?: $b['frameDiff'] <=> $a['frameDiff']
                ?: $b['highestBreak'] <=> $a['highestBreak']
                ?: $b['played'] <=> $a['played']
                ?: self::nl_cmp($a['name'], $b['name']);
        });
        $trophies = ['gold', 'silver', 'bronze'];
        foreach ($players as $index => &$player) {
            $player['rank'] = $index + 1;
            $player['trophy'] = $index < 3 && ($player['played'] > 0 || $player['points'] > 0)
                ? $trophies[$index]
                : '';
        }
        return $players;
    }

    public static function normalize_brand($input = []): array {
        $src = is_array($input) ? $input : [];
        $frames = intval($src['framesCount'] ?? 5);
        if (isset($src['tournaments']) && is_array($src['tournaments'])) {
            $tournaments = array_values(array_filter(array_map(fn($n) => substr(trim((string) $n), 0, 80), $src['tournaments'])));
        } else {
            $parts = preg_split('/\n|,/', (string) ($src['tournaments'] ?? ''));
            $tournaments = array_values(array_filter(array_map(fn($n) => substr(trim((string) $n), 0, 80), $parts ?: [])));
        }
        $goal = function ($value, $fallback, $max) {
            $n = intval($value);
            if ($n < 1) {
                return $fallback;
            }
            return min($max, $n);
        };
        $safe_url = function ($value, $fallback = '') {
            $raw = substr(trim((string) $value), 0, 400);
            if ($raw === '') {
                return $fallback;
            }
            if (str_starts_with($raw, 'data:')) {
                return '';
            }
            if (preg_match('/[\r\n<>]/', $raw)) {
                return $fallback;
            }
            return $raw;
        };
        $def = self::DEFAULT_BRAND;
        $theme = class_exists('Snookerclub_Theme')
            ? Snookerclub_Theme::normalize($src)
            : [];
        $accent = (string) ($src['accent'] ?? ($theme['accent'] ?? ''));
        $soft = (string) ($src['accentSoft'] ?? ($theme['accentSoft'] ?? ''));
        return [
            'clubName' => substr(trim((string) ($src['clubName'] ?? $def['clubName'])), 0, 80) ?: $def['clubName'],
            'tagline' => substr(trim((string) ($src['tagline'] ?? $def['tagline'])), 0, 160),
            'accent' => $theme['accent'] ?? (preg_match('/^#[0-9a-fA-F]{6}$/', $accent) ? $accent : $def['accent']),
            'accentSoft' => $theme['accentSoft'] ?? (preg_match('/^#[0-9a-fA-F]{6}$/', $soft) ? $soft : $def['accentSoft']),
            'themePreset' => $theme['themePreset'] ?? 'baize',
            'bg' => $theme['bg'] ?? $def['bg'],
            'bgSide' => $theme['bgSide'] ?? $def['bgSide'],
            'surface' => $theme['surface'] ?? $def['surface'],
            'surface2' => $theme['surface2'] ?? $def['surface2'],
            'ink' => $theme['ink'] ?? $def['ink'],
            'muted' => $theme['muted'] ?? $def['muted'],
            'topbarInk' => $theme['topbarInk'] ?? $def['topbarInk'],
            'font' => $theme['font'] ?? 'club',
            'radius' => $theme['radius'] ?? 'club',
            'density' => $theme['density'] ?? 'comfortable',
            'inheritWp' => !empty($theme['inheritWp']),
            'logoUrl' => $safe_url($src['logoUrl'] ?? ''),
            'heroUrl' => $safe_url($src['heroUrl'] ?? '', $def['heroUrl']) ?: $def['heroUrl'],
            'showSignatures' => ($src['showSignatures'] ?? true) !== false && ($src['showSignatures'] ?? true) !== '0',
            'framesCount' => $frames >= 1 && $frames <= 17 ? $frames : 5,
            'tournaments' => array_slice($tournaments, 0, 40),
            'notice' => substr(trim((string) ($src['notice'] ?? $def['notice'])), 0, 240),
            'venue' => substr(trim((string) ($src['venue'] ?? $def['venue'])), 0, 120),
            'openingHours' => substr(trim((string) ($src['openingHours'] ?? $def['openingHours'])), 0, 120),
            'nextEvent' => substr(trim((string) ($src['nextEvent'] ?? $def['nextEvent'])), 0, 160),
            'goalMatchesMonth' => $goal($src['goalMatchesMonth'] ?? $def['goalMatchesMonth'], $def['goalMatchesMonth'], 200),
            'goalMatchesWeek' => $goal($src['goalMatchesWeek'] ?? $def['goalMatchesWeek'], $def['goalMatchesWeek'], 50),
            'goalFramesMonth' => $goal($src['goalFramesMonth'] ?? $def['goalFramesMonth'], $def['goalFramesMonth'], 500),
            'goalCenturies' => $goal($src['goalCenturies'] ?? $def['goalCenturies'], $def['goalCenturies'], 50),
            'goalNewMembersMonth' => $goal($src['goalNewMembersMonth'] ?? $def['goalNewMembersMonth'], $def['goalNewMembersMonth'], 80),
            'goalClubNightsMonth' => $goal($src['goalClubNightsMonth'] ?? $def['goalClubNightsMonth'], $def['goalClubNightsMonth'], 40),
        ];
    }

    public static function resolve_hero_url(array $brand, string $base = '/snooker'): string {
        $raw = trim((string) ($brand['heroUrl'] ?? ''));
        if ($raw === '' || $raw === 'hero.jpg') {
            return rtrim($base, '/') . '/hero.jpg';
        }
        if (preg_match('#^https?://#i', $raw) || str_starts_with($raw, '/')) {
            return $raw;
        }
        return rtrim($base, '/') . '/' . ltrim($raw, '/');
    }

    public static function club_kpis(array $matches, array $roster = [], array $players = [], array $brand = [], array $events = [], string $today = ''): array {
        $today = $today !== '' ? $today : self::club_today();
        $decorated = array_map([self::class, 'decorate_match'], $matches);
        $week_start = self::iso_week_start($today);
        $month = self::month_key($today);
        [$year, $mon] = array_map('intval', explode('-', $month));
        $shifted = self::shift_month($year, $mon, -1);
        $last_month = sprintf('%04d-%02d', $shifted['year'], $shifted['month']);
        $this_month = array_values(array_filter($decorated, fn($m) => self::month_key($m['date'] ?? '') === $month));
        $frames_played = array_reduce($decorated, fn($s, $m) => $s + ($m['framesPlayed'] ?? 0), 0);
        $frames_month = array_reduce($this_month, fn($s, $m) => $s + ($m['framesPlayed'] ?? 0), 0);
        $close_matches = count(array_filter($decorated, fn($m) => !empty($m['close'])));
        $centuries = array_reduce($decorated, fn($s, $m) => $s + ($m['centuries'] ?? 0), 0);
        $highest_break = array_reduce($decorated, fn($max, $m) => max($max, $m['highestBreak']['value'] ?? 0), 0);
        $active_players = count(array_filter($players, fn($p) => ($p['played'] ?? 0) > 0));
        $roster_count = count($roster);
        $handicaps = array_map(fn($p) => $p['handicap'], array_values(array_filter($players, fn($p) => ($p['framesPlayed'] ?? 0) > 0)));
        $avg_handicap = $handicaps ? round(array_sum($handicaps) / count($handicaps) * 10) / 10 : 0;
        $def = self::DEFAULT_BRAND;
        $goals = [
            'matchesMonth' => $brand['goalMatchesMonth'] ?? $def['goalMatchesMonth'],
            'matchesWeek' => $brand['goalMatchesWeek'] ?? $def['goalMatchesWeek'],
            'framesMonth' => $brand['goalFramesMonth'] ?? $def['goalFramesMonth'],
            'centuries' => $brand['goalCenturies'] ?? $def['goalCenturies'],
            'newMembersMonth' => $brand['goalNewMembersMonth'] ?? $def['goalNewMembersMonth'],
            'clubNightsMonth' => $brand['goalClubNightsMonth'] ?? $def['goalClubNightsMonth'],
        ];
        $matches_week = count(array_filter($decorated, fn($m) => (string) ($m['date'] ?? '') >= $week_start && (string) ($m['date'] ?? '') <= $today));
        $matches_month = count($this_month);
        $new_members = count(array_filter($roster, fn($r) => self::month_key($r['createdAt'] ?? '') === $month));
        $nights = array_values(array_filter($events, fn($e) => ($e['kind'] ?? '') === 'clubavond'));
        $club_nights_month = count(array_filter($nights, fn($e) => self::month_key($e['date'] ?? '') === $month));
        $club_nights_upcoming = count(array_filter($nights, fn($e) => (string) ($e['date'] ?? '') >= $today));
        $upcoming = self::sort_events(array_values(array_filter($events, fn($e) => (string) ($e['date'] ?? '') >= $today)));
        $next = $upcoming[0] ?? null;
        return [
            'matchesToday' => count(array_filter($decorated, fn($m) => ($m['date'] ?? '') === $today)),
            'matchesWeek' => $matches_week,
            'matchesMonth' => $matches_month,
            'matchesLastMonth' => count(array_filter($decorated, fn($m) => self::month_key($m['date'] ?? '') === $last_month)),
            'framesPlayed' => $frames_played,
            'framesMonth' => $frames_month,
            'closeMatches' => $close_matches,
            'rosterCount' => $roster_count,
            'activePlayers' => $active_players,
            'avgHandicap' => $avg_handicap,
            'newMembersMonth' => $new_members,
            'clubNightsMonth' => $club_nights_month,
            'clubNightsUpcoming' => $club_nights_upcoming,
            'nextEvent' => $next,
            'nextEventLabel' => self::format_next_event($next, $brand['nextEvent'] ?? ''),
            'goals' => $goals,
            'progress' => [
                'week' => self::progress_meter($matches_week, $goals['matchesWeek']),
                'month' => self::progress_meter($matches_month, $goals['matchesMonth']),
                'frames' => self::progress_meter($frames_month, $goals['framesMonth']),
                'active' => self::progress_meter($active_players, $roster_count ?: 1),
                'break' => self::progress_meter($highest_break, 147),
                'centuries' => self::progress_meter($centuries, $goals['centuries']),
                'members' => self::progress_meter($new_members, $goals['newMembersMonth']),
                'nights' => self::progress_meter($club_nights_month, $goals['clubNightsMonth']),
            ],
        ];
    }

    public static function summarize_matches(array $matches, array $roster = [], array $brand = [], array $events = [], string $today = ''): array {
        $today = $today !== '' ? $today : self::club_today();
        $highest = ['value' => 0, 'player' => '', 'date' => '', 'tournament' => '', 'matchId' => ''];
        $centuries = 0;
        $closest = null;
        foreach ($matches as $match) {
            $decorated = self::decorate_match($match);
            $centuries += $decorated['centuries'];
            $current = $decorated['highestBreak'];
            if (($current['value'] ?? 0) > $highest['value']) {
                $highest = [
                    'value' => $current['value'],
                    'player' => $current['player'],
                    'date' => $match['date'] ?? '',
                    'tournament' => $match['tournament'] ?? '',
                    'matchId' => $match['id'] ?? '',
                ];
            }
            if (!empty($decorated['close'])) {
                $score = $decorated['wins']['p1'] + $decorated['wins']['p2'];
                $prev = $closest ? $closest['wins']['p1'] + $closest['wins']['p2'] : -1;
                if (!$closest || $score > $prev) {
                    $closest = $decorated;
                }
            }
        }
        $players = self::rank_players($matches, $roster);
        $recent = $matches;
        usort($recent, function ($a, $b) {
            $d = strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
            return $d !== 0 ? $d : strcmp((string) ($b['createdAt'] ?? ''), (string) ($a['createdAt'] ?? ''));
        });
        $recent = array_map([self::class, 'decorate_match'], array_slice($recent, 0, 8));
        $tournaments = array_values(array_unique(array_filter(array_map(fn($m) => $m['tournament'] ?? '', $matches))));
        $kpis = self::club_kpis($matches, $roster, $players, $brand, $events, $today);
        return [
            'matchCount' => count($matches),
            'tournamentCount' => count($tournaments),
            'playerCount' => count($players),
            'centuries' => $centuries,
            'highestBreak' => $highest,
            'closest' => $closest,
            'players' => $players,
            'top3' => array_slice($players, 0, 3),
            'recent' => $recent,
            'tournaments' => $tournaments,
            'roster' => self::public_roster($roster),
            'kpis' => $kpis,
        ];
    }

    public static function public_roster(array $roster): array {
        return array_map(fn($row) => ['id' => $row['id'], 'name' => $row['name']], $roster);
    }

    public static function normalize_roster($raw, array $matches = []): array {
        if (is_array($raw)) {
            $seen = [];
            $out = [];
            $index = 0;
            foreach ($raw as $row) {
                $index++;
                $name = self::clean_name($row['name'] ?? '');
                if ($name === '') {
                    continue;
                }
                $key = strtolower($name);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = [
                    'id' => (string) ($row['id'] ?? ('p-' . $index)),
                    'name' => $name,
                    'createdAt' => $row['createdAt'] ?? gmdate('c'),
                    'updatedAt' => $row['updatedAt'] ?? $row['createdAt'] ?? gmdate('c'),
                ];
            }
            usort($out, fn($a, $b) => self::nl_cmp($a['name'], $b['name']));
            return $out;
        }
        $names = [];
        foreach ($matches as $match) {
            foreach (['player1', 'player2'] as $key) {
                $name = self::clean_name($match[$key] ?? '');
                if ($name !== '') {
                    $names[$name] = true;
                }
            }
        }
        $list = array_keys($names);
        usort($list, [self::class, 'nl_cmp']);
        $now = gmdate('c');
        $out = [];
        foreach ($list as $i => $name) {
            $out[] = ['id' => 'p-' . ($i + 1), 'name' => $name, 'createdAt' => $now, 'updatedAt' => $now];
        }
        return $out;
    }

    public static function empty_state(): array {
        return [
            'matches' => [],
            'roster' => [],
            'events' => [],
            'brand' => self::DEFAULT_BRAND,
        ];
    }

    private function read_state(): array {
        if ($this->cache !== null) {
            return $this->cache;
        }
        if ($this->use_wp) {
            $parsed = get_option('snookerclub_state', null);
            if (!is_array($parsed)) {
                $this->cache = self::empty_state();
                return $this->cache;
            }
        } else {
            if (!is_file($this->file)) {
                $this->cache = self::empty_state();
                return $this->cache;
            }
            $parsed = json_decode((string) file_get_contents($this->file), true);
            if (!is_array($parsed)) {
                $this->cache = self::empty_state();
                return $this->cache;
            }
        }
        $events = [];
        foreach (($parsed['events'] ?? []) as $row) {
            try {
                $events[] = self::normalize_event($row, ['existing' => $row]);
            } catch (Throwable $e) {
                // skip broken rows
            }
        }
        $this->cache = [
            'matches' => is_array($parsed['matches'] ?? null) ? $parsed['matches'] : [],
            'roster' => self::normalize_roster($parsed['roster'] ?? null, $parsed['matches'] ?? []),
            'events' => self::sort_events($events),
            'brand' => self::normalize_brand($parsed['brand'] ?? []),
        ];
        return $this->cache;
    }

    private function write_state(array $state): void {
        $this->cache = $state;
        if ($this->use_wp) {
            update_option('snookerclub_state', $state, false);
            return;
        }
        if (!is_dir($this->data_dir)) {
            mkdir($this->data_dir, 0775, true);
        }
        $tmp = $this->file . '.' . getmypid() . '.tmp';
        file_put_contents($tmp, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        rename($tmp, $this->file);
    }

    public function list_matches(): array {
        $state = $this->read_state();
        $matches = array_map([self::class, 'decorate_match'], $state['matches']);
        usort($matches, function ($a, $b) {
            $d = strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
            return $d !== 0 ? $d : strcmp((string) ($b['createdAt'] ?? ''), (string) ($a['createdAt'] ?? ''));
        });
        return $matches;
    }

    public function get_match(string $id): ?array {
        foreach ($this->read_state()['matches'] as $row) {
            if (($row['id'] ?? '') === $id) {
                return self::decorate_match($row);
            }
        }
        return null;
    }

    public function create_match(array $input, array $opts = []): array {
        $state = $this->read_state();
        $match = self::normalize_match($input, ['roster' => $state['roster']]);
        $require = $opts['require_signatures'] ?? true;
        if ($require && (!$match['signature1'] || !$match['signature2'])) {
            throw self::invalid('Beide spelers moeten een handtekening zetten.');
        }
        $state['matches'][] = $match;
        $this->write_state($state);
        return self::decorate_match($match);
    }

    public function create_paper_match(array $input): array {
        return $this->create_match(array_merge(Snookerclub_Excel::paper_input_to_match($input), ['source' => 'paper']), [
            'require_signatures' => false,
        ]);
    }

    public function import_paper(array $input = []): array {
        $state = $this->read_state();
        $fallback = self::clean_name($input['player'] ?? $input['player1'] ?? '');
        $rows = !empty($input['rows']) && is_array($input['rows'])
            ? $input['rows']
            : Snookerclub_Excel::parse_paper_csv((string) ($input['csv'] ?? ''), $fallback);
        if (!$rows) {
            throw self::invalid('Geen papierregels gevonden.');
        }
        $created = [];
        foreach ($rows as $row) {
            $draft = Snookerclub_Excel::paper_input_to_match(array_merge($row, [
                'player' => $row['player'] ?? $fallback,
            ]));
            foreach ([$draft['player1'], $draft['player2']] as $name) {
                if ($name === '') {
                    throw self::invalid('Elke regel heeft een speler en een tegenstander nodig.');
                }
                if (!self::resolve_roster_player($state['roster'], $name)) {
                    $player = self::normalize_player(['name' => $name], ['roster' => $state['roster']]);
                    $state['roster'][] = $player;
                    usort($state['roster'], fn($a, $b) => self::nl_cmp($a['name'], $b['name']));
                }
            }
            $match = self::normalize_match(array_merge($draft, ['source' => 'paper']), ['roster' => $state['roster']]);
            $state['matches'][] = $match;
            $created[] = self::decorate_match($match);
        }
        $this->write_state($state);
        return [
            'matches' => $created,
            'count' => count($created),
            'roster' => self::public_roster($state['roster']),
        ];
    }

    public function player_dossier(string $name, string $season = ''): array {
        $state = $this->read_state();
        $resolved = Snookerclub_Excel::resolve_player_name($name, $state['matches'], $state['roster']);
        $matches = Snookerclub_Excel::matches_in_season($state['matches'], $season);
        $dossier = Snookerclub_Excel::player_dossier($matches, $resolved, $state['roster']);
        $dossier['season'] = Snookerclub_Excel::normalize_season($season);
        $dossier['seasons'] = Snookerclub_Excel::seasons($state['matches']);
        return $dossier;
    }

    public function report(string $season = ''): array {
        $state = $this->read_state();
        $want = Snookerclub_Excel::normalize_season($season);
        $matches = Snookerclub_Excel::matches_in_season($state['matches'], $want);
        $players = self::rank_players($matches, $state['roster']);
        $brand = self::normalize_brand($state['brand']);
        return [
            'brand' => $brand,
            'season' => $want,
            'seasons' => Snookerclub_Excel::seasons($state['matches']),
            'players' => $players,
            'top3' => array_slice($players, 0, 3),
            'matchCount' => count($matches),
            'printedAt' => gmdate('Y-m-d'),
        ];
    }

    public function head_to_head(string $player_a, string $player_b): array {
        $state = $this->read_state();
        return Snookerclub_Excel::head_to_head($state['matches'], $player_a, $player_b);
    }

    public function update_match(string $id, array $input): ?array {
        $state = $this->read_state();
        foreach ($state['matches'] as $i => $row) {
            if (($row['id'] ?? '') === $id) {
                $match = self::normalize_match($input, ['existing' => $row, 'roster' => $state['roster']]);
                $state['matches'][$i] = $match;
                $this->write_state($state);
                return self::decorate_match($match);
            }
        }
        return null;
    }

    public function delete_match(string $id): bool {
        $state = $this->read_state();
        $before = count($state['matches']);
        $state['matches'] = array_values(array_filter($state['matches'], fn($m) => ($m['id'] ?? '') !== $id));
        if (count($state['matches']) === $before) {
            return false;
        }
        $this->write_state($state);
        return true;
    }

    public function list_players(): array {
        return self::public_roster($this->read_state()['roster']);
    }

    public function create_player(array $input): array {
        $state = $this->read_state();
        $player = self::normalize_player($input, ['roster' => $state['roster']]);
        $state['roster'][] = $player;
        usort($state['roster'], fn($a, $b) => self::nl_cmp($a['name'], $b['name']));
        $this->write_state($state);
        return $player;
    }

    public function update_player(string $id, array $input): ?array {
        $state = $this->read_state();
        foreach ($state['roster'] as $i => $row) {
            if (($row['id'] ?? '') !== $id) {
                continue;
            }
            $player = self::normalize_player($input, ['existing' => $row, 'roster' => $state['roster']]);
            $state['roster'][$i] = $player;
            usort($state['roster'], fn($a, $b) => self::nl_cmp($a['name'], $b['name']));
            if ($row['name'] !== $player['name']) {
                foreach ($state['matches'] as &$match) {
                    if ($match['player1'] === $row['name']) {
                        $match['player1'] = $player['name'];
                    }
                    if ($match['player2'] === $row['name']) {
                        $match['player2'] = $player['name'];
                    }
                }
            }
            $this->write_state($state);
            return $player;
        }
        return null;
    }

    public function delete_player(string $id): bool {
        $state = $this->read_state();
        $player = null;
        foreach ($state['roster'] as $row) {
            if (($row['id'] ?? '') === $id) {
                $player = $row;
                break;
            }
        }
        if (!$player) {
            return false;
        }
        foreach ($state['matches'] as $match) {
            if (($match['player1'] ?? '') === $player['name'] || ($match['player2'] ?? '') === $player['name']) {
                throw self::invalid('Deze speler heeft wedstrijden. Verwijder die eerst of hernoem de speler.');
            }
        }
        $state['roster'] = array_values(array_filter($state['roster'], fn($row) => ($row['id'] ?? '') !== $id));
        $this->write_state($state);
        return true;
    }

    public function list_events(): array {
        return self::sort_events($this->read_state()['events']);
    }

    public function create_event(array $input): array {
        $state = $this->read_state();
        $event = self::normalize_event($input);
        $state['events'] = self::sort_events(array_merge($state['events'], [$event]));
        $this->write_state($state);
        return $event;
    }

    public function update_event(string $id, array $input): ?array {
        $state = $this->read_state();
        foreach ($state['events'] as $i => $row) {
            if (($row['id'] ?? '') === $id) {
                $event = self::normalize_event($input, ['existing' => $row]);
                $state['events'][$i] = $event;
                $state['events'] = self::sort_events($state['events']);
                $this->write_state($state);
                return $event;
            }
        }
        return null;
    }

    public function delete_event(string $id): bool {
        $state = $this->read_state();
        $before = count($state['events']);
        $state['events'] = array_values(array_filter($state['events'], fn($e) => ($e['id'] ?? '') !== $id));
        if (count($state['events']) === $before) {
            return false;
        }
        $this->write_state($state);
        return true;
    }

    public function agenda($year = null, $month = null): array {
        return self::build_month_agenda($this->read_state()['events'], $year, $month);
    }

    public function get_brand(): array {
        return self::normalize_brand($this->read_state()['brand']);
    }

    public function save_brand(array $input): array {
        $state = $this->read_state();
        $state['brand'] = self::normalize_brand(array_merge($state['brand'], $input));
        $this->write_state($state);
        return $state['brand'];
    }

    public function save_hero(string $data_url, string $public_path): array {
        if (!preg_match('#^data:image/(png|jpe?g);base64,([a-z0-9+/=\s]+)$#i', $data_url, $m)) {
            throw self::invalid('Stuur een JPG of PNG.');
        }
        $buf = base64_decode(preg_replace('/\s+/', '', $m[2]), true);
        if ($buf === false || strlen($buf) < 80 || strlen($buf) > 750000) {
            throw self::invalid('Achtergrond moet tussen 80 bytes en 750 kB zijn.');
        }
        if ($this->use_wp && function_exists('update_option')) {
            update_option('snookerclub_hero', $buf, false);
        } else {
            if (!is_dir($this->data_dir)) {
                mkdir($this->data_dir, 0775, true);
            }
            file_put_contents($this->hero_file, $buf);
        }
        return $this->save_brand(['heroUrl' => $public_path]);
    }

    public function read_hero(): ?string {
        if ($this->use_wp && function_exists('get_option')) {
            $buf = get_option('snookerclub_hero', '');
            return is_string($buf) && $buf !== '' ? $buf : null;
        }
        return is_file($this->hero_file) ? (string) file_get_contents($this->hero_file) : null;
    }

    public function overview(string $base_path = '/snooker'): array {
        $state = $this->read_state();
        $brand = self::normalize_brand($state['brand']);
        $today = self::club_today();
        $summary = self::summarize_matches($state['matches'], $state['roster'], $brand, $state['events'], $today);
        return array_merge($summary, [
            'brand' => array_merge($brand, ['heroUrl' => self::resolve_hero_url($brand, $base_path)]),
            'agenda' => self::build_month_agenda($state['events'], null, null, $today),
            'nextEvent' => $summary['kpis']['nextEvent'],
            'nextEventLabel' => $summary['kpis']['nextEventLabel'],
        ]);
    }
}
