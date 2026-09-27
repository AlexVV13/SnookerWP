<?php
/**
 * CLI checks for the PHP store — same cases as scripts/check.js.
 */
require dirname(__DIR__) . '/snookerclub/includes/class-store.php';

$failed = 0;
function assert_true($cond, $msg) {
    global $failed;
    if (!$cond) {
        $failed++;
        fwrite(STDERR, "  FAIL $msg\n");
    }
}

$sample = Snookerclub_Store::normalize_match([
    'tournament' => 'Clubavond',
    'date' => '2026-09-12',
    'player1' => 'Anna',
    'player2' => 'Ben',
    'frames' => [
        ['p1' => 72, 'p2' => 21],
        ['p1' => 8, 'p2' => 67],
        ['p1' => 80, 'p2' => 12],
        ['p1' => 0, 'p2' => 0],
        ['p1' => 0, 'p2' => 0],
    ],
    'break1' => 56,
    'break2' => 32,
]);
$wins = Snookerclub_Store::frame_wins($sample['frames']);
assert_true($wins['p1'] === 2 && $wins['p2'] === 1, 'frame wins');
assert_true($sample['handicap1'] === 53.3 && $sample['handicap2'] === 33.3, 'match handicap averages played frames');
assert_true($sample['framesPlayed'] === 3, 'unused 0-0 frames are ignored');
assert_true(Snookerclub_Store::match_averages($sample['frames'])['handicap1'] === 53.3, 'Anna frames average 53.3');

$ignored = Snookerclub_Store::normalize_match([
    'tournament' => 'Clubavond',
    'date' => '2026-09-12',
    'player1' => 'Anna',
    'player2' => 'Ben',
    'handicap1' => 14,
    'handicap2' => 99,
    'frames' => $sample['frames'],
    'break1' => 56,
    'break2' => 32,
]);
assert_true($ignored['handicap1'] === 53.3, 'manual handicap fields are ignored');

