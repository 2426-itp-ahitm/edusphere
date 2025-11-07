<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $htlleo_db_tables;
global $wpdb;

$htlleo_db_tables = [
    'events' => $wpdb->prefix . 'htlleo_events',
    'sessions' => $wpdb->prefix . 'htlleo_sessions',
    'interests' => $wpdb->prefix . 'htlleo_interests',
    'event_interests' => $wpdb->prefix . 'htlleo_event_interests',
    'registrations' => $wpdb->prefix . 'htlleo_registrations',
];

function htlleo_get_table($key) {
    global $htlleo_db_tables;
    return $htlleo_db_tables[$key] ?? '';
}
