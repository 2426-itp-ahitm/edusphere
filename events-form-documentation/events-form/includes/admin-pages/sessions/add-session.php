<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$sessions_table = htlleo_get_table('sessions');

if (isset($_POST['htlleo_add_session'])) {
    $event_id = intval($_POST['event_id']);
    $group_name = sanitize_text_field($_POST['group_name']);
    $start = sanitize_text_field($_POST['start_time']);
    $end = sanitize_text_field($_POST['end_time']);
    $capacity = intval($_POST['capacity']);

    $wpdb->insert($sessions_table, [
        'event_id' => $event_id,
        'group_name' => $group_name,
        'start_datetime' => $start,
        'end_datetime' => $end,
        'capacity' => $capacity,
        'created_at' => current_time('mysql'),
    ]);

    echo '<div class="notice notice-success is-dismissible"><p>Session added!</p></div>';
}
