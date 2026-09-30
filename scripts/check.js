#!/usr/bin/env node
import fs from 'fs';
import os from 'os';
import path from 'path';
import http from 'http';
import { fileURLToPath } from 'url';
import { createApp, normalizeBasePath } from '../server.js';
import { normalizeMatch, summarizeMatches, frameWins, rankPlayers, matchAverages, normalizeEvent, buildMonthAgenda, progressMeter, isoWeekStart, clubKpis, clubSeason, playerDossier, playerCareer, parsePaperCsv, paperInputToMatch, rankingCsv } from '../lib/store.js';
import { pct } from '../lib/excel.js';
import { pluginHeaderVersion, pluginManifest, buildPluginZip, PLUGIN_DIR, PUBLIC_ZIP } from '../lib/wpPlugin.js';
import { normalizeTheme } from '../lib/theme.js';
import { DEFAULT_BRAND, normalizeBrand, FRAME_FORMATS, parseFrameFormat } from '../lib/brand.js';
import { spawnSync } from 'child_process';

let failed = 0;
function assert(cond, msg) {
  if (!cond) {
    failed += 1;
    console.error(`  FAIL ${msg}`);
  }
}

const root = path.dirname(fileURLToPath(import.meta.url));
const guestJs = fs.readFileSync(path.join(root, '../public/guest.js'), 'utf8');
const adminJs = fs.readFileSync(path.join(root, '../public/admin.js'), 'utf8');
const embedJs = fs.readFileSync(path.join(root, '../public/embed.js'), 'utf8');
const dockerfile = fs.readFileSync(path.join(root, '../Dockerfile'), 'utf8');
const compose = fs.readFileSync(path.join(root, '../docker-compose.yml'), 'utf8');

