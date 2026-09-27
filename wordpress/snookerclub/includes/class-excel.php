<?php
/**
 * Paper → Excel stats — same rules as webhost/snooker/lib/excel.js.
 */

if (!defined('SNOOKERCLUB_EXCEL')) {
    define('SNOOKERCLUB_EXCEL', true);
}

class Snookerclub_Excel {
    public const ROUND_NAMES = ['8ste finale', 'kwart finale', 'halve finale', 'finale'];

    public static function clean($value, int $max = 80): string {
        $name = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
        return substr($name, 0, $max);
    }

    public static function same_name($a, $b): bool {
        $one = self::clean($a);
        $two = self::clean($b);
        return $one !== '' && strtolower($one) === strtolower($two);
    }

    public static function club_season($date): string {
        if (!preg_match('/^(\d{4})-(\d{2})-\d{2}$/', (string) $date, $m)) {
            return '';
        }
        $year = (int) $m[1];
        $month = (int) $m[2];
        $start = $month >= 8 ? $year : $year - 1;
        return $start . '-' . ($start + 1);
    }

    public static function season_start_date($season): string {
        if (!preg_match('/^(\d{4})\s*[-\/]\s*(\d{4})$/', (string) $season, $m)) {
            return '';
        }
        return $m[1] . '-08-01';
    }

    public static function normalize_season($value, $date = ''): string {
        $raw = trim((string) $value);
        if (preg_match('/^(\d{4})\s*[-\/]\s*(\d{2,4})$/', $raw, $m)) {
            $start = (int) $m[1];
            $end = (int) $m[2];
            if ($end < 100) {
                $end += 2000;
            }
            if ($end === $start + 1) {
                return $start . '-' . $end;
            }
        }
        return self::club_season($date);
    }

    public static function normalize_round($value): string {
        $raw = strtolower(self::clean($value, 40));
        if ($raw === '') {
            return '';
        }
        $raw = preg_replace('/^[hv]\s*[.\-:]?\s*/i', '', $raw) ?? $raw;
        $raw = preg_replace('/kwart\s*-?\s*finale/', 'kwart finale', $raw) ?? $raw;
        $raw = preg_replace('/halve?\s*-?\s*finale/', 'halve finale', $raw) ?? $raw;
        $raw = preg_replace('/\b8e\b|\b8e\s+finale\b|\bachtste\s+finale\b/', '8ste finale', $raw) ?? $raw;
        if ($raw === 'finale' || $raw === 'endstrijd') {
            return 'finale';
        }
        if (in_array($raw, self::ROUND_NAMES, true)) {
            return $raw;
        }
        if ($raw === 'kwartfinale') {
            return 'kwart finale';
        }
        if ($raw === 'halvefinale' || $raw === 'halve') {
            return 'halve finale';
        }
        if ($raw === '8ste' || $raw === '8ste finale') {
            return '8ste finale';
        }
        return self::clean($value, 40);
    }

    public static function parse_frame_result(array $input): ?array {
        $direct_for = isset($input['framesFor']) || isset($input['fPlus']) || isset($input['F+'])
            ? intval($input['framesFor'] ?? $input['fPlus'] ?? $input['F+'] ?? '')
            : null;
        $direct_against = isset($input['framesAgainst']) || isset($input['fMinus']) || isset($input['F-'])
            ? intval($input['framesAgainst'] ?? $input['fMinus'] ?? $input['F-'] ?? '')
            : null;
        if ($direct_for !== null && $direct_against !== null && $direct_for >= 0 && $direct_against >= 0
            && (isset($input['framesFor']) || isset($input['fPlus']) || isset($input['F+']))) {
            return ['framesFor' => $direct_for, 'framesAgainst' => $direct_against];
        }
        $raw = trim((string) ($input['result'] ?? $input['RESULT'] ?? $input['uitslag'] ?? ''));
        if (preg_match('/^(\d+)\s*[-–:\/]\s*(\d+)$/', $raw, $m)) {
            return ['framesFor' => (int) $m[1], 'framesAgainst' => (int) $m[2]];
        }
        $win = isset($input['w']) || isset($input['W']) || isset($input['win'])
            ? intval($input['w'] ?? $input['W'] ?? $input['win'] ?? '')
            : null;
        $loss = isset($input['l']) || isset($input['L']) || isset($input['loss'])
            ? intval($input['l'] ?? $input['L'] ?? $input['loss'] ?? '')
            : null;
        if ($win === 1 && $loss !== 1) {
            return ['framesFor' => 1, 'framesAgainst' => 0];
        }
        if ($loss === 1 && $win !== 1) {
            return ['framesFor' => 0, 'framesAgainst' => 1];
        }
        return null;
    }

