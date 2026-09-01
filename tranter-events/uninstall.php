<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
// Keep event posts, taxonomies, metadata, and media references intact so replacing
// the plugin cannot erase events that staff already entered.
delete_option('tranter_events_settings');
delete_option('tranter_events_data_version');