const guestHtmlSrc = fs.readFileSync(path.join(root, '../public/guest.html'), 'utf8');
const adminHtmlSrc = fs.readFileSync(path.join(root, '../public/admin.html'), 'utf8');
assert(guestHtmlSrc.includes('/webhost/snooker/guest.css'), 'html does not use /webhost/guest.css');
assert(guestJs.includes('signature1') && guestJs.includes('signature2') && guestJs.includes('frames'), 'guest posts frames and both signatures');
assert(guestHtmlSrc.includes('id="sign1"') && guestHtmlSrc.includes('id="sign2"'), 'guest has two signature pads');
assert(guestHtmlSrc.includes('name="frameFormat"') && guestHtmlSrc.includes('poule'), 'guest has frame format with poule options');
assert(!guestHtmlSrc.includes('name="matchType"') && !guestHtmlSrc.includes('name="table"') && !guestHtmlSrc.includes('name="referee"'), 'guest form dropped soort, baan and scheidsrechter');
assert(guestHtmlSrc.includes('Tornooi') && !guestHtmlSrc.includes('>Toernooi '), 'guest uses Tornooi spelling');
assert(guestJs.includes('signaturesRequired') && guestJs.includes('showSignatures'), 'guest can disable signatures from brand');
assert(DEFAULT_BRAND.tournaments.includes('6 Red') && DEFAULT_BRAND.tournaments.includes('Kersttornooi'), 'default brand has Merode tornooien');
assert(DEFAULT_BRAND.frameMode === 'bestof' && FRAME_FORMATS.some((row) => row.mode === 'fixed' && row.count === 3), 'brand supports fixed poule frames');
assert(!guestJs.includes('handicap1:') && !guestJs.includes('form.handicap1'), 'guest does not post handicap fields');
assert(adminJs.includes('/api/admin/matches') && adminJs.includes('/api/admin/brand'), 'admin edits matches and brand');
assert(adminJs.includes('/api/admin/players') && adminHtmlSrc.includes('player-form'), 'admin manages club players');
assert(adminHtmlSrc.includes('tab-excel') && adminHtmlSrc.includes('paper-form') && adminHtmlSrc.includes('dossier-player'), 'admin has paper/excel tab');
assert(adminJs.includes('/api/admin/paper') && adminJs.includes('VERSUS') && adminJs.includes('Totaal partijen'), 'admin exports dossier columns');
assert(guestHtmlSrc.includes('F+') && guestHtmlSrc.includes('dossier-card') && guestJs.includes('/api/public/dossier'), 'guest ranking and dossier match excel');
assert(guestHtmlSrc.includes('<select name="player1"') && guestHtmlSrc.includes('<select name="player2"'), 'guest picks players from dropdowns');
assert(!guestHtmlSrc.includes('<input name="player1"'), 'guest does not type player names');
assert(adminJs.includes('kpi-matches') && adminJs.includes('showTab'), 'admin dashboard has stats and tabs');
assert(embedJs.includes('data-snooker-live') && embedJs.includes('data-snooker-players') && embedJs.includes('data-snooker-agenda') && embedJs.includes('data-snooker-rapport'), 'embed has results, players, agenda and report boards');
assert(guestHtmlSrc.includes('Erelijst') && guestJs.includes('trophySvg'), 'guest shows ranking with trophies');
assert(guestHtmlSrc.includes('data-guest-tab="match"') && guestHtmlSrc.includes('data-step="1"'), 'guest has match tab and wizard');
assert(guestHtmlSrc.includes('data-guest-tab="club"') && guestHtmlSrc.includes('data-guest-tab="kpis"') && guestHtmlSrc.includes('data-guest-tab="agenda"'), 'guest has club, KPI and agenda tabs');
assert(guestHtmlSrc.includes('guest-agenda-grid') && guestHtmlSrc.includes('class="meter"'), 'guest shows agenda and KPI meters');
assert(guestHtmlSrc.includes('guest-agenda-detail') && guestJs.includes('data-agenda-day'), 'guest agenda opens day details');
assert(!guestHtmlSrc.includes('guest-agenda-list') && !guestJs.includes('guest-agenda-list'), 'guest does not dump the full upcoming list');
assert(guestHtmlSrc.includes('member-count') && guestHtmlSrc.includes('night-count'), 'guest shows member and club-night targets');
assert(adminHtmlSrc.includes('tab-agenda') && adminHtmlSrc.includes('kpi-month-bar') && adminHtmlSrc.includes('embed-agenda-iframe'), 'admin has agenda, meters and agenda embed');
assert(adminHtmlSrc.includes('id="live-frame"') && adminHtmlSrc.includes('id="players-frame"') && adminHtmlSrc.includes('id="agenda-frame"'), 'admin preview shows results, players and agenda iframes');
assert(adminJs.includes('playersUrl') && adminJs.includes('agendaUrl'), 'admin preview loads players and agenda urls');
assert(adminHtmlSrc.includes('goalNewMembersMonth') && adminHtmlSrc.includes('kpi-members'), 'admin can set new-member targets');
assert(!guestJs.includes('snooker_agenda') && !adminJs.includes('snooker_agenda'), 'does not use merode snooker_agenda');
const guestCss = fs.readFileSync(path.join(root, '../public/guest.css'), 'utf8');
const adminCss = fs.readFileSync(path.join(root, '../public/admin.css'), 'utf8');
assert(guestCss.includes('#f3f7f0') && guestCss.includes('#163524') && guestCss.includes('#2ea85a'), 'guest shares admin colors');
assert(guestCss.includes('[hidden]'), 'guest hidden attribute is not overridden by flex');
assert(adminCss.includes('#f3f7f0') && adminCss.includes('#163524'), 'admin keeps light dashboard tokens');
assert(guestHtmlSrc.includes('class="topbar"') && !guestHtmlSrc.includes('id="bg"'), 'guest uses admin-like topbar');
assert(normalizeTheme({ themePreset: 'midnight' }).bgSide === '#0b0e11', 'midnight preset sets sidebar');
assert(normalizeTheme({ accent: '#123456' }).themePreset === 'custom', 'legacy accent without preset stays custom');
assert(guestJs.includes('function setText') && adminJs.includes('function setText'), 'guest and admin guard missing nodes');
assert(adminHtmlSrc.includes('kpi-week') && adminHtmlSrc.includes('app-version'), 'admin dashboard has extra KPIs and version');
assert(adminJs.includes('renderAppInfo') && adminJs.includes('kpis'), 'admin renders version and KPI block');
assert(dockerfile.includes('package-wp-plugin.js'), 'docker image bakes the wordpress plugin zip');
assert(dockerfile.includes('HEALTHCHECK') && dockerfile.includes('org.opencontainers.image.version'), 'docker image is versioned and healthchecked');
assert(compose.includes('healthcheck:') && compose.includes('no-new-privileges'), 'compose is production-shaped');
assert(compose.includes('APP_VERSION: "1.0.2"') && compose.includes('webhost-snooker:1.0.2'), 'compose pins 1.0.2');
assert(dockerfile.includes('ARG APP_VERSION=1.0.2'), 'docker default version is 1.0.2');
assert(guestJs.includes('function applyLogo') && adminJs.includes('function applyLogo') && adminHtmlSrc.includes('id="side-club"'), 'logo and admin sidebar use the club brand');
assert(guestJs.includes('Winst / frames') && adminJs.includes('Hoogste break') && !guestJs.includes('TOTAL PLATEAU'), 'dossier cards are Dutch');
assert(fs.existsSync(path.join(root, '../.env.example')), 'env example documents host, path and data dir');
assert(!guestHtmlSrc.includes('name="handicap1"') && !guestHtmlSrc.includes('name="handicap2"'), 'guest form does not take manual handicap');
assert(guestHtmlSrc.includes('gemiddelde van de gespeelde frames'), 'guest explains computed handicap');
assert(DEFAULT_BRAND.clubName === 'SC De Merodesnookers' && DEFAULT_BRAND.venue.includes('Turnhout'), 'default brand is SC De Merodesnookers in Turnhout');
assert(normalizeBrand({ clubName: 'Tafels & Thee' }).clubName === 'Tafels & Thee', 'club name can be changed');
assert(normalizeBrand({ framesCount: 9 }).framesCount === 9, 'best of 9 is allowed');
assert(normalizeBrand({ frameFormat: 'fixed:3' }).frameMode === 'fixed' && normalizeBrand({ frameFormat: 'fixed:3' }).framesCount === 3, 'fixed poule frame format');
assert(parseFrameFormat('bestof:7').framesCount === 7, 'parseFrameFormat reads best of');
assert(adminJs.includes('hero-file') && adminJs.includes('/api/admin/hero'), 'admin can change hero image');
assert(adminHtmlSrc.includes('name="tournament"') && adminHtmlSrc.includes('event-tournaments'), 'agenda can link to a tornooi');
assert(dockerfile.includes('9091') && dockerfile.includes('/webhost/snooker'), 'docker listens on 9091');
assert(compose.includes('127.0.0.1:9091:9091'), 'compose binds loopback only');
assert(normalizeBasePath('webhost/snooker/') === '/webhost/snooker', 'base path');
assert(guestJs.includes('SNOOKER_NONCE') && adminJs.includes('SNOOKER_NONCE'), 'frontend can send a WordPress nonce');
const pluginMain = fs.readFileSync(path.join(PLUGIN_DIR, 'snookerclub.php'), 'utf8');
const pluginUpdater = fs.readFileSync(path.join(PLUGIN_DIR, 'includes/class-updater.php'), 'utf8');
const pluginApp = fs.readFileSync(path.join(PLUGIN_DIR, 'includes/class-plugin.php'), 'utf8');
assert(pluginMain.includes('Plugin Name: Snookerclub') && pluginHeaderVersion(pluginMain) === '1.0.2', 'wp plugin header version');
assert(pluginMain.includes('Update URI:'), 'wp plugin declares an Update URI');
assert(pluginUpdater.includes('pre_set_site_transient_update_plugins') && pluginUpdater.includes('auto_update_plugin'), 'wp plugin checks and auto-updates');
assert(pluginUpdater.includes('DEFAULT_FEED') && pluginUpdater.includes('sync_wp_auto_update_flag') && pluginUpdater.includes('https_url'), 'updater uses default https feed and syncs WP auto-update');
assert(pluginUpdater.includes('update_hosts') && pluginApp.includes('Nu op updates controleren'), 'updater registers Update URI hosts and has manual check');
const insecureFeed = pluginManifest({ origin: 'http://club.example', base: '/webhost/snooker', version: '1.0.2' });
assert(insecureFeed.package.startsWith('https://'), 'plugin feed package URL is always https');
assert(pluginApp.includes('add_shortcode') && pluginApp.includes('snookerclub_live') && pluginApp.includes('snookerclub_ranking') && pluginApp.includes('snookerclub_rapport'), 'wp plugin has shortcodes');
assert(fs.existsSync(path.join(PLUGIN_DIR, 'includes/class-templates.php')) && fs.readFileSync(path.join(PLUGIN_DIR, 'includes/class-templates.php'), 'utf8').includes('register_block_pattern'), 'wp plugin registers insertable patterns');
assert(fs.existsSync(path.join(root, '../public/board.css')) && fs.readFileSync(path.join(PLUGIN_DIR, 'includes/class-render.php'), 'utf8').includes('snooker-league'), 'wp plugin has league-table templates');
assert(pluginApp.includes('snookerclub_ingeven') && pluginApp.includes('snookerclub-ingeven'), 'wp plugin can enter matches on the host');
assert(pluginApp.includes('enqueue_guest_assets') && pluginApp.includes('enqueue_board_assets'), 'wp plugin enqueues guest and board assets');
assert(pluginApp.includes("'style' => 'snookerclub-board'") && pluginApp.includes('with_board_styles') && pluginApp.includes('content_has_board'), 'wp plugin registers board CSS and injects it on render');
const boardCss = fs.readFileSync(path.join(root, '../public/board.css'), 'utf8');
assert(boardCss.includes('.snooker-trophy') && boardCss.includes('inline-block') && boardCss.includes('.snooker-sheet-table') && boardCss.includes('@media print'), 'board css keeps trophies and printable sheets');
assert(boardCss.includes('body.snooker-printing') && boardCss.includes('.snooker-print-target'), 'board css prints only the report sheet');
assert(boardCss.includes('col-fplus') && boardCss.includes('.snooker-seat strong'), 'ranking keeps F+ and opaque podium names');
assert(boardCss.includes('@media (max-width: 720px)') && boardCss.includes('position: sticky'), 'board css has mobile sticky ranking column');
assert(fs.readFileSync(path.join(root, '../public/guest.css'), 'utf8').includes('@media (max-width: 480px)'), 'guest css has phone breakpoint');
assert(fs.readFileSync(path.join(PLUGIN_DIR, 'includes/class-render.php'), 'utf8').includes('col-fplus'), 'ranking shortcode marks F+ column');
assert(pluginApp.includes('showSignatures') && pluginApp.includes('frameFormat'), 'wp club settings control signatures and frame format');
assert(embedJs.includes('snookerPrint') && embedJs.includes('data-static') && embedJs.includes('afterprint'), 'embed isolates print and keeps SSR reports');
assert(fs.readFileSync(path.join(PLUGIN_DIR, 'blocks/board/block.json'), 'utf8').includes('"style": "snookerclub-board"'), 'block metadata links board.css');
assert(fs.readFileSync(path.join(PLUGIN_DIR, 'includes/class-theme.php'), 'utf8').includes('.snookerclub-board'), 'theme tokens apply to inserted boards');
assert(fs.readFileSync(path.join(PLUGIN_DIR, 'includes/class-rest.php'), 'utf8').includes('snookerclub/v1'), 'wp plugin exposes a WordPress REST API');
assert(pluginApp.includes('ensure_pages') && pluginApp.includes('snookerclub-embed--app'), 'wp plugin creates pages and renders inline');
assert(pluginApp.includes('maybe_upgrade') && pluginApp.includes('snookerclub_pretty_urls'), 'wp plugin upgrades and keeps pretty URLs optional');
assert(pluginApp.includes('snookerclub_brand_save') && pluginApp.includes('Clubnaam'), 'wp settings can change the club name');
assert(pluginApp.includes('sync_app_page_title'), 'wp page title follows the club name');
assert(!pluginApp.includes('iframe src=') && !fs.readFileSync(path.join(PLUGIN_DIR, 'blocks/board/editor.js'), 'utf8').includes('iframe'), 'wp plugin editor and shortcodes do not use iframes');
assert(guestHtmlSrc.includes('class="snooker-app"') && adminHtmlSrc.includes('snooker-app--admin'), 'frontend shells are scoped for WordPress embeds');
assert(pluginApp.includes('register_block') && pluginApp.includes('snookerclub_theme'), 'wp plugin has gutenberg block and customizer theme');
assert(fs.existsSync(path.join(PLUGIN_DIR, 'uninstall.php')) && fs.existsSync(path.join(PLUGIN_DIR, 'includes/class-theme.php')), 'wp plugin is uninstallable and themed');
assert(fs.existsSync(path.join(root, '../docs/INSTALL.md')) && fs.existsSync(path.join(root, '../docs/REGELS.md')), 'install and rules live in docs/');
assert(fs.readFileSync(path.join(PLUGIN_DIR, 'includes/class-theme.php'), 'utf8').includes('snookerclub_club_name'), 'customizer can set the club name');
assert(guestJs.includes('function apiUrl') && guestJs.includes('SNOOKER_REST') && guestJs.includes('ingeven'), 'guest posts to the WordPress REST API');
assert(embedJs.includes('SNOOKER_REST') && embedJs.includes('data-src'), 'embed uses REST or data-src');
assert(guestCss.includes('snookerclub-embed--ingeven'), 'guest css scopes the host wizard');
assert(guestHtmlSrc.includes('/webhost/snooker/theme.js') && guestJs.includes('SnookerTheme'), 'guest loads theme helper');
const feed = pluginManifest({ origin: 'https://club.example', base: '/webhost/snooker', version: '1.0.2' });
assert(feed.version === '1.0.2' && feed.package.endsWith('/plugin/snookerclub.zip'), 'update feed points at plugin zip');
assert(fs.existsSync(path.join(PLUGIN_DIR, 'includes/class-excel.php')), 'wp plugin has excel/paper stats');
const php = spawnSync('php', [path.join(root, '../wordpress/tests/store-check.php')], { encoding: 'utf8' });
assert(php.status === 0, `php store check: ${(php.stderr || php.stdout || '').trim()}`);
const phpRender = spawnSync('php', [path.join(root, '../wordpress/tests/render-check.php')], { encoding: 'utf8' });
assert(phpRender.status === 0, `php render check: ${(phpRender.stderr || phpRender.stdout || '').trim()}`);
const zipPath = buildPluginZip();
assert(zipPath === PUBLIC_ZIP && fs.existsSync(PUBLIC_ZIP) && fs.statSync(PUBLIC_ZIP).size > 1000, 'plugin zip lives in public/plugin');