$extra = Snookerclub_Store::normalize_match([
    'tournament' => 'Clubavond',
    'date' => '2026-09-11',
    'player1' => 'Anna',
    'player2' => 'Chris',
    'frames' => [
        ['p1' => 80, 'p2' => 10],
        ['p1' => 70, 'p2' => 20],
        ['p1' => 0, 'p2' => 0],
        ['p1' => 0, 'p2' => 0],
        ['p1' => 0, 'p2' => 0],
    ],
    'break1' => 100,
    'break2' => 12,
    'table' => 'Baan 2',
    'matchType' => 'beker',
]);
$ranked = Snookerclub_Store::rank_players([$sample, $extra]);
assert_true($ranked[0]['name'] === 'Anna' && $ranked[0]['trophy'] === 'gold', 'leader gets gold trophy');
$anna = null;
$ben = null;
foreach ($ranked as $row) {
    if ($row['name'] === 'Anna') $anna = $row;
    if ($row['name'] === 'Ben') $ben = $row;
}
assert_true($anna && $anna['handicap'] === 62.0, 'Anna career handicap averages all played frames');
assert_true($ben && $ben['handicap'] === 33.3, 'Ben career handicap is 33.3');
assert_true($anna && $anna['framePct'] === 80.0 && $anna['matchPct'] === 100.0, 'Anna excel match and frame percentages');
assert_true(Snookerclub_Excel::club_season('2016-09-12') === '2016-2017' && Snookerclub_Excel::club_season('2016-07-31') === '2015-2016', 'club season runs August-July');
$paper = Snookerclub_Store::normalize_match(Snookerclub_Excel::paper_input_to_match([
    'player1' => 'Anna',
    'versus' => 'Ben',
    'tournament' => 'Snookertronooi 1',
    'date' => '2016-09-18',
    'result' => '2-1',
    'breaks' => 42,
    'round' => 'H kwart finale',
    'season' => '2016-2017',
]));
assert_true(($paper['source'] ?? '') === 'paper' && $paper['season'] === '2016-2017' && $paper['round'] === 'kwart finale', 'paper match keeps season and round');
$paper_wins = Snookerclub_Store::frame_wins($paper['frames']);
assert_true($paper_wins['p1'] === 2 && $paper_wins['p2'] === 1 && (int) $paper['handicap1'] === 0, 'paper frames synthesize without point average');
$dossier = Snookerclub_Excel::player_dossier([$paper], 'Anna');
assert_true($dossier['rows'][0]['versus'] === 'Ben' && $dossier['rows'][0]['roundLabel'] === 'H kwart finale', 'dossier row is from the player viewpoint');
$mixed = Snookerclub_Store::rank_players([$sample, $extra, $paper]);
$anna_mixed = null;
$ben_mixed = null;
foreach ($mixed as $row) {
    if ($row['name'] === 'Anna') $anna_mixed = $row;
    if ($row['name'] === 'Ben') $ben_mixed = $row;
}
assert_true($anna_mixed && $anna_mixed['handicap'] === 62.0 && ($anna_mixed['avgPoints'] ?? null) === 62.0, 'paper 1-0 frames do not dilute Anna point average');
assert_true($anna_mixed && $anna_mixed['framesFor'] === 6 && $anna_mixed['wins'] === 3 && $anna_mixed['losses'] === 0, 'Anna W/L and F+ count paper and signed matches');
assert_true($ben_mixed && $ben_mixed['framesFor'] === 2 && $ben_mixed['losses'] === 2, 'Ben frames and losses count every result');
$empty_dossier = Snookerclub_Excel::player_dossier([], '');
assert_true(($empty_dossier['player'] ?? 'x') === '' && ($empty_dossier['rows'] ?? ['x']) === [], 'empty dossier name does not throw');
$unknown_dossier = Snookerclub_Excel::player_dossier([$paper], 'Onbekende');
assert_true(($unknown_dossier['player'] ?? '') === 'Onbekende' && ($unknown_dossier['rows'] ?? ['x']) === [], 'unknown player dossier is empty');
assert_true(Snookerclub_Excel::pct(184, 209) === 88.04 && Snookerclub_Excel::pct(414, 531) === 77.97, 'snippet-2 match and frame percentages');
assert_true($ranked[1]['trophy'] === 'silver' && $ranked[2]['trophy'] === 'bronze', 'top 3 get trophies');

$summary = Snookerclub_Store::summarize_matches([$sample, $extra]);
assert_true($summary['highestBreak']['value'] === 100 && $summary['centuries'] === 1, 'highest break and centuries');
assert_true(Snookerclub_Store::progress_meter(3, 12)['pct'] === 25 && Snookerclub_Store::progress_meter(20, 10)['pct'] === 100, 'KPI progress caps at 100');
assert_true(Snookerclub_Store::iso_week_start('2026-09-12') === '2026-09-07', 'ISO week starts on Monday');

$event = Snookerclub_Store::normalize_event([
    'title' => 'Les',
    'date' => '2026-09-15',
    'start' => '19:30:00',
    'end' => '21:00:00',
]);
assert_true($event['start'] === '19:30', 'time inputs may send seconds');

$clubavond = Snookerclub_Store::normalize_event([
    'title' => 'Clubavond',
    'date' => '2026-09-15',
    'kind' => 'clubavond',
    'start' => '19:30',
    'end' => '23:00',
]);
$memberKpis = Snookerclub_Store::club_kpis(
    [],
    [
        ['createdAt' => '2026-09-12T10:00:00.000Z'],
        ['createdAt' => '2026-08-01T10:00:00.000Z'],
    ],
    [],
    ['goalNewMembersMonth' => 5, 'goalClubNightsMonth' => 4],
    [$clubavond],
    '2026-09-12'
);
assert_true($memberKpis['newMembersMonth'] === 1 && $memberKpis['progress']['members']['goal'] === 5, 'counts new members this month');
assert_true($memberKpis['clubNightsMonth'] === 1 && $memberKpis['clubNightsUpcoming'] === 1, 'counts club nights from agenda');

