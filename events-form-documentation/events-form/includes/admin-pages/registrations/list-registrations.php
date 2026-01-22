<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

$registrations_table = htlleo_get_table('registrations');
$sessions_table      = htlleo_get_table('sessions');
$interests_table     = htlleo_get_table('interests');
$events_table        = htlleo_get_table('events');

// Use WP timezone-aware date
$today = current_time('Y-m-d');

// Fetch all interests
$interests = $wpdb->get_results("SELECT id, name FROM $interests_table ORDER BY name ASC", ARRAY_A);
$interests_map = array_column($interests, 'name', 'id');

// Fetch all sessions (for mapping current session IDs)
$all_sessions = $wpdb->get_results("SELECT s.id, s.group_name, e.title AS event_title, e.event_date
    FROM {$sessions_table} s
    JOIN {$events_table} e ON s.event_id=e.id
    ORDER BY e.event_date ASC, s.group_name ASC", ARRAY_A);
$all_sessions_map = [];
foreach ($all_sessions as $s) {
    $all_sessions_map[$s['id']] = [
        'group_name' => $s['group_name'],
        'event_title' => $s['event_title'],
        'event_date' => $s['event_date']
    ];
}

// Fetch upcoming events and sessions (for dropdown editing)
$upcoming_rows = $wpdb->get_results($wpdb->prepare("
    SELECT e.id AS event_id, e.title AS event_title, e.event_date, s.id AS session_id, s.group_name
    FROM {$events_table} e
    JOIN {$sessions_table} s ON s.event_id = e.id
    WHERE e.event_date >= %s
    ORDER BY e.event_date ASC, e.title ASC, s.group_name ASC
", $today), ARRAY_A);

$upcoming_events = [];
foreach ($upcoming_rows as $r) {
    $eid = (int)$r['event_id'];
    if (!isset($upcoming_events[$eid])) {
        $upcoming_events[$eid] = [
            'title' => $r['event_title'],
            'date' => $r['event_date'],
            'sessions' => []
        ];
    }
    $upcoming_events[$eid]['sessions'][] = [
        'id' => (int)$r['session_id'],
        'group_name' => $r['group_name']
    ];
}

// Fetch all registrations
$registrations = $wpdb->get_results("
    SELECT r.*, s.group_name, s.event_id, e.title AS event_title, e.event_date
    FROM $registrations_table r
    JOIN $sessions_table s ON r.session_id = s.id
    JOIN $events_table e ON s.event_id = e.id
    ORDER BY 
        CASE WHEN e.event_date >= '$today' THEN 0 ELSE 1 END,  -- upcoming first
        e.event_date ASC,  -- nearest upcoming event first
        s.group_name ASC,  -- session name
        r.registered_at DESC
", ARRAY_A);

// Gender options
$genders = ['male' => 'Male', 'female' => 'Female', 'diverse' => 'Diverse', 'none' => '—'];
?>

<div class="wrap">
    <h1>Registrations</h1>

    <p>
        <a href="<?php echo admin_url('admin-ajax.php?action=htlleo_export_registrations&_wpnonce='.wp_create_nonce('htlleo_export_registrations')); ?>" 
        class="button button-primary">
        Exportieren als Excel
        </a>
    </p>


    <div style="margin-bottom: 1rem;">
        <input type="text" id="htlleo-search-input" placeholder="Search users..." style="padding:5px; width: 200px; margin-right:10px;">
        <select id="htlleo-event-filter" style="padding:5px;">
            <option value="">— All Events —</option>
            <?php
            // Event filter dropdown
            $events = $wpdb->get_results("SELECT id, title, event_date FROM $events_table ORDER BY event_date DESC", ARRAY_A);
            foreach ($events as $e) {
                echo '<option value="'.esc_attr($e['id']).'">'.esc_html($e['title'].' ('.$e['event_date'].')').'</option>';
            }
            ?>
        </select>
    </div>

    <?php if ($registrations): ?>
        <div id="htlleo-registrations-table-wrapper" style="max-height:600px; overflow:auto;">
            <table id="htlleo-registrations-table" class="widefat striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Session</th>
                        <th>Interest</th>
                        <th>Gender</th>
                        <th>Last</th>
                        <th>First</th>
                        <th>City</th>
                        <th>School</th>
                        <th>Class</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registrations as $reg): ?>
                        <tr data-event-id="<?php echo esc_attr($reg['event_id']); ?>">
                            
                            <td><?php echo $reg['id']; ?></td>

                            <!-- Session dropdown -->
                            <td>
                                <select class="htlleo-edit-select" data-id="<?php echo $reg['id']; ?>" data-col="session_id" style="max-width:200px;" title="<?php echo esc_attr($reg['group_name'].' — '.$reg['event_title'].' ('.$reg['event_date'].')'); ?>">
                                    <?php
                                    $current_session_id = $reg['session_id'];
                                    // Truncate display to 25 chars max
                                    $display_text = mb_strlen($reg['group_name'].' — '.$reg['event_title'].' ('.$reg['event_date'].')') > 25
                                        ? mb_substr($reg['group_name'].' — '.$reg['event_title'].' ('.$reg['event_date'].')', 0, 25).'…'
                                        : $reg['group_name'].' — '.$reg['event_title'].' ('.$reg['event_date'].')';

                                    echo '<option value="'.esc_attr($current_session_id).'" selected>'.esc_html($display_text).'</option>';

                                    foreach ($upcoming_events as $edata) {
                                        echo '<optgroup label="'.esc_attr($edata['title'].' — '.$edata['date']).'">';
                                        foreach ($edata['sessions'] as $s) {
                                            if ($s['id'] == $current_session_id) continue;
                                            echo '<option value="'.esc_attr($s['id']).'">'.esc_html($s['group_name']).'</option>';
                                        }
                                        echo '</optgroup>';
                                    }
                                    ?>
                                </select>
                            </td>


                            <!-- Interest dropdown -->
                            <td>
                                <select class="htlleo-edit-select" data-id="<?php echo $reg['id']; ?>" data-col="preferred_interest_id">
                                    <option value="">—</option>
                                    <?php foreach ($interests as $i): ?>
                                        <option value="<?php echo $i['id']; ?>" <?php selected($reg['preferred_interest_id'], $i['id']); ?>>
                                            <?php echo esc_html($i['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>

                            <!-- Gender dropdown -->
                            <td>
                                <select class="htlleo-edit-select" data-id="<?php echo $reg['id']; ?>" data-col="gender">
                                    <?php foreach ($genders as $val => $label): ?>
                                        <option value="<?php echo esc_attr($val); ?>" <?php selected($reg['gender'], $val); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>

                            <!-- Other editable fields -->
                            <?php
                            $editable_fields = ['last_name','first_name','city','school','class','email','phone','status'];
                            foreach ($editable_fields as $field):
                            ?>
                                <td contenteditable="true" data-id="<?php echo $reg['id']; ?>" data-col="<?php echo $field; ?>">
                                    <?php echo esc_html($reg[$field]); ?>
                                </td>
                            <?php endforeach; ?>

                            <td><?php echo esc_html($reg['registered_at']); ?></td>

                            <!-- Delete button -->
                            <td>
                                <button class="htlleo-delete-btn button button-secondary" data-id="<?php echo $reg['id']; ?>">Delete</button>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p>No registrations found.</p>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($){
    // Save TEXT edits
    $('#htlleo-registrations-table td[contenteditable="true"]').on('blur', function(){
        let cell = $(this), id = cell.data('id'), col = cell.data('col'), val = cell.text().trim();
        if (!id || !col) return;
        $.post(ajaxurl, {
            action:'htlleo_update_registration',
            nonce:'<?php echo wp_create_nonce("htlleo_edit_registration"); ?>',
            id:id,
            col:col,
            value:val
        }, function(resp){
            if(resp.success){ cell.css('background','#d4edda'); setTimeout(()=>cell.css('background',''),500); }
            else { alert("Update failed: "+resp.data); cell.css('background','#f8d7da'); }
        });
    });

    // Save DROPDOWN edits
    $('.htlleo-edit-select').on('change', function(){
        let select = $(this), id = select.data('id'), col = select.data('col'), val = select.val();
        $.post(ajaxurl, {
            action:'htlleo_update_registration',
            nonce:'<?php echo wp_create_nonce("htlleo_edit_registration"); ?>',
            id:id,
            col:col,
            value:val
        }, function(resp){
            if(resp.success){ select.css('background','#d4edda'); setTimeout(()=>select.css('background',''),500); }
            else { alert("Update failed: "+resp.data); select.css('background','#f8d7da'); }
        });
    });

    // Filter by first name, last name, and event
    function filterRegistrations() {
        let search = $('#htlleo-search-input').val().toLowerCase();
        let eventId = $('#htlleo-event-filter').val();

        $('#htlleo-registrations-table tbody tr').each(function(){
            let row = $(this);
            let firstName = row.find('td[data-col="first_name"]').text().toLowerCase();
            let lastName  = row.find('td[data-col="last_name"]').text().toLowerCase();
            let textMatch = firstName.includes(search) || lastName.includes(search);

            let eventMatch = true;
            if(eventId) {
                let rowEventId = row.data('event-id');
                eventMatch = rowEventId == eventId;
            }

            if(textMatch && eventMatch) row.show();
            else row.hide();
        });
    }

    $('#htlleo-search-input').on('input', filterRegistrations);
    $('#htlleo-event-filter').on('change', filterRegistrations);

    // Delete registration
    $('.htlleo-delete-btn').on('click', function(){
        if(!confirm('Are you sure you want to delete this registration?')) return;
        let btn = $(this);
        let regId = btn.data('id');

        $.post(ajaxurl, {
            action: 'htlleo_delete_registration',
            nonce: '<?php echo wp_create_nonce("htlleo_delete_registration"); ?>',
            id: regId
        }, function(resp){
            if(resp.success){
                btn.closest('tr').fadeOut(300, function(){ $(this).remove(); });
            } else {
                alert('Delete failed: ' + resp.data);
            }
        });
    });
});
</script>
