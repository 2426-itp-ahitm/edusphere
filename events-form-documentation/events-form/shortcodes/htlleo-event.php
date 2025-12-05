<?php
if (!defined('ABSPATH')) exit;

function htlleo_register_event_shortcode() {
    global $wpdb;

    add_shortcode('htlleo_event', function($atts) use ($wpdb) {
        $atts = shortcode_atts(['id' => 0], $atts, 'htlleo_event');
        $event_id = intval($atts['id']);
        if (!$event_id) return '<p>No event specified.</p>';

        $events_table = htlleo_get_table('events');
        $sessions_table = htlleo_get_table('sessions');
        $interests_table = htlleo_get_table('interests');
        $event_interests_table = htlleo_get_table('event_interests');

        $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM $events_table WHERE id=%d", $event_id), ARRAY_A);
        if (!$event) return '<p>Event not found.</p>';

        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $sessions_table WHERE event_id=%d ORDER BY start_time ASC",
            $event_id
        ), ARRAY_A);

        $event_interest_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT interest_id FROM $event_interests_table WHERE event_id=%d",
            $event_id
        ));
        $event_interests = [];
        if ($event_interest_ids) {
            $interests = $wpdb->get_results("SELECT id, name FROM $interests_table WHERE id IN (" . implode(',', array_map('intval',$event_interest_ids)) . ")", ARRAY_A);
            foreach ($interests as $i) {
                $event_interests[$i['id']] = $i['name'];
            }
        }

        wp_enqueue_style('htlleo-event-style', plugin_dir_url(__FILE__) . './style.css');

        ob_start(); ?>
        <div class="htlleo-event">
            <header class="htlleo-event-header">
                <h2><?php echo esc_html($event['title']); ?></h2>
                <p><?php echo esc_html($event['event_date']); ?></p>
                <?php if ($event_interests): ?>
                    <p><strong>Abteilungen:</strong> <?php echo esc_html(implode(', ', $event_interests)); ?></p>
                <?php endif; ?>
            </header>

            <?php if ($sessions): ?>
                <section class="htlleo-event-sessions">
                    <h3>Gruppen</h3>
                    <div class="htlleo-session-list">
                        <?php foreach ($sessions as $session): 
                            $registrations_count = $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM {$wpdb->prefix}htlleo_registrations WHERE session_id=%d",
                                $session['id']
                            ));
                            $is_available = intval($session['capacity']) > intval($registrations_count);
                        ?>
                        <div class="htlleo-session-card <?php echo $is_available?'htlleo-session-available':'htlleo-session-full'; ?>" 
                             data-session-id="<?php echo intval($session['id']); ?>">
                            <p class="htlleo-session-group"><?php echo esc_html($session['group_name']); ?></p>
                            <p class="htlleo-session-time"><?php echo esc_html($session['start_time']); ?> - <?php echo esc_html($session['end_time']); ?></p>
                            <p class="htlleo-session-capacity"><?php echo intval($session['capacity']) - intval($registrations_count); ?> Plätze frei</p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <div class="htlleo-registration-form-container"></div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const eventContainer = document.querySelector('.htlleo-event');
            const container = eventContainer.querySelector('.htlleo-registration-form-container');
            const eventInterests = <?php echo json_encode($event_interests); ?>;
            const eventId = <?php echo $event_id; ?>;

            function openForm(container, contentHTML) {
                container.innerHTML = contentHTML;

                // Höhe messen und animieren
                const fullHeight = container.scrollHeight + 'px';
                container.style.height = '0';
                container.offsetHeight; // trigger reflow
                container.style.transition = 'height 0.3s ease';
                container.style.height = fullHeight;

                // Setze height auf auto nach der Transition für dynamische Inhalte
                container.addEventListener('transitionend', function handler() {
                    container.style.height = 'auto';
                    container.removeEventListener('transitionend', handler);
                });

                const form = container.querySelector('.htlleo-registration-form');
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const data = new FormData(form);
                    data.append('action', 'htlleo_register_user');

                    fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                        method: 'POST',
                        body: data
                    })
                    .then(res => res.json())
                    .then(res => {
                        const respDiv = container.querySelector('.htlleo-registration-response');
                        respDiv.textContent = res.message;

                        if (res.success) {
                            // Smooth Close
                            container.style.height = container.scrollHeight + 'px'; // fix current height
                            container.offsetHeight; // trigger reflow
                            container.style.height = '0';

                            container.addEventListener('transitionend', function handlerClose() {
                                container.innerHTML = '';
                                container.style.height = '';
                                container.removeEventListener('transitionend', handlerClose);
                            });

                            // Optional: Session-Liste neu laden wie vorher
                        }
                    });
                });
            }


            function attachCardClickHandlers() {
                const cards = eventContainer.querySelectorAll('.htlleo-session-available');
                cards.forEach(card => {
                    card.addEventListener('click', function() {
                        const sessionId = this.dataset.sessionId;
                        let interestOptions = '';
                        for (const id in eventInterests) {
                            interestOptions += `<option value="${id}">${eventInterests[id]}</option>`;
                        }

                        const formHTML = `
                            <form class="htlleo-registration-form">
                                <input type="hidden" name="session_id" value="${sessionId}">
                                <label>Vorname: <input type="text" name="first_name" required></label>
                                <label>Nachname: <input type="text" name="last_name" required></label>
                                <label>Geschlecht:
                                    <select name="gender">
                                        <option value="male">Männlich</option>
                                        <option value="female">Weiblich</option>
                                        <option value="diverse">Divers</option>
                                    </select>
                                </label>
                                <label>Stadt: <input type="text" name="city"></label>
                                <label>Schule: <input type="text" name="school"></label>
                                <label>Klasse: <input type="text" name="class"></label>
                                <label>Email: <input type="email" name="email" required></label>
                                <label>Telefon: <input type="text" name="phone"></label>
                                <label>Bevorzugtes Interesse:
                                    <select name="preferred_interest_id" required>
                                        <option value="">— wählen —</option>
                                        ${interestOptions}
                                    </select>
                                </label>
                                <button type="submit">Jetzt anmelden</button>
                            </form>
                            <div class="htlleo-registration-response"></div>
                        `;
                        openForm(container, formHTML);
                    });
                });
            }

            attachCardClickHandlers();
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
    $preferred_interest_id = intval($_POST['preferred_interest_id']);

    if (!$session_id || !$preferred_interest_id) {
        wp_send_json(['success'=>false,'message'=>'Session und Interesse müssen ausgewählt werden.']);
    }

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
        'preferred_interest_id' => $preferred_interest_id,
        'status'     => 'pending',
        'registered_at' => current_time('mysql'),
    ];

    $inserted = $wpdb->insert($table, $data);

    if($inserted){
        wp_send_json(['success'=>true,'message'=>'Danke, Ihre Anmeldung wurde gespeichert!']);
    } else {
        wp_send_json(['success'=>false,'message'=>'Fehler beim Speichern. Bitte versuchen Sie es erneut.']);
    }
}