$agenda = Snookerclub_Store::build_month_agenda([$clubavond], 2026, 9, '2026-09-12');
$day = null;
foreach ($agenda['days'] as $row) {
    if (($row['date'] ?? '') === '2026-09-15') $day = $row;
}
assert_true($agenda['weekdays'][0] === 'ma' && $day && $day['events'][0]['title'] === 'Clubavond', 'month agenda groups events');
assert_true(count($agenda['upcoming']) === 1 && str_contains(strtolower($agenda['label']), 'september'), 'upcoming events stay after today');

try {
    Snookerclub_Store::normalize_event(['title' => '', 'date' => '2026-09-15']);
    assert_true(false, 'empty agenda title should throw');
} catch (Snookerclub_Invalid $err) {
    assert_true($err->code_name === 'INVALID', 'rejects empty agenda title');
}

assert_true($extra['table'] === 'Baan 2' && $extra['matchType'] === 'beker', 'keeps match details');

try {
    Snookerclub_Store::normalize_match([
        'tournament' => 'Clubavond',
        'date' => '2026-09-12',
        'player1' => 'Anna',
        'player2' => 'Anna',
        'frames' => $sample['frames'],
        'break1' => 0,
        'break2' => 0,
    ]);
    assert_true(false, 'same player should throw');
} catch (Snookerclub_Invalid $err) {
    assert_true($err->code_name === 'INVALID' && str_contains($err->getMessage(), 'verschillende'), 'rejects identical players');
}

try {
    Snookerclub_Store::normalize_match(array_merge($sample, ['date' => '12-09-2026']));
    assert_true(false, 'bad date should throw');
} catch (Snookerclub_Invalid $err) {
    assert_true($err->code_name === 'INVALID', 'rejects bad date');
}

$dir = sys_get_temp_dir() . '/snookerclub-test-' . getmypid();
@mkdir($dir, 0775, true);
$store = new Snookerclub_Store($dir);
$store->create_player(['name' => 'Chris']);
$store->create_player(['name' => 'Dana']);
$sig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=';
try {
    $store->create_match([
        'tournament' => 'Open',
        'date' => '2026-09-12',
        'player1' => 'Chris',
        'player2' => 'Dana',
        'frames' => [['p1' => 90, 'p2' => 10], ['p1' => 12, 'p2' => 70], ['p1' => 61, 'p2' => 40], ['p1' => 0, 'p2' => 0], ['p1' => 0, 'p2' => 0]],
        'break1' => 88,
        'break2' => 41,
    ]);
    assert_true(false, 'guest must send both signatures');
} catch (Snookerclub_Invalid $err) {
    assert_true(str_contains($err->getMessage(), 'handtekening'), 'guest must send both signatures');
}
$created = $store->create_match([
    'tournament' => 'Open',
    'date' => '2026-09-12',
    'player1' => 'Chris',
    'player2' => 'Dana',
    'frames' => [['p1' => 90, 'p2' => 10], ['p1' => 12, 'p2' => 70], ['p1' => 61, 'p2' => 40], ['p1' => 0, 'p2' => 0], ['p1' => 0, 'p2' => 0]],
    'break1' => 88,
    'break2' => 41,
    'signature1' => $sig,
    'signature2' => $sig,
]);
assert_true($created['handicap1'] === 54.3 && $created['handicap2'] === 40.0, 'posted match handicap is frame average');
assert_true(!empty($created['signed']), 'posted match keeps both signatures');
$over = $store->overview('/snooker');
assert_true($over['highestBreak']['value'] === 88 && $over['highestBreak']['player'] === 'Chris', 'overview highest break');
$paper_saved = $store->create_paper_match([
    'player1' => 'Chris',
    'versus' => 'Dana',
    'tournament' => 'Snookertronooi 1',
    'date' => '2016-09-18',
    'framesFor' => 2,
    'framesAgainst' => 1,
    'breaks' => 49,
    'round' => 'kwart finale',
]);
assert_true(($paper_saved['source'] ?? '') === 'paper' && $paper_saved['season'] === '2016-2017', 'php store saves paper match without signatures');
$imported = $store->import_paper([
    'player' => 'Chris',
    'csv' => "VERSUS;TOURNAMENT;RESULT;W;L;BREAKS;ROUND;SEASON\nAnna;Podblack 2;1-0;1;0;27;H finale;2016-2017",
]);
assert_true(($imported['count'] ?? 0) === 1, 'php store imports excel paper row');
$chris_dossier = $store->player_dossier('Chris');
assert_true(($chris_dossier['career']['winsMatches'] ?? 0) === 3, 'php dossier counts paper and signed match wins');
assert_true(($chris_dossier['career']['winsFrames'] ?? 0) === 5, 'php dossier F+ counts signed and paper frames');
assert_true(($chris_dossier['career']['avgPoints'] ?? null) === 54.33, 'php dossier gem. punten ignores paper 1-0 frames');
$blank_dossier = $store->player_dossier('');
assert_true(($blank_dossier['rows'] ?? ['x']) === [], 'store empty player dossier does not throw');
$ghost_dossier = $store->player_dossier('Niemand');
assert_true(($ghost_dossier['player'] ?? '') === 'Niemand' && ($ghost_dossier['rows'] ?? ['x']) === [], 'unknown store dossier is empty');
$season_report = $store->report('2016-2017');
assert_true(($season_report['season'] ?? '') === '2016-2017' && ($season_report['matchCount'] ?? 0) >= 2, 'php report filters August-July season');
$season_dossier = $store->player_dossier('Chris', '2016-2017');
assert_true(($season_dossier['career']['winsMatches'] ?? 0) >= 1 && empty(array_filter($season_dossier['rows'], fn($row) => ($row['season'] ?? '') !== '2016-2017')), 'php dossier can filter by season');
$csv = Snookerclub_Excel::ranking_csv($season_report['players']);
assert_true(str_contains($csv, 'F+') && str_contains($csv, 'Speler'), 'php ranking csv has excel columns');

