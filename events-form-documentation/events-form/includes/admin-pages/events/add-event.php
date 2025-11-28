<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$events_table = htlleo_get_table('events');
$sessions_table = htlleo_get_table('sessions');
$interests_table = htlleo_get_table('interests');

$interests = $wpdb->get_results("SELECT * FROM $interests_table ORDER BY id ASC");

// -----------------------------
// Handle Add Event
// -----------------------------
if (isset($_POST['htlleo_add_event'])) {
    if (!isset($_POST['htlleo_event_nonce']) || !wp_verify_nonce($_POST['htlleo_event_nonce'], 'htlleo_add_event')) {
        echo '<div class="notice notice-error is-dismissible"><p>Nonce verification failed!</p></div>';
    } else {
        $title = sanitize_text_field($_POST['event_title']);
        $description = sanitize_textarea_field($_POST['event_description']);
        $event_date = date('Y-m-d', strtotime($_POST['event_date']));

        $wpdb->insert($events_table, [
            'title' => $title,
            'description' => $description,
            'event_date' => $event_date,
            'created_at' => current_time('mysql'),
        ]);

        if ($wpdb->last_error) {
            die('DB Error: ' . $wpdb->last_error);
        } else {
            echo 'Insert succeeded! Insert ID: ' . $wpdb->insert_id;
        }

        $event_id = $wpdb->insert_id;

        // Insert sessions
        if (!empty($_POST['sessions']) && is_array($_POST['sessions'])) {
            foreach ($_POST['sessions'] as $session) {
                $group_name = sanitize_text_field($session['group_name']);
                $start_time = date('H:i:s', strtotime($session['start_time']));
                $end_time = date('H:i:s', strtotime($session['end_time']));
                $capacity = intval($session['capacity']);

                if ($group_name && $start_time && $end_time && $capacity > 0) {
                    $wpdb->insert($sessions_table, [
                        'event_id' => $event_id,
                        'group_name' => $group_name,
                        'start_time' => $start_time,
                        'end_time' => $end_time,
                        'capacity' => $capacity,
                        'created_at' => current_time('mysql'),
                    ]);
                }
            }
        }

        // Event Interests
        $event_interests_table = htlleo_get_table('event_interests');
        if (!empty($_POST['event_interests']) && is_array($_POST['event_interests'])) {
            foreach ($_POST['event_interests'] as $interest_id) {
                $wpdb->insert($event_interests_table, [
                    'event_id' => $event_id,
                    'interest_id' => intval($interest_id)
                ]);
            }
        }

        echo '<div class="notice notice-success is-dismissible"><p>Event and sessions added successfully!</p></div>';
    }
}
?>

<div class="wrap">
    <h2>Add New Event</h2>
    <form method="post" id="htlleo-add-event-form">
        <?php wp_nonce_field('htlleo_add_event', 'htlleo_event_nonce'); ?>
        <table class="form-table">
            <tr>
                <th>Title</th>
                <td><input type="text" name="event_title" required></td>
            </tr>
            <tr>
                <th>Description</th>
                <td><textarea name="event_description" required></textarea></td>
            </tr>
            <tr>
                <th>Date</th>
                <td><input type="date" name="event_date" required></td>
            </tr>
            <tr>
                <th>Interests</th>
                <td>
                    <select name="event_interests[]" id="event_interests" multiple style="width: 40%;">
                        <?php foreach ($interests as $interest) : ?>
                            <option value="<?php echo $interest->id; ?>"><?php echo esc_html($interest->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <script>
            jQuery(document).ready(function($){
                $('#event_interests').select2({
                    placeholder: 'Select interests'
                });
            });
            </script>

        </table>

        <h3>Sessions (time slots)</h3>
        <table id="sessions-table" class="widefat striped">
            <thead>
                <tr>
                    <th>Group Name</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Capacity</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><input type="text" name="sessions[0][group_name]" required></td>
                    <td><input type="time" name="sessions[0][start_time]" required></td>
                    <td><input type="time" name="sessions[0][end_time]" required></td>
                    <td><input type="number" name="sessions[0][capacity]" min="1" required></td>
                    <td><button type="button" class="button remove-session">Remove</button></td>
                </tr>
            </tbody>
        </table>

        <button type="button" class="button" id="add-session-btn">Add Session</button>
        <br><br>
        <input type="submit" class="button button-primary" value="Add Event" name="htlleo_add_event">
    </form>
</div>

<script>
jQuery(document).ready(function($){
    let sessionIndex = 1;

    $('#add-session-btn').on('click', function(){
        let newRow = `<tr>
            <td><input type="text" name="sessions[${sessionIndex}][group_name]" required></td>
            <td><input type="time" name="sessions[${sessionIndex}][start_time]" required></td>
            <td><input type="time" name="sessions[${sessionIndex}][end_time]" required></td>
            <td><input type="number" name="sessions[${sessionIndex}][capacity]" min="1" required></td>
            <td><button type="button" class="button remove-session">Remove</button></td>
        </tr>`;
        $('#sessions-table tbody').append(newRow);
        sessionIndex++;
    });

    $(document).on('click', '.remove-session', function(){
        $(this).closest('tr').remove();
    });
});
</script>
