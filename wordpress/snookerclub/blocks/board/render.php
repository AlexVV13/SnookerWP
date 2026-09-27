<?php
if (!defined('ABSPATH')) {
    exit;
}
$view = $attributes['view'] ?? 'ranking';
$opts = [
    'view' => $view,
    'skin' => $attributes['skin'] ?? 'site',
    'limit' => (int) ($attributes['limit'] ?? 0),
    'player' => sanitize_text_field((string) ($attributes['player'] ?? ($_GET['player'] ?? ''))),
    'season' => sanitize_text_field((string) ($attributes['season'] ?? ($_GET['season'] ?? ''))),
    'a' => sanitize_text_field((string) ($attributes['a'] ?? ($_GET['a'] ?? ''))),
    'b' => sanitize_text_field((string) ($attributes['b'] ?? ($_GET['b'] ?? ''))),
];
if ($view === 'ingeven' || $view === 'match') {
    echo Snookerclub_Plugin::shortcode_ingeven();
    return;
}
if ($view === 'app') {
    echo Snookerclub_Plugin::shortcode_guest();
    return;
}
echo Snookerclub_Plugin::render_board($view, $opts);