add_action('wp_ajax_htlleo_get_event_sessions', 'htlleo_get_event_sessions');
add_action('wp_ajax_nopriv_htlleo_get_event_sessions', 'htlleo_get_event_sessions');

function htlleo_get_event_sessions() {
    global $wpdb;
    $event_id = intval($_GET['id']);
    if (!$event_id) wp_die();

    $sessions_table = htlleo_get_table('sessions');
    $sessions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $sessions_table WHERE event_id=%d ORDER BY start_time ASC",
        $event_id
    ), ARRAY_A);

    ob_start();
    if ($sessions) {
        foreach ($sessions as $session):
            $registrations_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}htlleo_registrations WHERE session_id=%d",
                $session['id']
            ));
            $is_available = intval($session['capacity']) > intval($registrations_count);
            ?>
            <div class="htlleo-session-card <?php echo $is_available?'htlleo-session-available':'htlleo-session-full'; ?>" 
                 data-session-id="<?php echo intval($session['id']); ?>">
                <p class="htlleo-session-group"><?php echo esc_html($session['group_name']); ?></p>
                <p class="htlleo-session-time"><?php echo esc_html($session['start_time']); ?> - <?php echo esc_html($session['end_time']); ?></p>
                <p class="htlleo-session-capacity"><?php echo intval($session['capacity']) - intval($registrations_count); ?> Plätze frei</p>
            </div>
            <?php
        endforeach;
    } else {
        echo '<p>Keine Gruppen verfügbar.</p>';
    }
    echo ob_get_clean();
    wp_die();
}
