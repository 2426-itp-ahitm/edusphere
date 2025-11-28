<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

$registrations_table = htlleo_get_table('registrations');
$sessions_table      = htlleo_get_table('sessions');
$interests_table     = htlleo_get_table('interests');
$events_table        = htlleo_get_table('events');

// Use WP timezone-aware date
$today = current_time('Y-m-d');

// Fetch interests
$interests = $wpdb->get_results("SELECT id, name FROM $interests_table ORDER BY name ASC", ARRAY_A);

// Fetch upcoming events and their sessions, grouped by event
$sql = "
    SELECT
        e.id AS event_id,
        e.title AS event_title,
        e.event_date,
        s.id AS session_id,
        s.group_name
    FROM {$events_table} e
    JOIN {$sessions_table} s ON s.event_id = e.id
    WHERE e.event_date >= %s
    ORDER BY e.event_date ASC, e.title ASC, s.group_name ASC
";

$rows = $wpdb->get_results( $wpdb->prepare( $sql, $today ), ARRAY_A );

$events_with_sessions = [];
if ( $rows ) {
    foreach ( $rows as $r ) {
        $eid = (int) $r['event_id'];
        if ( ! isset( $events_with_sessions[ $eid ] ) ) {
            $events_with_sessions[ $eid ] = [
                'title'    => $r['event_title'],
                'date'     => $r['event_date'],
                'sessions' => [],
            ];
        }
        $events_with_sessions[ $eid ]['sessions'][] = [
            'id'         => (int) $r['session_id'],
            'group_name' => $r['group_name'],
        ];
    }
}

