<?php
if (!defined('ABSPATH')) exit;

function htlleo_register_event_shortcode() {
    global $wpdb;

    add_shortcode('htlleo_event', function($atts) use ($wpdb) {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts, 'htlleo_event');

        $event_id = intval($atts['id']);
        if (!$event_id) return '<p>No event specified.</p>';

        $events_table = htlleo_get_table('events');
        $sessions_table = htlleo_get_table('sessions');
        $interests_table = htlleo_get_table('interests');
        $event_interests_table = htlleo_get_table('event_interests');

        $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM $events_table WHERE id = %d", $event_id), ARRAY_A);
        if (!$event) return '<p>Event not found.</p>';

        $sessions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $sessions_table WHERE event_id = %d ORDER BY start_time ASC", $event_id), ARRAY_A);

        $interest_names = $wpdb->get_col($wpdb->prepare(
            "SELECT i.name 
             FROM $interests_table i
             INNER JOIN $event_interests_table ei ON ei.interest_id = i.id
             WHERE ei.event_id = %d",
            $event_id
        ));

        // CSS einbinden
        wp_enqueue_style('htlleo-event-style', plugin_dir_url(__FILE__) . './style.css');

        ob_start(); ?>
        <div class="htlleo-event">
            <header class="htlleo-event-header">
                <h2 class="htlleo-event-title"><?php echo esc_html($event['title']); ?></h2>
                <p class="htlleo-event-date"><?php echo esc_html($event['event_date']); ?></p>
                <?php if (!empty($interest_names)): ?>
                    <p class="htlleo-event-interests">
                        <strong>Abteilungen:</strong> <?php echo esc_html(implode(', ', $interest_names)); ?>
                    </p>
                <?php endif; ?>
            </header>

            <?php if (!empty($sessions)): ?>
                <section class="htlleo-event-sessions">
                    <h3 class="htlleo-sessions-heading">Gruppen</h3>
                    <div class="htlleo-session-list">
                        <?php foreach ($sessions as $session): ?>
                            <?php
                                $registrations_count = $wpdb->get_var($wpdb->prepare(
                                    "SELECT COUNT(*) FROM {$wpdb->prefix}htlleo_registrations WHERE session_id = %d",
                                    $session['id']
                                ));

                                $is_available = intval($session['capacity']) > intval($registrations_count);
                            ?>
                            <div class="htlleo-session-card <?php echo $is_available ? 'htlleo-session-available' : 'htlleo-session-full'; ?>" 
                                data-session-id="<?php echo intval($session['id']); ?>">
                                <p class="htlleo-session-group"><?php echo esc_html($session['group_name']); ?></p>
                                <p class="htlleo-session-time"><?php echo esc_html($session['start_time']); ?> - <?php echo esc_html($session['end_time']); ?></p>
                                <p class="htlleo-session-capacity">
                                    <?php echo intval($session['capacity']) - intval($registrations_count); ?> Plätze frei
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <div class="htlleo-registration-form-container"></div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const cards = document.querySelectorAll('.htlleo-session-available');
                
                cards.forEach(card => {
                    card.addEventListener('click', function() {
                        const sessionId = this.dataset.sessionId;
                        const container = this.closest('.htlleo-event').querySelector('.htlleo-registration-form-container');

                        // Formular dynamisch einfügen
                        container.innerHTML = `
                            <form class="htlleo-registration-form" data-session-id="${sessionId}">
                                <h3>Jetzt anmelden für Gruppe: ${this.querySelector('.htlleo-session-group').textContent}</h3>
                                <label>Vorname:<input type="text" name="first_name" required></label>
                                <label>Nachname:<input type="text" name="last_name" required></label>
                                <label>Geschlecht:
                                    <select name="gender">
                                        <option value="m">Männlich</option>
                                        <option value="f">Weiblich</option>
                                        <option value="d">Divers</option>
                                    </select>
                                </label>
                                <label>Stadt:<input type="text" name="city"></label>
                                <label>Schule:<input type="text" name="school"></label>
                                <label>Klasse:<input type="text" name="class"></label>
                                <label>Email:<input type="email" name="email" required></label>
                                <label>Telefon:<input type="text" name="phone"></label>
                                <label>Bevorzugtes Interesse:<input type="text" name="preferred_interest_id"></label>
                                <button type="submit">Jetzt anmelden</button>
                            </form>
                            <div class="htlleo-registration-response"></div>
                        `;

                        // Formular via AJAX senden
                        const form = container.querySelector('.htlleo-registration-form');
                        form.addEventListener('submit', function(e){
                            e.preventDefault();
                            const data = new FormData(form);
                            data.append('action', 'htlleo_register_user');

                            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                                method: 'POST',
                                body: data
                            })
                            .then(res => res.json())
                            .then(res => {
                                container.querySelector('.htlleo-registration-response').textContent = res.message;
                                if(res.success){
                                    form.reset();
                                }
                            });
                        });
                    });
                });
            });
        </script>

        <?php
        return ob_get_clean();
    });
}

add_action('init', 'htlleo_register_event_shortcode');


add_action('wp_ajax_htlleo_register_user', 'htlleo_register_user');
add_action('wp_ajax_nopriv_htlleo_register_user', 'htlleo_register_user');

function htlleo_register_user(){
    global $wpdb;
    $table = $wpdb->prefix . 'htlleo_registrations';

    $session_id = intval($_POST['session_id']);
    $data = [
        'session_id' => $session_id,
        'first_name' => sanitize_text_field($_POST['first_name']),
        'last_name'  => sanitize_text_field($_POST['last_name']),
        'gender'     => sanitize_text_field($_POST['gender']),
        'city'       => sanitize_text_field($_POST['city']),
        'school'     => sanitize_text_field($_POST['school']),
        'class'      => sanitize_text_field($_POST['class']),
        'email'      => sanitize_email($_POST['email']),
        'phone'      => sanitize_text_field($_POST['phone']),
        'preferred_interest_id' => intval($_POST['preferred_interest_id']),
        'status'     => 'pending',
    ];

    $inserted = $wpdb->insert($table, $data);

    if($inserted){
        wp_send_json(['success'=>true, 'message'=>'Danke, Ihre Anmeldung wurde gespeichert!']);
    } else {
        wp_send_json(['success'=>false, 'message'=>'Fehler beim Speichern. Bitte versuchen Sie es erneut.']);
    }
}

