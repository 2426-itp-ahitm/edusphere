<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$interests_table = htlleo_get_table('interests');

if (isset($_POST['htlleo_add_interest'])) {
    if (!isset($_POST['htlleo_interest_nonce']) || !wp_verify_nonce($_POST['htlleo_interest_nonce'], 'htlleo_add_interest')) {
        echo '<div class="notice notice-error is-dismissible"><p>Nonce verification failed!</p></div>';
    } else {
        $name = sanitize_text_field($_POST['interest_name']);
        if ($name) {
            $wpdb->insert($interests_table, [
                'name' => $name
            ]);
            echo '<div class="notice notice-success is-dismissible"><p>Interest added!</p></div>';
        }
    }
}
?>

<div class="wrap">
    <h2>Add New Interest</h2>
    <form method="post">
        <?php wp_nonce_field('htlleo_add_interest', 'htlleo_interest_nonce'); ?>
        <table class="form-table">
            <tr>
                <th>Name</th>
                <td><input type="text" name="interest_name" required></td>
            </tr>
        </table>
        <input type="submit" class="button button-primary" value="Add Interest" name="htlleo_add_interest">
    </form>
</div>
