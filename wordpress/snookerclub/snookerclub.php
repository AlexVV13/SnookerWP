<?php
/**
 * Plugin Name: Snookerclub
 * Plugin URI: https://alexvvught.com/
 * Description: Ranking, uitslagen, rapporten en invoer als invoegbare templates — in het WordPress-thema of als clubdashboard.
 * Version: 1.0.5
 * Requires at least: 6.2
 * Requires PHP: 8.1
 * Author: Alex van Vught
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: snookerclub
 * Update URI: https://alexvvught.com/webhost/snooker/api/public/wp-plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SNOOKERCLUB_VERSION', '1.0.5');
define('SNOOKERCLUB_FILE', __FILE__);
define('SNOOKERCLUB_DIR', plugin_dir_path(__FILE__));
define('SNOOKERCLUB_URL', plugin_dir_url(__FILE__));

require_once SNOOKERCLUB_DIR . 'includes/class-store.php';
require_once SNOOKERCLUB_DIR . 'includes/class-excel.php';
require_once SNOOKERCLUB_DIR . 'includes/class-theme.php';
require_once SNOOKERCLUB_DIR . 'includes/class-updater.php';
require_once SNOOKERCLUB_DIR . 'includes/class-rest.php';
require_once SNOOKERCLUB_DIR . 'includes/class-render.php';
require_once SNOOKERCLUB_DIR . 'includes/class-templates.php';
require_once SNOOKERCLUB_DIR . 'includes/class-plugin.php';

register_activation_hook(__FILE__, ['Snookerclub_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['Snookerclub_Plugin', 'deactivate']);

add_action('plugins_loaded', static function () {
    Snookerclub_Plugin::init();
    Snookerclub_Rest::init();
    Snookerclub_Updater::init();
    Snookerclub_Theme::init();
    Snookerclub_Templates::init();
});