    public static function synthesize_frames($frames_for, $frames_against, int $slots = 5): array {
        $won = max(0, intval($frames_for));
        $lost = max(0, intval($frames_against));
        $need = min(max($slots, $won + $lost), 17);
        $frames = [];
        for ($i = 0; $i < $won; $i++) {
            $frames[] = ['p1' => 1, 'p2' => 0];
        }
        for ($i = 0; $i < $lost; $i++) {
            $frames[] = ['p1' => 0, 'p2' => 1];
        }
        while (count($frames) < $need) {
            $frames[] = ['p1' => 0, 'p2' => 0];
        }
        return array_slice($frames, 0, $need);
    }

    public static function has_real_points(array $frames): bool {
        foreach ($frames as $frame) {
            if (((int) ($frame['p1'] ?? 0)) > 1 || ((int) ($frame['p2'] ?? 0)) > 1) {
                return true;
            }
        }
        return false;
    }

    public static function pad_frames($frames, int $slots = 5): array {
        $rows = [];
        foreach (is_array($frames) ? $frames : [] as $frame) {
            $rows[] = ['p1' => (int) ($frame['p1'] ?? 0), 'p2' => (int) ($frame['p2'] ?? 0)];
        }
        while (count($rows) < $slots) {
            $rows[] = ['p1' => 0, 'p2' => 0];
        }
        return $rows;
    }

    public static function paper_input_to_match(array $input): array {
        $player1 = self::clean($input['player1'] ?? $input['player'] ?? $input['PLAYER'] ?? '');
        $player2 = self::clean($input['player2'] ?? $input['versus'] ?? $input['VERSUS'] ?? '');
        $tournament = self::clean($input['tournament'] ?? $input['TOURNAMENT'] ?? '');
        $season = self::normalize_season($input['season'] ?? $input['SEASON'] ?? '', $input['date'] ?? '');
        $date = trim((string) ($input['date'] ?? $input['DATE'] ?? ''));
        if ($date === '') {
            $date = self::season_start_date($season);
        }
        $parsed = self::parse_frame_result($input);
        $raw_frames = is_array($input['frames'] ?? null) ? $input['frames'] : [];
        $has_scored = false;
        foreach ($raw_frames as $frame) {
            if (((int) ($frame['p1'] ?? 0)) + ((int) ($frame['p2'] ?? 0)) > 0) {
                $has_scored = true;
                break;
            }
        }
        $frames = $has_scored
            ? self::pad_frames($raw_frames, 5)
            : self::synthesize_frames($parsed['framesFor'] ?? 0, $parsed['framesAgainst'] ?? 0);
        return [
            'tournament' => $tournament,
            'date' => $date,
            'player1' => $player1,
            'player2' => $player2,
            'frames' => $frames,
            'break1' => intval($input['break1'] ?? $input['breaks'] ?? $input['BREAKS'] ?? 0),
            'break2' => intval($input['break2'] ?? 0),
            'season' => $season,
            'round' => self::normalize_round($input['round'] ?? $input['ROUND'] ?? ''),
            'source' => 'paper',
            'matchType' => strtolower((string) ($input['matchType'] ?? $input['type'] ?? 'competitie')),
            'table' => self::clean($input['table'] ?? '', 40),
            'referee' => self::clean($input['referee'] ?? '', 80),
            'note' => substr(trim((string) ($input['note'] ?? '')), 0, 200),
        ];
    }

    public static function pct($part, $whole, int $digits = 2): float {
        if (!$whole) {
            return 0;
        }
        $factor = 10 ** $digits;
        return round(($part / $whole) * 100 * $factor) / $factor;
    }

