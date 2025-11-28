<?php 

if (!defined('ABSPATH')) exit;

function htlleo_admin_menu() {
    add_menu_page(
        'HTL Leonding Events',
        'HTL Events',
        'manage_options',
        'htlleo_events',
        'htlleo_admin_events_page',
        'dashicons-calendar',
        5
    );

    add_submenu_page(
        'htlleo_events',
        'Interests',
        'Interests',
        'manage_options',
        'htlleo_interests',
        'htlleo_admin_interests_page'
    );

    add_submenu_page(
        'htlleo_events',
        'Registrations',
        'Registrations',
        'manage_options',
        'htlleo_registrations',
        'htlleo_admin_registrations_page'
    );
}
add_action('admin_menu', 'htlleo_admin_menu');

// Callback functions
function htlleo_admin_events_page() {
    require plugin_dir_path(__FILE__) . 'admin-pages/events/events.php';
}

function htlleo_admin_interests_page() {
    require plugin_dir_path(__FILE__) . 'admin-pages/interests/interests.php';
}

function htlleo_admin_registrations_page() {
    require plugin_dir_path(__FILE__) . 'admin-pages/registrations/registrations.php';
}



// Update Event Inline
add_action('wp_ajax_htlleo_update_event', function() {
    global $wpdb;
    $events_table = htlleo_get_table('events');

    check_ajax_referer('htlleo_inline_edit', 'nonce');

    $id    = intval($_POST['id']);
    $col   = sanitize_text_field($_POST['col']);
    $value = sanitize_text_field($_POST['value']);

    $allowed_cols = ['title', 'description', 'event_date'];
    if (!in_array($col, $allowed_cols)) {
        wp_send_json_error('Invalid column');
    }

    error_log('Updating event: ' . print_r($_POST, true));

    $updated = $wpdb->update(
        $events_table,
        [$col => $value],
        ['id' => $id],
        ['%s'],
        ['%d']
    );
    
    if ($updated === false) {
        error_log('DB update failed: ' . $wpdb->last_error);
        wp_send_json_error('Database update failed: ' . $wpdb->last_error);
    }
    
    wp_send_json_success(['updated' => $updated, 'col' => $col, 'value' => $value]);
});
add_action('wp_ajax_htlleo_update_event_interests', function() {
    global $wpdb;
    $event_interests_table = htlleo_get_table('event_interests');

    check_ajax_referer('htlleo_inline_edit', 'nonce');

    $event_id = intval($_POST['event_id']);
    $interests = array_map('intval', $_POST['interests'] ?? []);

    // Remove all existing interests
    $wpdb->delete($event_interests_table, ['event_id' => $event_id]);

    // Insert new ones
    foreach($interests as $interest_id){
        $wpdb->insert($event_interests_table, [
            'event_id' => $event_id,
            'interest_id' => $interest_id
        ]);
    }

    wp_send_json_success(['event_id' => $event_id, 'interests' => $interests]);
});


// Update Session Inline
add_action('wp_ajax_htlleo_update_session', function() {
    global $wpdb;
    $sessions_table = htlleo_get_table('sessions');

    check_ajax_referer('htlleo_inline_edit', 'nonce');

    $id    = intval($_POST['id']);
    $col   = sanitize_text_field($_POST['col']);
    $value = sanitize_text_field($_POST['value']);

    $allowed_cols = ['group_name', 'start_time', 'end_time', 'capacity'];
    if (!in_array($col, $allowed_cols)) {
        wp_send_json_error('Invalid column');
    }

    // Convert datetime to MySQL format if necessary
    if (in_array($col, ['start_time', 'end_time'])) {
        $value = date('H:i:s', strtotime($value));
    }

    $format = $col === 'capacity' ? '%d' : '%s';

    $updated = $wpdb->update(
        $sessions_table,
        [$col => $value],
        ['id' => $id],
        [$format],
        ['%d']
    );

    if ($updated !== false) wp_send_json_success();
    else wp_send_json_error('Database update failed');
});

// Delete Session
add_action('wp_ajax_htlleo_delete_session', function() {
    global $wpdb;
    $sessions_table = htlleo_get_table('sessions');

    check_ajax_referer('htlleo_inline_edit', 'nonce');

    $id = intval($_POST['id']);
    $deleted = $wpdb->delete($sessions_table, ['id' => $id]);

    if ($deleted !== false) wp_send_json_success();
    else wp_send_json_error('Database delete failed');
});

// Add Session
add_action('wp_ajax_htlleo_add_session', function(){
    global $wpdb;
    $sessions_table = htlleo_get_table('sessions');

    check_ajax_referer('htlleo_inline_edit', 'nonce');

    $event_id = intval($_POST['event_id']);
    $group_name = sanitize_text_field($_POST['group_name']);
    $start = sanitize_text_field($_POST['start_time']);
    $end = sanitize_text_field($_POST['end_time']);
    $capacity = intval($_POST['capacity']);

    if(!$event_id || !$group_name || !$start || !$end || !$capacity){
        wp_send_json_error('All fields are required.');
    }

    // Convert datetime-local to MySQL format
    $start = date('H:i:s', strtotime($start));
    $end = date('H:i:s', strtotime($end));

    $wpdb->insert($sessions_table, [
        'event_id' => $event_id,
        'group_name' => $group_name,
        'start_time' => $start,
        'end_time' => $end,
        'capacity' => $capacity,
        'created_at' => current_time('mysql'),
    ]);

    $id = $wpdb->insert_id;

    wp_send_json_success([
        'id' => $id,
        'group_name' => $group_name,
        'start_datetime' => $start,
        'end_datetime' => $end,
        'capacity' => $capacity
    ]);
});

// Update Interest Name Inline
add_action('wp_ajax_htlleo_update_interest', function(){
    global $wpdb;
    $interests_table = htlleo_get_table('interests');

    check_ajax_referer('htlleo_inline_edit', 'nonce');

    $id = intval($_POST['id']);
    $value = sanitize_text_field($_POST['value']);

    if (!$value) wp_send_json_error('Value cannot be empty');

    $updated = $wpdb->update($interests_table, ['name' => $value], ['id' => $id]);

    if($updated !== false) wp_send_json_success();
    else wp_send_json_error('Database update failed');
});

// Delete Interest
add_action('wp_ajax_htlleo_delete_interest', function(){
    global $wpdb;
    $interests_table = htlleo_get_table('interests');

    check_ajax_referer('htlleo_inline_edit', 'nonce');

    $id = intval($_POST['id']);
    $deleted = $wpdb->delete($interests_table, ['id' => $id]);

    if($deleted !== false) wp_send_json_success();
    else wp_send_json_error('Database delete failed');
});


// Update Registration Inline
add_action('wp_ajax_htlleo_update_registration', function() {
    global $wpdb;

    check_ajax_referer('htlleo_edit_registration', 'nonce');

    $registrations_table = htlleo_get_table('registrations');

    $id    = intval($_POST['id']);
    $col   = sanitize_key($_POST['col']);
    $value = sanitize_text_field($_POST['value']);

    $allowed_cols = [
        'session_id','preferred_interest_id','gender','last_name','first_name',
        'city','school','class','email','phone','status'
    ];

    if (!in_array($col, $allowed_cols)) {
        wp_send_json_error("Invalid column");
    }

    $updated = $wpdb->update(
        $registrations_table,
        [$col => $value],
        ['id' => $id],
        ['%s'],
        ['%d']
    );

    if ($updated === false) {
        wp_send_json_error("DB error: " . $wpdb->last_error);
    }

    wp_send_json_success("Updated");
});