$fresh = Snookerclub_Store::normalize_brand([]);
assert_true(($fresh['clubName'] ?? '') === 'SC De Merodesnookers' && str_contains((string) ($fresh['venue'] ?? ''), 'Turnhout'), 'default brand is SC De Merodesnookers');
$renamed = Snookerclub_Store::normalize_brand(['clubName' => 'Tafels & Thee']);
assert_true($renamed['clubName'] === 'Tafels & Thee', 'club name can be changed');
$long = Snookerclub_Store::normalize_brand(['framesCount' => 9]);
assert_true(($long['framesCount'] ?? 0) === 9, 'best of 9 is allowed');
$six = Snookerclub_Store::normalize_match([
    'tournament' => '6 Red 1',
    'date' => '2026-09-24',
    'player1' => 'Anna',
    'player2' => 'Ben',
    'frames' => $sample['frames'],
    'break1' => 155,
    'break2' => 0,
    'matchType' => '6red',
]);
assert_true($six['matchType'] === '6red' && $six['break1'] === 155, '6-red and free-ball 155 are valid');
$night = Snookerclub_Store::normalize_brand(['themePreset' => 'midnight', 'clubName' => 'Nachtclub']);
assert_true(($night['bgSide'] ?? '') === '#0b0e11' && ($night['themePreset'] ?? '') === 'midnight', 'midnight theme preset');
$legacy = Snookerclub_Store::normalize_brand(['accent' => '#123456', 'clubName' => 'Oud']);
assert_true(($legacy['accent'] ?? '') === '#123456' && ($legacy['themePreset'] ?? '') === 'custom', 'legacy brand keeps custom accent');

array_map('unlink', glob($dir . '/*') ?: []);
@rmdir($dir);

if ($failed) {
    fwrite(STDERR, "snooker wordpress store check FAILED with $failed problem(s).\n");
    exit(1);
}
echo "snooker wordpress store check passed.\n";
