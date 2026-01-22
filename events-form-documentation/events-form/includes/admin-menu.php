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


add_action('wp_ajax_htlleo_delete_registration', function(){
    if(!check_ajax_referer('htlleo_delete_registration', 'nonce', false)){
        wp_send_json_error('Invalid nonce');
    }

    global $wpdb;
    $registrations_table = htlleo_get_table('registrations');
    $id = intval($_POST['id']);

    if($wpdb->delete($registrations_table, ['id' => $id])){
        wp_send_json_success();
    } else {
        wp_send_json_error('Could not delete registration');
    }
});



// Add AJAX action
add_action('wp_ajax_htlleo_export_registrations', 'htlleo_export_registrations_callback');

function htlleo_export_registrations_callback() {
    if (!current_user_can('manage_options')) {
        wp_die('Zugriff verweigert.');
    }

    $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
    if (!wp_verify_nonce($nonce, 'htlleo_export_registrations')) {
        wp_die('Ungültiger Nonce.');
    }

    global $wpdb;

    $registrations_table = htlleo_get_table('registrations');
    $sessions_table      = htlleo_get_table('sessions');
    $events_table        = htlleo_get_table('events');
    $interests_table     = htlleo_get_table('interests');

    $rows = $wpdb->get_results("
        SELECT r.*, 
               s.group_name,
               e.title AS event_title,
               e.event_date,
               i.name AS interest_name
        FROM $registrations_table r
        JOIN $sessions_table s ON r.session_id = s.id
        JOIN $events_table e ON s.event_id = e.id
        LEFT JOIN $interests_table i ON i.id = r.preferred_interest_id
        ORDER BY e.event_date ASC, s.group_name ASC, r.last_name ASC
    ", ARRAY_A);

    // German column headers
    $headers = [
        'ID',
        'Veranstaltung',
        'Datum',
        'Gruppe',
        'Interesse',
        'Geschlecht',
        'Nachname',
        'Vorname',
        'Stadt',
        'Schule',
        'Klasse',
        'E-Mail',
        'Telefon',
        'Status',
        'Registriert am'
    ];

    // Try to load PhpSpreadsheet (composer)
    $autoload_paths = [
        __DIR__ . '/vendor/autoload.php',
        plugin_dir_path(__FILE__) . 'vendor/autoload.php',
        WP_CONTENT_DIR . '/vendor/autoload.php'
    ];
    $loaded = false;
    foreach ($autoload_paths as $p) {
        if (file_exists($p)) {
            require_once $p;
            $loaded = true;
            break;
        }
    }

    // If PhpSpreadsheet exists, generate XLSX, otherwise fallback to CSV
    if ($loaded && class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        // Fully-qualified class names to avoid use-statements inside function
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header row
        $col = 1;
        foreach ($headers as $h) {
            $sheet->setCellValueByColumnAndRow($col, 1, $h);
            $col++;
        }

        // Data rows
        $rowIndex = 2;
        foreach ($rows as $r) {
            $data = [
                $r['id'],
                $r['event_title'],
                $r['event_date'],
                $r['group_name'],
                $r['interest_name'],
                $r['gender'],
                $r['last_name'],
                $r['first_name'],
                $r['city'],
                $r['school'],
                $r['class'],
                $r['email'],
                $r['phone'],
                $r['status'],
                $r['registered_at']
            ];

            $col = 1;
            foreach ($data as $v) {
                // Avoid problems with null
                $sheet->setCellValueByColumnAndRow($col, $rowIndex, $v === null ? '' : $v);
                $col++;
            }
            $rowIndex++;
        }

        $filename = 'registrierungen_' . date('Y-m-d_H-i') . '.xlsx';

        // Send proper headers
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        // Flush output buffer if any
        if (ob_get_length()) ob_end_clean();
        $writer->save('php://output');
        exit;
    } else {
        // Fallback: CSV export (works without PhpSpreadsheet)
        $filename = 'registrierungen_' . date('Y-m-d_H-i') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        // Output BOM for Excel to detect UTF-8
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');

        // Write header (German)
        fputcsv($out, $headers, ';');

        foreach ($rows as $r) {
            $data = [
                $r['id'],
                $r['event_title'],
                $r['event_date'],
                $r['group_name'],
                $r['interest_name'],
                $r['gender'],
                $r['last_name'],
                $r['first_name'],
                $r['city'],
                $r['school'],
                $r['class'],
                $r['email'],
                $r['phone'],
                $r['status'],
                $r['registered_at']
            ];
            // fputcsv will accept arrays; use semicolon as delimiter for German Excel compatibility
            fputcsv($out, $data, ';');
        }

        fclose($out);
        exit;
    }
}
