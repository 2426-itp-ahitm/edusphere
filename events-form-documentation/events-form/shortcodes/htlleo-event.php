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
            <div class="htlleo-event-header">
                <h2><?php echo esc_html($event['title']); ?></h2>
                <p class="htlleo-event-date"><?php echo esc_html($event['event_date']); ?></p>
                <?php if (!empty($interest_names)): ?>
                    <p class="htlleo-event-interests"><strong>Interests:</strong> <?php echo esc_html(implode(', ', $interest_names)); ?></p>
                <?php endif; ?>
            </div>

            <?php if (!empty($sessions)): ?>
                <div class="htlleo-event-sessions">
                    <?php foreach ($sessions as $session): ?>
                        <div class="htlleo-session-row">
                            <p class="htlleo-session-group"><?php echo esc_html($session['group_name']); ?></p>
                            <p class="htlleo-session-time"><?php echo esc_html($session['start_time']); ?> - <?php echo esc_html($session['end_time']); ?></p>
                            <p class="htlleo-session-capacity">Capacity: <?php echo intval($session['capacity']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    });
}

add_action('init', 'htlleo_register_event_shortcode');