    public static function real_point_frames(array $frames): array {
        $out = [];
        foreach ($frames as $frame) {
            $a = (int) ($frame['p1'] ?? 0);
            $b = (int) ($frame['p2'] ?? 0);
            if ($a > 1 || $b > 1) {
                $out[] = ['p1' => $a, 'p2' => $b];
            }
        }
        return $out;
    }

    public static function resolve_player_name(string $value, array $matches = [], array $roster = []): string {
        $raw = self::clean(str_replace('+', ' ', urldecode($value)));
        if ($raw === '') {
            return '';
        }
        foreach ($roster as $row) {
            $id = (string) ($row['id'] ?? '');
            $name = self::clean($row['name'] ?? '');
            if ($id !== '' && ($id === $value || $id === $raw)) {
                return $name;
            }
            if (self::same_name($name, $raw)) {
                return $name;
            }
        }
        foreach ($matches as $match) {
            foreach (['player1', 'player2'] as $key) {
                $name = self::clean($match[$key] ?? '');
                if (self::same_name($name, $raw)) {
                    return $name;
                }
            }
        }
        return $raw;
    }

    public static function empty_dossier(string $player = ''): array {
        return [
            'player' => self::clean($player),
            'rows' => [],
            'career' => self::player_career([]),
            'headToHead' => [],
        ];
    }

    public static function frame_wins_of(array $match): array {
        $from_frames = ['p1' => 0, 'p2' => 0];
        foreach ($match['frames'] ?? [] as $frame) {
            $a = (int) ($frame['p1'] ?? 0);
            $b = (int) ($frame['p2'] ?? 0);
            if ($a > $b) {
                $from_frames['p1']++;
            } elseif ($b > $a) {
                $from_frames['p2']++;
            }
        }
        if ($from_frames['p1'] + $from_frames['p2'] > 0) {
            return $from_frames;
        }
        if (isset($match['wins']['p1'], $match['wins']['p2'])
            && ((int) $match['wins']['p1'] + (int) $match['wins']['p2']) > 0) {
            return ['p1' => (int) $match['wins']['p1'], 'p2' => (int) $match['wins']['p2']];
        }
        $parsed = self::parse_frame_result($match);
        if ($parsed && ($parsed['framesFor'] + $parsed['framesAgainst']) > 0) {
            return ['p1' => $parsed['framesFor'], 'p2' => $parsed['framesAgainst']];
        }
        return $from_frames;
    }

    public static function match_row_for_player(array $match, string $player_name): ?array {
        $name = self::clean($player_name);
        $first = self::same_name($match['player1'] ?? '', $name);
        $second = self::same_name($match['player2'] ?? '', $name);
        if (!$first && !$second) {
            return null;
        }
        $wins = self::frame_wins_of($match);
        $frames_for = $first ? $wins['p1'] : $wins['p2'];
        $frames_against = $first ? $wins['p2'] : $wins['p1'];
        $won = $frames_for > $frames_against;
        $lost = $frames_against > $frames_for;
        $outcome = $won ? 'H' : ($lost ? 'V' : '');
        $round = self::normalize_round($match['round'] ?? '');
        $break = $first ? (int) ($match['break1'] ?? 0) : (int) ($match['break2'] ?? 0);
        $real = self::real_point_frames($match['frames'] ?? []);
        $points_for = 0;
        $points_against = 0;
        foreach ($real as $frame) {
            $points_for += $first ? $frame['p1'] : $frame['p2'];
            $points_against += $first ? $frame['p2'] : $frame['p1'];
        }
        return [
            'matchId' => $match['id'] ?? '',
            'date' => $match['date'] ?? '',
            'versus' => $first ? ($match['player2'] ?? '') : ($match['player1'] ?? ''),
            'tournament' => $match['tournament'] ?? '',
            'result' => $frames_for . '-' . $frames_against,
            'framesFor' => $frames_for,
            'framesAgainst' => $frames_against,
            'pointsFor' => $points_for,
            'pointsAgainst' => $points_against,
            'realFrames' => count($real),
            'w' => $won ? 1 : 0,
            'l' => $lost ? 1 : 0,
            'breaks' => $break,
            'outcome' => $outcome,
            'round' => $round,
            'roundLabel' => trim($outcome . ' ' . $round),
            'season' => $match['season'] ?? self::club_season($match['date'] ?? ''),
            'source' => $match['source'] ?? (self::has_real_points($match['frames'] ?? []) ? 'guest' : 'paper'),
        ];
    }

