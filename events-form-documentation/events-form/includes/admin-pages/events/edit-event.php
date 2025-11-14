<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$events_table = htlleo_get_table('events');
$sessions_table = htlleo_get_table('sessions');

$event_id = intval($_GET['event_id'] ?? 0);
if (!$event_id) {
    echo '<div class="notice notice-error"><p>Invalid Event ID.</p></div>';
    return;
}

// Fetch event
$event = $wpdb->get_row($wpdb->prepare("SELECT * FROM $events_table WHERE id=%d", $event_id), ARRAY_A);

// Fetch sessions
$sessions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $sessions_table WHERE event_id=%d ORDER BY start_datetime ASC", $event_id), ARRAY_A);

// -------------------
// Handle Update Event
// -------------------
if (isset($_POST['htlleo_edit_event'])) {
    if (!isset($_POST['htlleo_event_nonce']) || !wp_verify_nonce($_POST['htlleo_event_nonce'], 'htlleo_edit_event')) {
        echo '<div class="notice notice-error is-dismissible"><p>Nonce verification failed!</p></div>';
    } else {
        $title = sanitize_text_field($_POST['event_title']);
        $description = sanitize_textarea_field($_POST['event_description']);

        // Update event
        $wpdb->update($events_table, [
            'title' => $title,
            'description' => $description,
        ], ['id' => $event_id]);

        // Handle session updates
        if (!empty($_POST['sessions']) && is_array($_POST['sessions'])) {
            foreach ($_POST['sessions'] as $sid => $session) {
                $group_name = sanitize_text_field($session['group_name']);
                $start = sanitize_text_field($session['start_datetime']);
                $end = sanitize_text_field($session['end_datetime']);
                $capacity = intval($session['capacity']);

                // If session id exists, update
                if (isset($session['id']) && $session['id'] > 0) {
                    $wpdb->update($sessions_table, [
                        'group_name' => $group_name,
                        'start_datetime' => $start,
                        'end_datetime' => $end,
                        'capacity' => $capacity,
                    ], ['id' => intval($session['id'])]);
                } else {
                    // New session
                    $wpdb->insert($sessions_table, [
                        'event_id' => $event_id,
                        'group_name' => $group_name,
                        'start_datetime' => $start,
                        'end_datetime' => $end,
                        'capacity' => $capacity,
                        'created_at' => current_time('mysql'),
                    ]);
                }
            }
        }

        echo '<div class="notice notice-success is-dismissible"><p>Event and sessions updated!</p></div>';

        // Reload updated data
        $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM $events_table WHERE id=%d", $event_id), ARRAY_A);
        $sessions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $sessions_table WHERE event_id=%d ORDER BY start_datetime ASC", $event_id), ARRAY_A);
    }
}
?>

<div class="wrap">
    <h2>Edit Event</h2>
    <form method="post">
        <?php wp_nonce_field('htlleo_edit_event', 'htlleo_event_nonce'); ?>

        <table class="form-table">
            <tr>
                <th>Title</th>
                <td><input type="text" name="event_title" value="<?php echo esc_attr($event['title']); ?>" required></td>
            </tr>
            <tr>
                <th>Description</th>
                <td><textarea name="event_description" required><?php echo esc_textarea($event['description']); ?></textarea></td>
            </tr>
        </table>

        <h3>Sessions</h3>
        <table id="sessions-table" class="widefat striped">
            <thead>
                <tr>
                    <th>Group Name</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Capacity</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $session): ?>
                <tr>
                    <td>
                        <input type="text" name="sessions[<?php echo $session['id']; ?>][group_name]" value="<?php echo esc_attr($session['group_name']); ?>" required>
                        <input type="hidden" name="sessions[<?php echo $session['id']; ?>][id]" value="<?php echo $session['id']; ?>">
                    </td>
                    <td><input type="datetime-local" name="sessions[<?php echo $session['id']; ?>][start_datetime]" value="<?php echo esc_attr(date('Y-m-d\TH:i', strtotime($session['start_datetime']))); ?>" required></td>
                    <td><input type="datetime-local" name="sessions[<?php echo $session['id']; ?>][end_datetime]" value="<?php echo esc_attr(date('Y-m-d\TH:i', strtotime($session['end_datetime']))); ?>" required></td>
                    <td><input type="number" name="sessions[<?php echo $session['id']; ?>][capacity]" value="<?php echo esc_attr($session['capacity']); ?>" min="1" required></td>
                    <td><button type="button" class="button remove-session-btn">Remove</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5">
                        <button type="button" class="button add-session-btn">Add Session</button>
                    </td>
                </tr>
            </tfoot>
        </table>

        <br>
        <input type="submit" class="button button-primary" value="Update Event" name="htlleo_edit_event">
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($){
    $('.add-session-btn').on('click', function(){
        var row = `<tr>
            <td><input type="text" name="sessions[][group_name]" required></td>
            <td><input type="datetime-local" name="sessions[][start_datetime]" required></td>
            <td><input type="datetime-local" name="sessions[][end_datetime]" required></td>
            <td><input type="number" name="sessions[][capacity]" min="1" required></td>
            <td><button type="button" class="button remove-session-btn">Remove</button></td>
        </tr>`;
        $('#sessions-table tbody').append(row);
    });

    $(document).on('click', '.remove-session-btn', function(){
        $(this).closest('tr').remove();
    });
});
</script>
