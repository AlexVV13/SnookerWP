<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('snookerclub_slug');
delete_option('snookerclub_update_url');
delete_option('snookerclub_auto_update');
delete_option('snookerclub_pretty_urls');
delete_option('snookerclub_state');
delete_option('snookerclub_hero');
delete_option('snookerclub_page_app');
delete_option('snookerclub_page_match');
delete_option('snookerclub_synced_page_title');
delete_option('snookerclub_plugin_version');
delete_site_transient('snookerclub_update_feed');