// -----------------------------
// Handle Registration Submit
// -----------------------------
if ( isset($_POST['htlleo_add_registration']) ) {

    if ( ! isset($_POST['htlleo_add_registration_nonce']) || ! wp_verify_nonce( $_POST['htlleo_add_registration_nonce'], 'htlleo_add_registration' ) ) {
        echo '<div class="notice notice-error is-dismissible"><p>Nonce verification failed.</p></div>';
    } else {
        // sanitize form fields
        $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
        $preferred_interest_id = ! empty($_POST['preferred_interest_id']) ? intval($_POST['preferred_interest_id']) : null;
        $gender = isset($_POST['gender']) ? sanitize_text_field($_POST['gender']) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
        $school = isset($_POST['school']) ? sanitize_text_field($_POST['school']) : '';
        $class = isset($_POST['class']) ? sanitize_text_field($_POST['class']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';

        // Basic required validation
        $errors = [];
        if ( $session_id <= 0 ) $errors[] = 'Please select a session.';
        if ( empty($last_name) || empty($first_name) ) $errors[] = 'Please provide first and last name.';

        // Server-side: ensure selected session belongs to an upcoming event
        if ( $session_id > 0 ) {
            $session_ok_sql = "
                SELECT COUNT(1)
                FROM {$sessions_table} s
                JOIN {$events_table} e ON s.event_id = e.id
                WHERE s.id = %d AND e.event_date >= %s
            ";
            $session_ok = $wpdb->get_var( $wpdb->prepare( $session_ok_sql, $session_id, $today ) );
            if ( empty( $session_ok ) ) {
                $errors[] = 'Selected session is not available (it may be part of a past event).';
            }
        }

        if ( ! empty( $errors ) ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( implode(' ', $errors) ) . '</p></div>';
        } else {
            // Insert registration
            $insert_data = [
                'session_id' => $session_id,
                'preferred_interest_id' => $preferred_interest_id,
                'gender' => $gender,
                'last_name' => $last_name,
                'first_name' => $first_name,
                'city' => $city,
                'school' => $school,
                'class' => $class,
                'email' => $email,
                'phone' => $phone,
                'status' => 'registered',
                'registered_at' => current_time('mysql'),
            ];

            $wpdb->insert( $registrations_table, $insert_data );

            if ( $wpdb->last_error ) {
                echo '<div class="notice notice-error is-dismissible"><p>DB error: ' . esc_html( $wpdb->last_error ) . '</p></div>';
            } else {
                echo '<div class="notice notice-success is-dismissible"><p>Registration added successfully! ID: ' . intval( $wpdb->insert_id ) . '</p></div>';
                // Optionally clear form values to avoid duplicate submission UI
                $_POST = [];
            }
        }
    }
}
?>

<div class="wrap">
    <h1>Add Registration</h1>

    <form method="post" id="htlleo-add-registration-form">
        <?php wp_nonce_field('htlleo_add_registration', 'htlleo_add_registration_nonce'); ?>

        <table class="form-table">
            <tr>
                <th><label for="session_id">Session (upcoming events only)</label></th>
                <td>
                    <?php if ( ! empty( $events_with_sessions ) ): ?>
                        <select name="session_id" id="session_id" required style="min-width:320px;">
                            <option value="">— Select session —</option>
                            <?php foreach ( $events_with_sessions as $eid => $edata ): ?>
                                <optgroup label="<?php echo esc_attr( $edata['title'] . ' — ' . $edata['date'] ); ?>">
                                    <?php foreach ( $edata['sessions'] as $s ): ?>
                                        <option value="<?php echo esc_attr( $s['id'] ); ?>" <?php selected( isset($_POST['session_id']) ? intval($_POST['session_id']) : 0, $s['id'] ); ?>>
                                            <?php echo esc_html( $s['group_name'] ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <p><em>No upcoming sessions available. Please create an event with future dates first.</em></p>
                    <?php endif; ?>
                </td>
            </tr>

            <tr>
                <th><label for="preferred_interest_id">Preferred Interest</label></th>
                <td>
                    <select name="preferred_interest_id" id="preferred_interest_id">
                        <option value="">— None —</option>
                        <?php foreach ( $interests as $i ): ?>
                            <option value="<?php echo esc_attr( $i['id'] ); ?>" <?php selected( isset($_POST['preferred_interest_id']) ? intval($_POST['preferred_interest_id']) : null, $i['id'] ); ?>>
                                <?php echo esc_html( $i['name'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th><label for="gender">Gender</label></th>
                <td>
                    <?php
                    $genders = ['male' => 'Male', 'female' => 'Female', 'diverse' => 'Diverse', 'none' => '—'];
                    $current_gender = isset($_POST['gender']) ? sanitize_text_field($_POST['gender']) : 'none';
                    ?>
                    <select name="gender" id="gender">
                        <?php foreach ( $genders as $val => $label ): ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_gender, $val ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th><label for="last_name">Last name</label></th>
                <td><input type="text" name="last_name" id="last_name" value="<?php echo isset($_POST['last_name']) ? esc_attr($_POST['last_name']) : ''; ?>" required></td>
            </tr>

            <tr>
                <th><label for="first_name">First name</label></th>
                <td><input type="text" name="first_name" id="first_name" value="<?php echo isset($_POST['first_name']) ? esc_attr($_POST['first_name']) : ''; ?>" required></td>
            </tr>

            <tr><th><label for="city">City</label></th><td><input type="text" name="city" id="city" value="<?php echo isset($_POST['city']) ? esc_attr($_POST['city']) : ''; ?>"></td></tr>
            <tr><th><label for="school">School</label></th><td><input type="text" name="school" id="school" value="<?php echo isset($_POST['school']) ? esc_attr($_POST['school']) : ''; ?>"></td></tr>
            <tr><th><label for="class">Class</label></th><td><input type="text" name="class" id="class" value="<?php echo isset($_POST['class']) ? esc_attr($_POST['class']) : ''; ?>"></td></tr>
            <tr><th><label for="email">Email</label></th><td><input type="email" name="email" id="email" value="<?php echo isset($_POST['email']) ? esc_attr($_POST['email']) : ''; ?>"></td></tr>
            <tr><th><label for="phone">Phone</label></th><td><input type="text" name="phone" id="phone" value="<?php echo isset($_POST['phone']) ? esc_attr($_POST['phone']) : ''; ?>"></td></tr>
        </table>

        <p>
            <input type="submit" name="htlleo_add_registration" class="button button-primary" value="Add Registration">
        </p>
    </form>
</div>