    public static function player_career(array $rows): array {
        $wins_matches = count(array_filter($rows, fn($row) => ($row['w'] ?? 0) === 1));
        $losses_matches = count(array_filter($rows, fn($row) => ($row['l'] ?? 0) === 1));
        $wins_frames = array_reduce($rows, fn($sum, $row) => $sum + (int) ($row['framesFor'] ?? 0), 0);
        $losses_frames = array_reduce($rows, fn($sum, $row) => $sum + (int) ($row['framesAgainst'] ?? 0), 0);
        $breaks = array_values(array_filter(array_map(fn($row) => (int) ($row['breaks'] ?? 0), $rows), fn($v) => $v > 0));
        $gem = $breaks ? round(array_sum($breaks) / count($breaks) * 100) / 100 : 0;
        $total_matches = count($rows);
        $total_frames = $wins_frames + $losses_frames;
        $decided = $wins_matches + $losses_matches;
        $real_frames = array_reduce($rows, fn($sum, $row) => $sum + (int) ($row['realFrames'] ?? 0), 0);
        $points_for = array_reduce($rows, fn($sum, $row) => $sum + (int) ($row['pointsFor'] ?? 0), 0);
        return [
            'winsMatches' => $wins_matches,
            'lossesMatches' => $losses_matches,
            'totalMatches' => $total_matches,
            'winsFrames' => $wins_frames,
            'lossesFrames' => $losses_frames,
            'totalFrames' => $total_frames,
            'matchPct' => self::pct($wins_matches, $decided ?: $total_matches),
            'framePct' => self::pct($wins_frames, $total_frames),
            'highestBreak' => $breaks ? max($breaks) : 0,
            'gemBreak' => $gem,
            'avgPoints' => $real_frames ? round(($points_for / $real_frames) * 100) / 100 : null,
            'avgFrames' => $total_matches ? round(($wins_frames / $total_matches) * 100) / 100 : null,
        ];
    }

