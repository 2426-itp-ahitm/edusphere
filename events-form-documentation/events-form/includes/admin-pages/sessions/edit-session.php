<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$sessions_table = htlleo_get_table('sessions');

$session_id = intval($_GET['session_id'] ?? 0);
$session = $wpdb->get_row($wpdb->prepare("SELECT * FROM $sessions_table WHERE id=%d", $session_id), ARRAY_A);

if (!$session) {
    echo '<div class="notice notice-error"><p>Invalid session ID.</p></div>';
    return;
}

// Handle update
if (isset($_POST['htlleo_edit_session'])) {
    if (!isset($_POST['htlleo_session_nonce']) || !wp_verify_nonce($_POST['htlleo_session_nonce'], 'htlleo_edit_session')) {
        echo '<div class="notice notice-error"><p>Nonce verification failed!</p></div>';
    } else {
        $group_name = sanitize_text_field($_POST['group_name']);
        $start = sanitize_text_field($_POST['start_datetime']);
        $end = sanitize_text_field($_POST['end_datetime']);
        $capacity = intval($_POST['capacity']);

        $wpdb->update($sessions_table, [
            'group_name' => $group_name,
            'start_datetime' => $start,
            'end_datetime' => $end,
            'capacity' => $capacity,
        ], ['id' => $session_id]);

        echo '<div class="notice notice-success"><p>Session updated!</p></div>';
        $session = $wpdb->get_row($wpdb->prepare("SELECT * FROM $sessions_table WHERE id=%d", $session_id), ARRAY_A);
    }
}
?>

<div class="wrap">
    <h2>Edit Session</h2>
    <form method="post">
        <?php wp_nonce_field('htlleo_edit_session', 'htlleo_session_nonce'); ?>
        <table class="form-table">
            <tr>
                <th>Group Name</th>
                <td><input type="text" name="group_name" value="<?php echo esc_attr($session['group_name']); ?>" required></td>
            </tr>
            <tr>
                <th>Start</th>
                <td><input type="datetime-local" name="start_datetime" value="<?php echo esc_attr(date('Y-m-d\TH:i', strtotime($session['start_datetime']))); ?>" required></td>
            </tr>
            <tr>
                <th>End</th>
                <td><input type="datetime-local" name="end_datetime" value="<?php echo esc_attr(date('Y-m-d\TH:i', strtotime($session['end_datetime']))); ?>" required></td>
            </tr>
            <tr>
                <th>Capacity</th>
                <td><input type="number" name="capacity" value="<?php echo esc_attr($session['capacity']); ?>" min="1" required></td>
            </tr>
        </table>
        <input type="submit" class="button button-primary" value="Update Session" name="htlleo_edit_session">
    </form>
</div>