const sample = normalizeMatch({
  tournament: 'Clubavond',
  date: '2026-09-12',
  player1: 'Anna',
  player2: 'Ben',
  frames: [{ p1: 72, p2: 21 }, { p1: 8, p2: 67 }, { p1: 80, p2: 12 }, { p1: 0, p2: 0 }, { p1: 0, p2: 0 }],
  break1: 56,
  break2: 32,
});
assert(frameWins(sample.frames).p1 === 2 && frameWins(sample.frames).p2 === 1, 'frame wins');
assert(sample.handicap1 === 53.3 && sample.handicap2 === 33.3, 'match handicap averages played frames');
assert(sample.framesPlayed === 3, 'unused 0-0 frames are ignored');
assert(matchAverages(sample.frames).handicap1 === 53.3, 'Anna frames 72/8/80 average 53.3');
assert(normalizeMatch({
  tournament: 'Clubavond',
  date: '2026-09-12',
  player1: 'Anna',
  player2: 'Ben',
  handicap1: 14,
  handicap2: 99,
  frames: sample.frames,
  break1: 56,
  break2: 32,
}).handicap1 === 53.3, 'manual handicap fields are ignored');
const extra = normalizeMatch({
  tournament: 'Clubavond',
  date: '2026-09-11',
  player1: 'Anna',
  player2: 'Chris',
  frames: [{ p1: 80, p2: 10 }, { p1: 70, p2: 20 }, { p1: 0, p2: 0 }, { p1: 0, p2: 0 }, { p1: 0, p2: 0 }],
  break1: 100,
  break2: 12,
  table: 'Baan 2',
  matchType: 'beker',
});
const ranked = rankPlayers([sample, extra]);
assert(ranked[0].name === 'Anna' && ranked[0].trophy === 'gold', 'leader gets gold trophy');
assert(ranked.find((p) => p.name === 'Anna')?.handicap === 62, 'Anna career handicap averages all played frames');
assert(ranked.find((p) => p.name === 'Ben')?.handicap === 33.3, 'Ben career handicap is 33.3');
assert(ranked.find((p) => p.name === 'Anna')?.framePct === 80 && ranked.find((p) => p.name === 'Anna')?.matchPct === 100, 'Anna excel match and frame percentages');
assert(clubSeason('2016-09-12') === '2016-2017' && clubSeason('2016-07-31') === '2015-2016', 'club season runs August–July');
const paper = normalizeMatch(paperInputToMatch({
  player1: 'Anna',
  versus: 'Ben',
  tournament: 'Snookertronooi 1',
  date: '2016-09-18',
  result: '2-1',
  breaks: 42,
  round: 'H kwart finale',
  season: '2016-2017',
}));
assert(paper.source === 'paper' && paper.season === '2016-2017' && paper.round === 'kwart finale', 'paper match keeps season and round');
assert(frameWins(paper.frames).p1 === 2 && frameWins(paper.frames).p2 === 1 && paper.handicap1 === 0, 'paper frames synthesize without point average');
const dossier = playerDossier([paper], 'Anna');
assert(dossier.rows[0].versus === 'Ben' && dossier.rows[0].roundLabel === 'H kwart finale' && dossier.career.winsMatches === 1, 'dossier row is from the player viewpoint');
const mixedRanked = rankPlayers([sample, extra, paper]);
const annaMixed = mixedRanked.find((p) => p.name === 'Anna');
const benMixed = mixedRanked.find((p) => p.name === 'Ben');
assert(annaMixed?.handicap === 62 && annaMixed?.avgPoints === 62, 'paper 1-0 frames do not dilute Anna point average');
assert(annaMixed?.framesFor === 6 && annaMixed?.wins === 3 && annaMixed?.losses === 0, 'Anna W/L and F+ count paper and signed matches');
assert(benMixed?.framesFor === 2 && benMixed?.losses === 2, 'Ben frames and losses count every result');
const emptyDossier = playerDossier([], '');
assert(emptyDossier.player === '' && emptyDossier.rows.length === 0, 'empty dossier name does not throw');
const unknownDossier = playerDossier([paper], 'Onbekende');
assert(unknownDossier.player === 'Onbekende' && unknownDossier.rows.length === 0, 'unknown player dossier is empty');
const career = playerCareer([
  { w: 1, l: 0, framesFor: 414, framesAgainst: 0, breaks: 105 },
  { w: 0, l: 1, framesFor: 0, framesAgainst: 117, breaks: 39 },
]);
assert(career.totalFrames === 531 && career.winsFrames === 414 && career.lossesFrames === 117, 'career plateau is frame wins plus losses');
assert(pct(184, 209) === 88.04 && pct(414, 531) === 77.97, 'snippet-2 match and frame percentages');
assert(career.highestBreak === 105 && career.gemBreak === 72, 'gem. break averages recorded breaks');
const parsedCsv = parsePaperCsv('VERSUS;TOURNAMENT;RESULT;W;L;BREAKS;ROUND;SEASON\nRoddy H;Podblack 1;2-1;1;0;29;H kwart finale;2016-2017', 'Steven');
assert(parsedCsv[0].versus === 'Roddy H' && parsedCsv[0].player === 'Steven' && parsedCsv[0].result === '2-1', 'paper csv parses excel headers');
assert(rankingCsv(ranked).includes('F+') && rankingCsv(ranked).includes('Gem. punten/frame'), 'ranking csv has excel columns');
assert(ranked[1].trophy === 'silver' && ranked[2].trophy === 'bronze', 'top 3 get trophies');
const summary = summarizeMatches([sample, extra]);
assert(summary.highestBreak.value === 100 && summary.centuries === 1, 'highest break and centuries');
assert(progressMeter(3, 12).pct === 25 && progressMeter(20, 10).pct === 100, 'KPI progress caps at 100');
assert(isoWeekStart('2026-09-12') === '2026-09-07', 'ISO week starts on Monday');
assert(normalizeEvent({ title: 'Les', date: '2026-09-15', start: '19:30:00', end: '21:00:00' }).start === '19:30', 'time inputs may send seconds');
const clubavond = normalizeEvent({ title: 'Clubavond', date: '2026-09-15', kind: 'clubavond', start: '19:30', end: '23:00' });
assert(normalizeEvent({ title: 'Kerst poule', date: '2026-12-20', kind: 'toernooi', tournament: 'Kersttornooi' }).tournament === 'Kersttornooi', 'agenda links to a tornooi');
assert(normalizeEvent({ title: 'Kerst poule', date: '2026-12-20', kind: 'toernooi', tournament: 'Kersttornooi' }).kindLabel === 'Tornooi', 'event kind label is Tornooi');
const pouleMatch = normalizeMatch({
  tournament: 'Kersttornooi',
  date: '2026-12-20',
  player1: 'Anna',
  player2: 'Ben',
  frameFormat: 'fixed:3',
  frames: [{ p1: 40, p2: 20 }, { p1: 10, p2: 55 }, { p1: 33, p2: 30 }],
  break1: 20,
  break2: 12,
});
assert(pouleMatch.frameMode === 'fixed' && pouleMatch.bestOf === 3 && pouleMatch.frames.length === 3, 'poule match stores fixed 3 frames');
const memberKpis = clubKpis([], [{ createdAt: '2026-09-12T10:00:00.000Z' }, { createdAt: '2026-08-01T10:00:00.000Z' }], [], { goalNewMembersMonth: 5, goalClubNightsMonth: 4 }, [clubavond], '2026-09-12');
assert(memberKpis.newMembersMonth === 1 && memberKpis.progress.members.goal === 5, 'counts new members this month');
assert(memberKpis.clubNightsMonth === 1 && memberKpis.clubNightsUpcoming === 1, 'counts club nights from agenda');
const agenda = buildMonthAgenda([clubavond], 2026, 9, '2026-09-12');
assert(agenda.weekdays[0] === 'ma' && agenda.days.find((day) => day.date === '2026-09-15')?.events[0].title === 'Clubavond', 'month agenda groups events');
assert(agenda.upcoming.length === 1 && agenda.label.toLowerCase().includes('september'), 'upcoming events stay after today');
try {
  normalizeEvent({ title: '', date: '2026-09-15' });
  assert(false, 'empty agenda title should throw');
} catch (err) {
  assert(err.code === 'INVALID', 'rejects empty agenda title');
}
assert(extra.table === 'Baan 2' && extra.matchType === 'beker', 'keeps match details');
try {
  normalizeMatch({
    tournament: 'Clubavond',
    date: '2026-09-12',
    player1: 'Anna',
    player2: 'Anna',
    frames: sample.frames,
    break1: 0,
    break2: 0,
  });
  assert(false, 'same player should throw');
} catch (err) {
  assert(err.code === 'INVALID' && /verschillende/.test(err.message), 'rejects identical players');
}