    public static function player_dossier(array $matches, string $player_name, array $roster = []): array {
        $name = self::resolve_player_name($player_name, $matches, $roster);
        if ($name === '') {
            return self::empty_dossier();
        }
        $rows = [];
        foreach ($matches as $match) {
            $row = self::match_row_for_player($match, $name);
            if ($row) {
                $rows[] = $row;
            }
        }
        usort($rows, function ($a, $b) {
            $d = strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? ''));
            return $d !== 0 ? $d : strcasecmp((string) ($a['versus'] ?? ''), (string) ($b['versus'] ?? ''));
        });
        $opponents = [];
        foreach ($rows as $row) {
            $key = strtolower($row['versus']);
            if (!isset($opponents[$key])) {
                $opponents[$key] = [
                    'versus' => $row['versus'],
                    'wins' => 0,
                    'losses' => 0,
                    'framesFor' => 0,
                    'framesAgainst' => 0,
                    'lastDate' => '',
                    'lastResult' => '',
                ];
            }
            $opponents[$key]['wins'] += $row['w'];
            $opponents[$key]['losses'] += $row['l'];
            $opponents[$key]['framesFor'] += $row['framesFor'];
            $opponents[$key]['framesAgainst'] += $row['framesAgainst'];
            if ((string) ($row['date'] ?? '') >= $opponents[$key]['lastDate']) {
                $opponents[$key]['lastDate'] = $row['date'];
                $opponents[$key]['lastResult'] = $row['result'];
            }
        }
        $h2h = array_values($opponents);
        usort($h2h, fn($a, $b) => strcasecmp($a['versus'], $b['versus']));
        return [
            'player' => $name,
            'rows' => $rows,
            'career' => self::player_career($rows),
            'headToHead' => $h2h,
        ];
    }

    public static function head_to_head(array $matches, string $player_a, string $player_b): array {
        $a = self::clean($player_a);
        $b = self::clean($player_b);
        if ($a === '' || $b === '' || self::same_name($a, $b)) {
            return [
                'a' => $a,
                'b' => $b,
                'wins' => 0,
                'losses' => 0,
                'framesFor' => 0,
                'framesAgainst' => 0,
                'lastDate' => '',
                'lastResult' => '',
                'rows' => [],
            ];
        }
        $dossier = self::player_dossier($matches, $a);
        $rows = array_values(array_filter($dossier['rows'], fn($row) => self::same_name($row['versus'], $b)));
        $pair = [
            'versus' => $b,
            'wins' => 0,
            'losses' => 0,
            'framesFor' => 0,
            'framesAgainst' => 0,
            'lastDate' => '',
            'lastResult' => '',
        ];
        foreach ($dossier['headToHead'] as $row) {
            if (self::same_name($row['versus'], $b)) {
                $pair = $row;
                break;
            }
        }
        return [
            'a' => $a,
            'b' => $b,
            'wins' => $pair['wins'],
            'losses' => $pair['losses'],
            'framesFor' => $pair['framesFor'],
            'framesAgainst' => $pair['framesAgainst'],
            'lastDate' => $pair['lastDate'],
            'lastResult' => $pair['lastResult'],
            'rows' => $rows,
        ];
    }

    public static function decorate_ranked_player(array $player): array {
        $break_sum = $player['breakSum'] ?? 0;
        $break_count = $player['breakCount'] ?? 0;
        unset($player['breakSum'], $player['breakCount']);
        $frames = ((int) ($player['framesFor'] ?? 0)) + ((int) ($player['framesAgainst'] ?? 0));
        $played = (int) ($player['played'] ?? 0);
        $decided = ((int) ($player['wins'] ?? 0)) + ((int) ($player['losses'] ?? 0));
        $player['matchPct'] = self::pct($player['wins'] ?? 0, $decided ?: $played);
        $player['framePct'] = self::pct($player['framesFor'] ?? 0, $frames);
        $player['gemBreak'] = $break_count ? round(($break_sum / $break_count) * 100) / 100 : 0;
        $player['avgFrames'] = $played ? round((((int) ($player['framesFor'] ?? 0)) / $played) * 100) / 100 : null;
        $player['avgPoints'] = !empty($player['framesPlayed']) ? $player['handicap'] : null;
        return $player;
    }

    public static function matches_in_season(array $matches, string $season = ''): array {
        $want = self::normalize_season($season);
        if ($want === '') {
            return array_values($matches);
        }
        return array_values(array_filter($matches, static function ($match) use ($want) {
            $have = (string) ($match['season'] ?? '');
            if ($have === '') {
                $have = self::club_season($match['date'] ?? '');
            }
            return $have === $want || self::normalize_season($have) === $want;
        }));
    }

    public static function csv_cell($value): string {
        $text = (string) ($value ?? '');
        if (preg_match('/[";\n\r]/', $text)) {
            return '"' . str_replace('"', '""', $text) . '"';
        }
        return $text;
    }

    public static function to_csv(array $headers, array $rows): string {
        $lines = [implode(';', array_map([self::class, 'csv_cell'], $headers))];
        foreach ($rows as $row) {
            $cells = [];
            foreach ($headers as $header) {
                $cells[] = self::csv_cell($row[$header] ?? '');
            }
            $lines[] = implode(';', $cells);
        }
        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    public static function format_pct($value, int $digits = 2): string {
        return number_format((float) $value, $digits, ',', '') . '%';
    }

    public static function ranking_csv(array $players): string {
        $headers = ['#', 'Speler', 'W', 'L', 'F+', 'F-', 'M%', 'F%', 'HB', 'Gem. punten/frame'];
        $rows = [];
        foreach ($players as $player) {
            $avg = $player['avgPoints'] ?? $player['avgFrames'] ?? null;
            $rows[] = [
                '#' => $player['rank'] ?? '',
                'Speler' => $player['name'] ?? '',
                'W' => $player['wins'] ?? 0,
                'L' => $player['losses'] ?? 0,
                'F+' => $player['framesFor'] ?? 0,
                'F-' => $player['framesAgainst'] ?? 0,
                'M%' => self::format_pct($player['matchPct'] ?? $player['winRate'] ?? 0, isset($player['matchPct']) ? 2 : 0),
                'F%' => self::format_pct($player['framePct'] ?? 0),
                'HB' => $player['highestBreak'] ?? 0,
                'Gem. punten/frame' => $avg === null ? '' : str_replace('.', ',', (string) $avg),
            ];
        }
        return self::to_csv($headers, $rows);
    }

    public static function dossier_csv(array $dossier): string {
        $headers = ['VERSUS', 'TOURNAMENT', 'RESULT', 'W', 'L', 'BREAKS', 'ROUND', 'SEASON'];
        $rows = [];
        foreach ($dossier['rows'] ?? [] as $row) {
            $rows[] = [
                'VERSUS' => $row['versus'] ?? '',
                'TOURNAMENT' => $row['tournament'] ?? '',
                'RESULT' => str_replace('-', '/', (string) ($row['result'] ?? '')),
                'W' => $row['w'] ?? 0,
                'L' => $row['l'] ?? 0,
                'BREAKS' => $row['breaks'] ?? '',
                'ROUND' => $row['roundLabel'] ?? $row['round'] ?? '',
                'SEASON' => $row['season'] ?? '',
            ];
        }
        return self::to_csv($headers, $rows);
    }

    public static function seasons(array $matches): array {
        $set = [];
        foreach ($matches as $match) {
            $season = (string) ($match['season'] ?? '') ?: self::club_season($match['date'] ?? '');
            $season = self::normalize_season($season) ?: $season;
            if ($season !== '') {
                $set[$season] = true;
            }
        }
        $list = array_keys($set);
        rsort($list);
        return $list;
    }

    public static function parse_paper_csv(string $text, string $player_fallback = ''): array {
        $raw = trim(preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text);
        if ($raw === '') {
            return [];
        }
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw) ?: [])));
        if (!$lines) {
            return [];
        }
        $header = array_map('strtolower', self::split_csv_line($lines[0]));
        $looks = (bool) array_intersect($header, ['versus', 'tegenstander', 'tournament', 'toernooi', 'result', 'uitslag', 'season', 'seizoen', 'player', 'speler']);
        $keys = $looks ? $header : [];
        $start = $looks ? 1 : 0;
        $alias = [
            'player' => 'player', 'speler' => 'player', 'versus' => 'versus', 'tegenstander' => 'versus',
            'tournament' => 'tournament', 'toernooi' => 'tournament', 'result' => 'result', 'uitslag' => 'result',
            'w' => 'w', 'l' => 'l', 'breaks' => 'breaks', 'break' => 'breaks',
            'round' => 'round', 'ronde' => 'round', 'season' => 'season', 'seizoen' => 'season',
            'date' => 'date', 'datum' => 'date', 'f+' => 'framesFor', 'f-' => 'framesAgainst',
        ];
        $rows = [];
        for ($i = $start; $i < count($lines); $i++) {
            $cells = self::split_csv_line($lines[$i]);
            $row = [];
            if ($keys) {
                foreach ($keys as $index => $key) {
                    $mapped = $alias[$key] ?? $key;
                    $row[$mapped] = $cells[$index] ?? '';
                }
            } else {
                $row['versus'] = $cells[0] ?? '';
                $row['tournament'] = $cells[1] ?? '';
                $row['result'] = $cells[2] ?? '';
                $row['w'] = $cells[3] ?? '';
                $row['l'] = $cells[4] ?? '';
                $row['breaks'] = $cells[5] ?? '';
                $row['round'] = $cells[6] ?? '';
                $row['season'] = $cells[7] ?? '';
            }
            if (($row['player'] ?? '') === '') {
                $row['player'] = $player_fallback;
            }
            if (($row['versus'] ?? '') !== '' || ($row['tournament'] ?? '') !== '') {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    private static function split_csv_line(string $line): array {
        $cells = [];
        $current = '';
        $quoted = false;
        $len = strlen($line);
        for ($i = 0; $i < $len; $i++) {
            $ch = $line[$i];
            if ($quoted) {
                if ($ch === '"' && ($line[$i + 1] ?? '') === '"') {
                    $current .= '"';
                    $i++;
                } elseif ($ch === '"') {
                    $quoted = false;
                } else {
                    $current .= $ch;
                }
            } elseif ($ch === '"') {
                $quoted = true;
            } elseif ($ch === ';' || $ch === ',') {
                $cells[] = trim($current);
                $current = '';
            } else {
                $current .= $ch;
            }
        }
        $cells[] = trim($current);
        return $cells;
    }
}
