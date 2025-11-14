<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$sessions_table = htlleo_get_table('sessions');

$event_id = intval($event_id ?? 0); // event_id must be provided before including this file
if (!$event_id) return;

// Fetch sessions for this event
$sessions = $wpdb->get_results(
    $wpdb->prepare("SELECT * FROM $sessions_table WHERE event_id = %d ORDER BY start_time ASC", $event_id),
    ARRAY_A
);
?>

<table class="widefat striped sessions-table" data-event-id="<?php echo $event_id; ?>">
    <thead>
        <tr>
            <th>Group Name</th>
            <th>Start</th>
            <th>End</th>
            <th>Capacity</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($sessions): ?>
            <?php foreach ($sessions as $session): ?>
                <tr data-session-id="<?php echo $session['id']; ?>">
                    <td contenteditable="true" data-col="group_name"><?php echo esc_html($session['group_name']); ?></td>
                    <td contenteditable="true" data-col="start_time"><?php echo esc_html(date('H:i', strtotime($session['start_time']))); ?></td>
                    <td contenteditable="true" data-col="end_time"><?php echo esc_html(date('H:i', strtotime($session['end_time']))); ?></td>
                    <td contenteditable="true" data-col="capacity"><?php echo esc_html($session['capacity']); ?></td>
                    <td>
                        <button class="button remove-session-btn">Delete</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="5">No sessions yet.</td></tr>
        <?php endif; ?>
    </tbody>
    <tfoot>
        <tr class="add-session-row">
            <td><input type="text" class="new-group-name" placeholder="Group Name"></td>
            <td><input type="time" class="new-start" value="<?php echo date('H:i'); ?>"></td>
            <td><input type="time" class="new-end" value="<?php echo date('H:i', strtotime('+1 hour')); ?>"></td>
            <td><input type="number" class="new-capacity" min="1" placeholder="Capacity"></td>
            <td><button class="button add-session-btn">Add</button></td>
        </tr>
    </tfoot>
</table>

<script>
jQuery(document).ready(function($){
    const nonce = '<?php echo wp_create_nonce('htlleo_inline_edit'); ?>';
    const eventId = <?php echo $event_id; ?>;

    function formatForMySQL(datetime) {
        return datetime ? datetime.replace('T', ' ') + ':00' : '';
    }

    function formatForInput(mysqlDatetime) {
        return mysqlDatetime ? mysqlDatetime.slice(0,16).replace(' ', 'T') : '';
    }

    function bindSessionEvents() {
        // Inline edit
        $('.sessions-table td[contenteditable="true"]').off('blur').on('blur', function(){
            const td = $(this);
            let value = td.text().trim();
            const col = td.data('col');
            const session_id = td.closest('tr').data('session-id');

            if(col === 'start_time' || col === 'end_time'){
                value = formatForMySQL(value);
            }

            $.post(ajaxurl, {
                action: 'htlleo_update_session',
                nonce: nonce,
                id: session_id,
                col: col,
                value: value
            }, function(response){
                if(response.success){
                    td.css('background-color', '#d4edda');
                } else {
                    td.css('background-color', '#f8d7da');
                    alert(response.data);
                }
                setTimeout(() => td.css('background-color', ''), 500);
            });
        });

        // Delete session
        $('.remove-session-btn').off('click').on('click', function(){
            if(!confirm('Are you sure you want to delete this session?')) return;
            const row = $(this).closest('tr');
            const session_id = row.data('session-id');

            $.post(ajaxurl, {
                action: 'htlleo_delete_session',
                nonce: nonce,
                id: session_id
            }, function(response){
                if(response.success){
                    row.remove();
                } else {
                    alert(response.data);
                }
            });
        });
    }

    bindSessionEvents();

    // Add new session
    $('.add-session-btn').off('click').on('click', function(){
        const row = $(this).closest('tr');
        const table = $(this).closest('table');
        const eventId = table.data('event-id');
        const group_name = row.find('.new-group-name').val().trim();
        const start = formatForMySQL(row.find('.new-start').val());
        const end = formatForMySQL(row.find('.new-end').val());
        const capacity = row.find('.new-capacity').val();

        if(!group_name || !start || !end || !capacity){
            alert('Please fill in all fields.');
            return;
        }

        $.post(ajaxurl, {
            action: 'htlleo_add_session',
            nonce: nonce,
            event_id: eventId,
            group_name: group_name,
            start_time: start,
            end_time: end,
            capacity: capacity
        }, function(response){
            if(response.success){
                const s = response.data;
                const newRow = `<tr data-session-id="${s.id}">
                    <td contenteditable="true" data-col="group_name">${s.group_name}</td>
                    <td contenteditable="true" data-col="start_time">${formatForInput(s.start_time)}</td>
                    <td contenteditable="true" data-col="end_time">${formatForInput(s.end_time)}</td>
                    <td contenteditable="true" data-col="capacity">${s.capacity}</td>
                    <td><button class="button remove-session-btn">Delete</button></td>
                </tr>`;
                table.find('tbody').append(newRow);

                row.find('input').val('');
                bindSessionEvents();
            } else {
                alert(response.data);
            }
        });
    });

});
</script>