try {
  normalizeMatch({ ...sample, date: '12-09-2026' });
  assert(false, 'bad date should throw');
} catch (err) {
  assert(err.code === 'INVALID', 'rejects bad date');
}

const dataDir = fs.mkdtempSync(path.join(os.tmpdir(), 'snooker-'));
const app = createApp({ dataDir, basePath: '/webhost/snooker' });
const server = http.createServer(app);

function listen() {
  return new Promise((resolve) => {
    server.listen(0, '127.0.0.1', () => resolve(server.address().port));
  });
}

const port = await listen();
const origin = `http://127.0.0.1:${port}`;

try {
  const health = await fetch(`${origin}/webhost/snooker/health`);
  const healthJson = await health.json();
  assert(health.ok && healthJson.ok && healthJson.version, 'health reports version');
  const wpFeed = await fetch(`${origin}/webhost/snooker/api/public/wp-plugin`);
  const wpJson = await wpFeed.json();
  assert(wpFeed.ok && wpJson.version && String(wpJson.package || '').includes('/plugin/snookerclub.zip'), 'wp plugin update feed');
  const wpZip = await fetch(`${origin}/webhost/snooker/plugin/snookerclub.zip`);
  assert(wpZip.ok && (wpZip.headers.get('content-type') || '').includes('zip'), 'wp plugin zip download');
  const ready = await fetch(`${origin}/webhost/snooker/readyz`);
  assert(ready.ok && (await ready.json()).ready, 'readyz');

  const slash = await fetch(`${origin}/webhost/snooker`, { redirect: 'manual' });
  assert(slash.status === 308 && slash.headers.get('location')?.endsWith('/webhost/snooker/'), 'guest root keeps trailing slash');
  const guestPage = await fetch(`${origin}/webhost/snooker/`);
  const guestHtml = await guestPage.text();
  assert(guestPage.ok && guestHtml.includes('Speler 1'), 'guest page');
  assert(guestHtml.includes('/webhost/snooker/guest.css') && guestHtml.includes('/webhost/snooker/guest.js'), 'guest assets are rooted');
  const guestCss = await fetch(`${origin}/webhost/snooker/guest.css`);
  const guestJs = await fetch(`${origin}/webhost/snooker/guest.js`);
  assert(guestCss.ok && guestJs.ok, 'guest css/js are served');

  const unknown = await fetch(`${origin}/webhost/snooker/api/matches`, {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({
      tournament: 'Open',
      date: '2026-09-12',
      player1: 'Chris',
      player2: 'Dana',
      frames: [{ p1: 90, p2: 10 }, { p1: 12, p2: 70 }, { p1: 61, p2: 40 }, { p1: 0, p2: 0 }, { p1: 0, p2: 0 }],
      break1: 88,
      break2: 41,
    }),
  });
  assert(unknown.status === 400, 'guest cannot invent players');

  const headers = {
    'content-type': 'application/json',
    'x-webhost-user-id': '1',
    'x-webhost-user': 'clubbaas',
    'x-webhost-role': 'owner',
  };
  for (const name of ['Chris', 'Dana', 'Anna']) {
    const added = await fetch(`${origin}/webhost/snooker/api/admin/players`, {
      method: 'POST',
      headers,
      body: JSON.stringify({ name }),
    });
    assert(added.status === 201, `admin adds ${name}`);
  }
  const dup = await fetch(`${origin}/webhost/snooker/api/admin/players`, {
    method: 'POST',
    headers,
    body: JSON.stringify({ name: 'chris' }),
  });
  assert(dup.status === 400, 'duplicate player names are rejected');

  const sig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=';
  const unsigned = await fetch(`${origin}/webhost/snooker/api/matches`, {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({
      tournament: 'Open',
      date: '2026-09-12',
      player1: 'Chris',
      player2: 'Dana',
      frames: [{ p1: 90, p2: 10 }, { p1: 12, p2: 70 }, { p1: 61, p2: 40 }, { p1: 0, p2: 0 }, { p1: 0, p2: 0 }],
      break1: 88,
      break2: 41,
    }),
  });
  assert(unsigned.status === 400, 'guest must send both signatures');
  const samePlayer = await fetch(`${origin}/webhost/snooker/api/matches`, {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({
      tournament: 'Open',
      date: '2026-09-12',
      player1: 'Chris',
      player2: 'Chris',
      frames: [{ p1: 90, p2: 10 }, { p1: 12, p2: 70 }, { p1: 61, p2: 40 }, { p1: 0, p2: 0 }, { p1: 0, p2: 0 }],
      break1: 88,
      break2: 41,
      signature1: sig,
      signature2: sig,
    }),
  });
  assert(samePlayer.status === 400, 'guest cannot pick the same player twice');
  const created = await fetch(`${origin}/webhost/snooker/api/matches`, {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({
      tournament: 'Open',
      date: '2026-09-12',
      player1: 'Chris',
      player2: 'Dana',
      frames: [{ p1: 90, p2: 10 }, { p1: 12, p2: 70 }, { p1: 61, p2: 40 }, { p1: 0, p2: 0 }, { p1: 0, p2: 0 }],
      break1: 88,
      break2: 41,
      signature1: sig,
      signature2: sig,
    }),
  });
  assert(created.status === 201, 'guest can post without portal login');
  const createdMatch = await created.json();
  assert(createdMatch.match?.handicap1 === 54.3 && createdMatch.match?.handicap2 === 40, 'posted match handicap is frame average');
  assert(createdMatch.match?.signed && createdMatch.match?.signature1 && createdMatch.match?.signature2, 'posted match keeps both signatures');
  const overview = await fetch(`${origin}/webhost/snooker/api/public/overview`);
  const over = await overview.json();
  assert(over.highestBreak.value === 88 && over.highestBreak.player === 'Chris', 'overview highest break');
  assert(over.kpis?.framesPlayed === 3 && over.kpis.activePlayers === 2, 'dashboard KPIs count played frames');
  assert(over.players?.[0]?.trophy === 'gold', 'overview ranks players');
  assert(over.roster?.some((p) => p.name === 'Anna') && over.roster.length === 3, 'overview includes club roster');
  assert(over.players.some((p) => p.name === 'Anna' && p.played === 0), 'unused roster players stay on the list');
  const players = await (await fetch(`${origin}/webhost/snooker/api/public/players`)).json();
  assert(players.top3[0].name === 'Chris', 'public players list');
  const livePlayers = await fetch(`${origin}/webhost/snooker/live/players`);
  assert(livePlayers.ok && (await livePlayers.text()).includes('Spelerslijst'), 'players live page');
  const liveAgenda = await fetch(`${origin}/webhost/snooker/live/agenda`);
  assert(liveAgenda.ok && (await liveAgenda.text()).includes('Clubagenda'), 'agenda live page');

  const live = await fetch(`${origin}/webhost/snooker/api/public/live`);
  assert(live.headers.get('access-control-allow-origin') === '*', 'live has cors');
  assert((await live.json()).latest.player1 === 'Chris', 'live latest match');

  const denied = await fetch(`${origin}/webhost/snooker/api/admin/matches`);
  assert(denied.status === 401, 'admin denied without portal headers');

  const listed = await fetch(`${origin}/webhost/snooker/api/admin/matches`, {
    headers: { 'x-webhost-user-id': '1', 'x-webhost-user': 'clubbaas', 'x-webhost-role': 'owner' },
  });
  const listedJson = await listed.json();
  assert(listed.ok && listedJson.matches.length === 1, 'admin lists matches');
  assert(listedJson.matches[0].signed && listedJson.matches[0].signature1 && listedJson.matches[0].signature2, 'admin can see both signatures');
  const roster = await (await fetch(`${origin}/webhost/snooker/api/admin/players`, {
    headers: { 'x-webhost-user-id': '1', 'x-webhost-user': 'clubbaas', 'x-webhost-role': 'owner' },
  })).json();
  const chris = roster.players.find((p) => p.name === 'Chris');
  const blocked = await fetch(`${origin}/webhost/snooker/api/admin/players/${chris.id}`, {
    method: 'DELETE',
    headers: { 'x-webhost-user-id': '1', 'x-webhost-user': 'clubbaas', 'x-webhost-role': 'owner' },
  });
  assert(blocked.status === 400, 'cannot delete a player who has matches');

  const brand = await fetch(`${origin}/webhost/snooker/api/admin/brand`, {
    method: 'PUT',
    headers: {
      'content-type': 'application/json',
      'x-webhost-user-id': '1',
      'x-webhost-user': 'clubbaas',
      'x-webhost-role': 'owner',
    },
    body: JSON.stringify({ clubName: 'Tafels & Thee', accent: '#14532d' }),
  });
  assert((await brand.json()).clubName === 'Tafels & Thee', 'admin updates brand');

  const embed = await fetch(`${origin}/webhost/snooker/api/admin/embed`, {
    headers: { 'x-webhost-user-id': '1', 'x-webhost-user': 'clubbaas', host: 'club.example' },
  });
  const snippet = await embed.json();
  assert(snippet.iframe.includes('/webhost/snooker/live'), 'embed iframe');
  assert(snippet.script.includes('embed.js'), 'embed script');
  assert(snippet.playersIframe.includes('/live/players'), 'players embed iframe');
  assert(snippet.agendaIframe.includes('/live/agenda') && snippet.agendaScript.includes('data-snooker-agenda'), 'agenda embed iframe');

  const future = new Date();
  future.setUTCDate(future.getUTCDate() + 7);
  const futureDate = future.toISOString().slice(0, 10);
  const [futureYear, futureMonth] = futureDate.split('-');
  const eventRes = await fetch(`${origin}/webhost/snooker/api/admin/events`, {
    method: 'POST',
    headers,
    body: JSON.stringify({
      title: 'Clubavond',
      date: futureDate,
      kind: 'clubavond',
      start: '19:30',
      end: '23:00',
      place: 'Baan 1-4',
    }),
  });
  assert(eventRes.status === 201, 'admin creates agenda event');
  const createdEvent = await eventRes.json();
  const renamed = await fetch(`${origin}/webhost/snooker/api/admin/events/${createdEvent.event.id}`, {
    method: 'PUT',
    headers,
    body: JSON.stringify({
      title: 'Clubavond extra',
      date: futureDate,
      kind: 'clubavond',
      start: '19:30:00',
      end: '23:00:00',
      place: 'Baan 1-4',
    }),
  });
  assert(renamed.ok && (await renamed.json()).event.title === 'Clubavond extra', 'admin updates agenda event and accepts seconds');
  const publicAgenda = await fetch(`${origin}/webhost/snooker/api/public/agenda?year=${futureYear}&month=${Number(futureMonth)}`);
  const agendaJson = await publicAgenda.json();
  assert(publicAgenda.headers.get('access-control-allow-origin') === '*', 'agenda has cors');
  assert(agendaJson.agenda.events.some((event) => event.title === 'Clubavond extra' && event.date === futureDate), 'public agenda lists clubavond');
  const overLater = await (await fetch(`${origin}/webhost/snooker/api/public/overview`)).json();
  assert(overLater.kpis?.progress?.members && overLater.kpis.newMembersMonth >= 1, 'overview counts new members');
  assert(overLater.nextEventLabel && overLater.kpis.clubNightsUpcoming >= 1, 'overview includes next clubavond');
  const liveLater = await (await fetch(`${origin}/webhost/snooker/api/public/live`)).json();
  assert(liveLater.kpis?.progress?.nights && liveLater.nextEvent, 'live payload includes KPI progress and next event');
  assert(over.kpis?.progress?.break && Object.hasOwn(over.kpis.progress.break, 'pct'), 'overview KPIs include progress');

  const paperRes = await fetch(`${origin}/webhost/snooker/api/admin/matches`, {
    method: 'POST',
    headers,
    body: JSON.stringify({
      source: 'paper',
      player1: 'Chris',
      player2: 'Dana',
      tournament: 'Snookertronooi 1',
      date: '2016-09-18',
      framesFor: 2,
      framesAgainst: 1,
      break1: 49,
      round: 'kwart finale',
    }),
  });
  assert(paperRes.status === 201, 'admin can enter a paper score without signatures');
  const paperJson = await paperRes.json();
  assert(paperJson.match?.source === 'paper' && paperJson.match?.season === '2016-2017' && paperJson.wins?.p1 === 2, 'paper match stores season and frame result');
  const imported = await fetch(`${origin}/webhost/snooker/api/admin/paper`, {
    method: 'POST',
    headers,
    body: JSON.stringify({
      player: 'Chris',
      csv: 'VERSUS;TOURNAMENT;RESULT;W;L;BREAKS;ROUND;SEASON\nAnna;Podblack 2;1-0;1;0;27;H finale;2016-2017',
    }),
  });
  const importedJson = await imported.json();
  assert(imported.status === 201 && importedJson.count === 1, 'admin imports an excel paper row');
  const dossierRes = await fetch(`${origin}/webhost/snooker/api/public/dossier?player=Chris`);
  const dossierJson = await dossierRes.json();
  assert(dossierRes.ok && dossierJson.career?.winsMatches === 3 && dossierJson.career?.winsFrames === 5, 'public dossier counts W and F+ from all results');
  assert(dossierJson.career?.avgPoints === 54.33 && dossierJson.rows.some((row) => row.roundLabel.includes('finale')), 'public dossier gem. punten ignores paper frames');
  const emptyDossierRes = await fetch(`${origin}/webhost/snooker/api/public/dossier`);
  const emptyDossierJson = await emptyDossierRes.json();
  assert(emptyDossierRes.ok && Array.isArray(emptyDossierJson.rows) && emptyDossierJson.rows.length === 0, 'dossier without player is empty not an error');
  const ghostDossierRes = await fetch(`${origin}/webhost/snooker/api/public/dossier?player=Niemand`);
  const ghostDossierJson = await ghostDossierRes.json();
  assert(ghostDossierRes.ok && ghostDossierJson.player === 'Niemand' && ghostDossierJson.rows.length === 0, 'unknown player dossier is empty not an error');
  assert(dossierJson.headToHead.some((row) => row.versus === 'Dana' || row.versus === 'Anna'), 'dossier includes head-to-head');
  const h2hRes = await fetch(`${origin}/webhost/snooker/api/public/h2h?a=Chris&b=Dana`);
  const h2hJson = await h2hRes.json();
  assert(h2hRes.ok && h2hJson.wins + h2hJson.losses >= 1, 'head-to-head endpoint');
  const rapportRes = await fetch(`${origin}/webhost/snooker/api/public/rapport?season=2016-2017`);
  const rapportJson = await rapportRes.json();
  assert(rapportRes.ok && rapportJson.season === '2016-2017' && rapportJson.matchCount >= 2, 'public rapport filters season');
} catch (err) {
  failed += 1;
  console.error(`  FAIL http: ${err.message}`);
} finally {
  await new Promise((resolve) => server.close(resolve));
  fs.rmSync(dataDir, { recursive: true, force: true });
}

if (failed) {
  console.error(`snooker check FAILED with ${failed} problem(s).`);
  process.exit(1);
}
console.log('snooker check passed.');
