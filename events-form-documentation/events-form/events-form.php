<?php
/*
Plugin Name: Events Form for HTL Leonding
Description: Manage public events, sessions, interests, and registrations for HTL Leonding.
Version: 1.0
Author: Team Edusphere
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

require_once plugin_dir_path(__FILE__) . 'includes/db.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-menu.php';



// -------------------------
// Activation Hook: Create DB tables
// -------------------------
function htlleo_activate_plugin() {
    global $wpdb;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    $charset_collate = $wpdb->get_charset_collate();

    // Tables
    $tables = [];

    $tables[] = "CREATE TABLE {$wpdb->prefix}htlleo_events (
        id INT NOT NULL AUTO_INCREMENT,
        title VARCHAR(191) NOT NULL,
        description TEXT NOT NULL,
        event_date DATE NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    $tables[] = "CREATE TABLE {$wpdb->prefix}htlleo_sessions (
        id INT NOT NULL AUTO_INCREMENT,
        event_id INT NOT NULL,
        group_name VARCHAR(100) NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        capacity INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        FOREIGN KEY (event_id) REFERENCES {$wpdb->prefix}htlleo_events(id) ON DELETE CASCADE
    ) $charset_collate;";
    

    $tables[] = "CREATE TABLE {$wpdb->prefix}htlleo_interests (
        id INT NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    $tables[] = "CREATE TABLE {$wpdb->prefix}htlleo_event_interests (
        event_id INT NOT NULL,
        interest_id INT NOT NULL,
        PRIMARY KEY (event_id, interest_id),
        FOREIGN KEY (event_id) REFERENCES {$wpdb->prefix}htlleo_events(id) ON DELETE CASCADE,
        FOREIGN KEY (interest_id) REFERENCES {$wpdb->prefix}htlleo_interests(id) ON DELETE CASCADE
    ) $charset_collate;";

    $tables[] = "CREATE TABLE {$wpdb->prefix}htlleo_registrations (
        id INT NOT NULL AUTO_INCREMENT,
        session_id INT NOT NULL,
        preferred_interest_id INT DEFAULT NULL,
        gender VARCHAR(10),
        last_name VARCHAR(100),
        first_name VARCHAR(100),
        city VARCHAR(150),
        school VARCHAR(150),
        class VARCHAR(50),
        email VARCHAR(150),
        phone VARCHAR(50),
        status VARCHAR(20),
        registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        FOREIGN KEY (session_id) REFERENCES {$wpdb->prefix}htlleo_sessions(id) ON DELETE CASCADE,
        FOREIGN KEY (preferred_interest_id) REFERENCES {$wpdb->prefix}htlleo_interests(id) ON DELETE SET NULL
    ) $charset_collate;";

    // Run dbDelta on all tables
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
}
register_activation_hook(__FILE__, 'htlleo_activate_plugin');

// -------------------------
// Uninstall Hook: Remove DB tables
// -------------------------
function htlleo_uninstall_plugin() {
    global $wpdb;

    $tables = [
        "{$wpdb->prefix}htlleo_registrations",
        "{$wpdb->prefix}htlleo_event_interests",
        "{$wpdb->prefix}htlleo_sessions",
        "{$wpdb->prefix}htlleo_interests",
        "{$wpdb->prefix}htlleo_events"
    ];

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
}
register_uninstall_hook(__FILE__, 'htlleo_uninstall_plugin');

// -------------------------
// Enqueue styles and scripts
// -------------------------
function htlleo_admin_scripts($hook) {
    if ($hook !== 'toplevel_page_htlleo_events') return;

    wp_enqueue_script(
        'htlleo-inline-edit',
        plugin_dir_url(__FILE__) . 'assets/js/inline-edit.js',
        ['jquery'],
        '1.0',
        true
    );

    wp_localize_script('htlleo-inline-edit', 'htlleo_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('htlleo_inline_edit'),
    ]);
}
add_action('admin_enqueue_scripts', 'htlleo_admin_scripts');

add_action('wp_ajax_htlleo_add_session', function(){
    global $wpdb;
    $sessions_table = htlleo_get_table('sessions');

    check_ajax_referer('htlleo_inline_edit', 'nonce');

    $event_id = intval($_POST['event_id']);
    $group_name = sanitize_text_field($_POST['group_name']);
    $start_time = date('H:i:s', strtotime($_POST['start_time']));
    $end_time = date('H:i:s', strtotime($_POST['end_time']));
    $capacity = intval($_POST['capacity']);

    if(!$event_id || !$group_name || !$start_time || !$end_time || !$capacity){
        wp_send_json_error('All fields are required.');
    }

    $wpdb->insert($sessions_table, [
        'event_id' => $event_id,
        'group_name' => $group_name,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'capacity' => $capacity,
        'created_at' => current_time('mysql'),
    ]);

    $id = $wpdb->insert_id;

    wp_send_json_success([
        'id' => $id,
        'group_name' => $group_name,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'capacity' => $capacity
    ]);
});


