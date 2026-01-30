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
        echo '<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;800&display=swap" rel="stylesheet">';

        ob_start(); ?>
            <div class="htlleo-event" data-event-id="<?php echo $event_id; ?>" data-interests='<?php echo esc_attr(json_encode($event_interests)); ?>'>            <header class="htlleo-event-header">
                <h2><?php echo esc_html($event['title']); ?></h2>
                <p>
                    <?php 
                        $date_obj = new DateTime($event['event_date']);
                        $formatter = new IntlDateFormatter('de_AT', IntlDateFormatter::LONG, IntlDateFormatter::NONE, null, null, 'd. MMMM yyyy');
                        echo esc_html($formatter->format($date_obj));
                    ?>
                </p>
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
                            <p class="htlleo-session-time"><?php echo date('H:i', strtotime($session['start_time'])); ?> - <?php echo date('H:i', strtotime($session['end_time'])); ?> Uhr</p>
                            <p class="htlleo-session-capacity"><?php echo intval($session['capacity']) - intval($registrations_count); ?> Plätze frei</p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <div class="htlleo-registration-form-container"></div>
        </div>
        <script>
            // Event-Delegation außerhalb von DOMContentLoaded für bessere Zuverlässigkeit in Slidern
            if (typeof htlleoInit === 'undefined') {
                document.addEventListener('click', function(e) {
                    if (e.target.closest('.htlleo-registration-form')) return; 

                    const card = e.target.closest('.htlleo-session-available');
                    if (!card) return;

                    e.preventDefault();
                    e.stopPropagation();

                    const eventWrapper = card.closest('.htlleo-event');
                    const container = eventWrapper.querySelector('.htlleo-registration-form-container');
                    const sessionId = card.dataset.sessionId;
                    const groupName = card.querySelector('.htlleo-session-group').textContent;

                    // FIX: Interessen lokal aus dem jeweiligen Wrapper ziehen
                    const localInterests = JSON.parse(eventWrapper.getAttribute('data-interests') || '{}');

                    if (eventWrapper.dataset.activeSession === sessionId && container.style.height !== '0px') {
                        container.style.height = '0';
                        eventWrapper.dataset.activeSession = "";
                        setTimeout(() => { if (!eventWrapper.dataset.activeSession) container.innerHTML = ''; }, 500);
                        return;
                    }

                    eventWrapper.dataset.activeSession = sessionId;
                    
                    let interestOptions = '';
                    for (const id in localInterests) {
                        interestOptions += `<option value="${id}">${localInterests[id]}</option>`;
                    }

                    container.innerHTML = `
                        <form class="htlleo-registration-form" onclick="event.stopPropagation();">
                            <h3 class="form-title">Anmeldung: ${groupName}</h3>
                            <input type="hidden" name="session_id" value="${sessionId}">
                            <label>Vorname <input type="text" name="first_name" required></label>
                            <label>Nachname <input type="text" name="last_name" required></label>
                            <label>Geschlecht
                                <select name="gender" required>
                                    <option value="" disabled selected>— bitte wählen —</option>
                                    <option value="male">Männlich</option>
                                    <option value="female">Weiblich</option>
                                    <option value="diverse">Divers</option>
                                </select>
                            </label>
                            <label>Email <input type="email" name="email" required></label>
                            <label>Stadt <input type="text" name="city" required></label>
                            <label>Schule <input type="text" name="school" required></label>
                            <label>Klasse <input type="text" name="class" required></label>
                            <label>Telefon <input type="text" name="phone" required></label>
                            <label class="form-full-width">Interesse
                                <select name="preferred_interest_id" required>
                                    <option value="" disabled selected>— bitte wählen —</option>
                                    ${interestOptions}
                                </select>
                            </label>
                            <div class="form-actions form-full-width">
                                <button type="submit" class="submit-btn">Jetzt anmelden</button>
                                <button type="button" class="cancel-btn">Abbrechen</button>
                            </div>
                        </form>
                        <div class="htlleo-registration-response"></div>
                    `;
                    
                    requestAnimationFrame(() => {
                        container.style.height = container.scrollHeight + 'px';
                    });

                    const form = container.querySelector('.htlleo-registration-form');
                    form.addEventListener('submit', function(ev) {
                        ev.preventDefault();
                        const btn = form.querySelector('.submit-btn');
                        btn.disabled = true;
                        btn.textContent = 'Wird gesendet...';

                        const data = new FormData(form);
                        data.append('action', 'htlleo_register_user');

                        fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: data })
                        .then(res => res.json())
                        .then(res => {
                            const respDiv = container.querySelector('.htlleo-registration-response');
                            respDiv.innerHTML = `<div class="status-msg ${res.success ? 'success' : 'error'}">${res.message}</div>`;
                            
                            if (res.success) {
                                setTimeout(() => {
                                    // Formular schließen
                                    container.style.height = '0';
                                    eventWrapper.dataset.activeSession = "";

                                    // JETZT: Die Plätze live aktualisieren
                                    // Wir nutzen die eventId direkt aus PHP für diesen Shortcode-Block
                                    const refreshId = <?php echo (int)$event_id; ?>;
                                    
                                    fetch('<?php echo admin_url("admin-ajax.php"); ?>?action=htlleo_get_event_sessions&id=' + refreshId)
                                        .then(r => r.text())
                                        .then(html => {
                                            if (html.length > 10) { // Kurzer Check ob Inhalt kam
                                                // Wir updaten alle Instanzen dieses Events auf der Seite
                                                document.querySelectorAll('.htlleo-event[data-event-id="' + refreshId + '"] .htlleo-session-list').forEach(list => {
                                                    list.innerHTML = html;
                                                });
                                            }
                                            setTimeout(() => { container.innerHTML = ''; }, 500);
                                        });
                                }, 2000);
                            } else {
                                btn.disabled = false;
                                btn.textContent = 'Jetzt anmelden';
                            }
                        })
                        .catch(err => {
                            console.error('Fehler:', err);
                            btn.disabled = false;
                            btn.textContent = 'Fehler beim Senden';
                        });
                    });

                    container.querySelector('.cancel-btn').addEventListener('click', () => {
                        container.style.height = '0';
                        eventWrapper.dataset.activeSession = "";
                        setTimeout(() => { if (!eventWrapper.dataset.activeSession) container.innerHTML = ''; }, 500);
                    });
                }, true);
                var htlleoInit = true;
            }
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
    $registrations_table = $wpdb->prefix . 'htlleo_registrations';
    $sessions_table = htlleo_get_table('sessions');

    $session_id = intval($_POST['session_id']);
    $preferred_interest_id = intval($_POST['preferred_interest_id']);

    if (!$session_id || !$preferred_interest_id) {
        wp_send_json(['success'=>false,'message'=>'Daten unvollständig.']);
    }

    // --- Check if session full ---
    $session = $wpdb->get_row($wpdb->prepare("SELECT capacity FROM $sessions_table WHERE id=%d", $session_id));
    $current_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $registrations_table WHERE session_id=%d", $session_id));

    if ($current_count >= intval($session->capacity)) {
        wp_send_json(['success'=>false, 'message'=>'Leider ist diese Gruppe voll. Bitte wählen Sie eine andere.']);
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

    $inserted = $wpdb->insert($registrations_table, $data);

    if($inserted){
        // Email Versand (bleibt gleich)
        $to = $data['email'];
        $subject = "Anmeldebestätigung";
        $message = "Hallo " . $data['first_name'] . ",\n\nIhre Anmeldung war erfolgreich.";
        wp_mail($to, $subject, $message);

        wp_send_json(['success'=>true,'message'=>'Anmeldung erfolgreich gespeichert!']);
    } else {
        wp_send_json(['success'=>false,'message'=>'Datenbankfehler. Bitte versuchen Sie es erneut.']);
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
                <p class="htlleo-session-time"><?php echo date('H:i', strtotime($session['start_time'])); ?> - <?php echo date('H:i', strtotime($session['end_time'])); ?> Uhr</p>
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
