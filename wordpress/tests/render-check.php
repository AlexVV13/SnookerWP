<?php
/**
 * Smoke-test server-rendered boards without a full WordPress install.
 */
if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/snookerclub-wp-stub/');
}
if (!function_exists('esc_html')) {
    function esc_html($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('esc_url')) {
    function esc_url($value) {
        return (string) $value;
    }
}
if (!function_exists('esc_attr')) {
    function esc_attr($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!class_exists('Snookerclub_Rest')) {
    class Snookerclub_Rest {
        public static function root(): string {
            return 'http://example.test/wp-json/snookerclub/v1';
        }
    }
}

require dirname(__DIR__) . '/snookerclub/includes/class-render.php';
require dirname(__DIR__) . '/snookerclub/includes/class-plugin.php';

$failed = 0;
function assert_true($cond, $msg) {
    global $failed;
    if (!$cond) {
        $failed++;
        fwrite(STDERR, "  FAIL $msg\n");
    }
}

$live = Snookerclub_Render::live([]);
assert_true(str_contains($live, 'data-snooker-live'), 'live board has mount hook');
assert_true(str_contains($live, 'Nog geen wedstrijd'), 'live board shows empty copy');
assert_true(str_contains($live, 'SC De Merodesnookers'), 'empty live board uses the Merode club name');
assert_true(str_contains($live, '/wp-json/snookerclub/v1/live'), 'live board points at REST');
assert_true(!str_contains($live, '<iframe'), 'live board is not an iframe');
$liveLogo = Snookerclub_Render::live(['brand' => ['clubName' => 'Tafels en Thee', 'logoUrl' => 'https://club.test/logo.png']]);
assert_true(str_contains($liveLogo, 'Tafels en Thee') && str_contains($liveLogo, 'snooker-board-logo') && str_contains($liveLogo, 'https://club.test/logo.png'), 'live board shows a custom logo');

$players = Snookerclub_Render::players([]);
assert_true(str_contains($players, 'Nog geen spelers'), 'players board shows empty copy');
assert_true(str_contains($players, 'data-snooker-players'), 'players board has mount hook');
$ranking = Snookerclub_Render::ranking([
    'brand' => ['clubName' => 'Tafels'],
    'players' => [[
        'rank' => 1, 'name' => 'Anna', 'played' => 2, 'wins' => 2, 'losses' => 0,
        'framesFor' => 6, 'framesAgainst' => 1, 'points' => 4, 'handicap' => 62, 'framesPlayed' => 6,
        'highestBreak' => 80, 'trophy' => 'gold',
    ]],
    'top3' => [['name' => 'Anna', 'points' => 4, 'trophy' => 'gold', 'handicap' => 62, 'framesPlayed' => 6]],
], ['skin' => 'site']);
assert_true(str_contains($ranking, 'snooker-league') && str_contains($ranking, 'Anna'), 'ranking renders a league table');
assert_true(str_contains($ranking, 'snookerclub-board--site'), 'ranking can follow the site skin');
assert_true(str_contains($ranking, 'snooker-trophy') && str_contains($ranking, 'snooker-trophy--gold'), 'ranking shows a gold trophy');
$placed = Snookerclub_Render::ranking([
    'brand' => ['clubName' => 'Tafels'],
    'players' => [
        ['rank' => 1, 'name' => 'Anna', 'played' => 2, 'points' => 4, 'framesPlayed' => 6, 'handicap' => 62],
        ['rank' => 2, 'name' => 'Ben', 'played' => 2, 'points' => 2, 'framesPlayed' => 3, 'handicap' => 33],
        ['rank' => 3, 'name' => 'Chris', 'played' => 1, 'points' => 0, 'framesPlayed' => 2, 'handicap' => 20],
    ],
    'top3' => [
        ['rank' => 1, 'name' => 'Anna', 'points' => 4, 'framesPlayed' => 6, 'handicap' => 62],
        ['rank' => 2, 'name' => 'Ben', 'points' => 2, 'framesPlayed' => 3, 'handicap' => 33],
        ['rank' => 3, 'name' => 'Chris', 'points' => 0, 'framesPlayed' => 2, 'handicap' => 20],
    ],
], ['skin' => 'site']);
assert_true(str_contains($placed, 'snooker-trophy--gold') && str_contains($placed, 'snooker-trophy--silver') && str_contains($placed, 'snooker-trophy--bronze'), '1st 2nd 3rd get trophies without a trophy field');
$kpis = Snookerclub_Render::kpis(['kpis' => ['matchesMonth' => 3, 'progress' => []]], ['skin' => 'club']);
assert_true(str_contains($kpis, 'Clubcijfers') && str_contains($kpis, 'snookerclub-board--club'), 'kpi board uses club skin');

$agenda = Snookerclub_Render::agenda(['agenda' => ['label' => 'September 2026', 'weekdays' => ['ma'], 'days' => [], 'upcoming' => []]]);
assert_true(str_contains($agenda, 'Nog geen clubavonden'), 'agenda board shows empty copy');
assert_true(str_contains($agenda, 'data-snooker-agenda'), 'agenda board has mount hook');

$guest = (string) file_get_contents(dirname(__DIR__) . '/../public/guest.html');
assert_true(preg_match('#<body[^>]*>(.*)</body>#s', $guest, $m) === 1 && str_contains($m[1], 'Erelijst'), 'guest body is not empty');
assert_true(str_contains($m[1], 'class="snooker-app"'), 'guest body is scoped');

assert_true(Snookerclub_Plugin::content_has_board('<!-- wp:snookerclub/board {"view":"ranking","skin":"site"} /-->'), 'detects inserted board block');
assert_true(Snookerclub_Plugin::content_has_board('[snookerclub_ranking skin="site"]'), 'detects ranking shortcode');
assert_true(!Snookerclub_Plugin::content_has_board('Hallo, dit is een gewone pagina.'), 'ignores pages without the plugin');
$css = (string) file_get_contents(dirname(__DIR__) . '/../public/board.css');
assert_true(str_contains($css, 'inline-block') && str_contains($css, '.snooker-trophy'), 'board.css keeps trophy icons visible');
assert_true(str_contains($css, '.snooker-sheet-table') && str_contains($css, '@media print'), 'board.css styles printable excel sheets');
assert_true(str_contains($css, 'body.snooker-printing') && str_contains($css, '.snooker-print-target'), 'board.css isolates the report sheet when printing');
$rapport = Snookerclub_Render::rapport([
    'brand' => ['clubName' => 'Tafels'],
    'season' => '2016-2017',
    'printedAt' => '2026-09-26',
    'players' => [[
        'rank' => 1, 'name' => 'Anna', 'played' => 2, 'wins' => 2, 'losses' => 0,
        'framesFor' => 6, 'framesAgainst' => 1, 'points' => 4, 'handicap' => 62, 'framesPlayed' => 6,
        'highestBreak' => 80, 'trophy' => 'gold', 'matchPct' => 100, 'framePct' => 85.71,
    ]],
], ['skin' => 'site']);
assert_true(str_contains($rapport, 'snooker-sheet-head') && str_contains($rapport, 'snooker-league') && str_contains($rapport, 'F+'), 'rapport renders excel ranking sheet');
assert_true(str_contains($rapport, 'data-snooker-rapport') && str_contains($rapport, 'format=csv'), 'rapport has print/csv hooks');
assert_true(str_contains($rapport, 'data-static="1"') && str_contains($rapport, 'data-snooker-print'), 'rapport stays static and prints the sheet');
$dossier = Snookerclub_Render::dossier([
    'brand' => ['clubName' => 'Tafels'],
    'player' => 'Anna',
    'season' => '2016-2017',
    'career' => ['winsMatches' => 1, 'lossesMatches' => 0, 'matchPct' => 100, 'highestBreak' => 42, 'gemBreak' => 42, 'winsFrames' => 2, 'lossesFrames' => 1, 'framePct' => 66.67],
    'headToHead' => [['versus' => 'Ben', 'wins' => 1, 'losses' => 0, 'framesFor' => 2, 'framesAgainst' => 1, 'lastResult' => '2-1']],
    'rows' => [['versus' => 'Ben', 'tournament' => 'Snookertronooi 1', 'result' => '2-1', 'w' => 1, 'l' => 0, 'breaks' => 42, 'roundLabel' => 'H kwart finale', 'season' => '2016-2017']],
], ['skin' => 'club', 'player' => 'Anna']);
assert_true(str_contains($dossier, 'VERSUS') && str_contains($dossier, 'snooker-career-grid') && str_contains($dossier, 'Anna'), 'dossier renders career cards and excel log');
assert_true(str_contains($dossier, 'data-static="1"'), 'dossier is not live-refreshed over the server render');
$empty_sheet = Snookerclub_Render::dossier([
    'brand' => ['clubName' => 'Tafels'],
    'player' => '',
    'career' => [],
    'headToHead' => [],
    'rows' => [],
], ['skin' => 'club']);
assert_true(str_contains($empty_sheet, 'Spelersdossier') && str_contains($empty_sheet, 'Nog geen partijen'), 'empty player dossier renders without error');
assert_true(Snookerclub_Plugin::content_has_board('[snookerclub_rapport]'), 'detects rapport shortcode');

if ($failed) {
    fwrite(STDERR, "snooker wordpress render check FAILED with $failed problem(s).\n");
    exit(1);
}
echo "snooker wordpress render check passed.\n";
