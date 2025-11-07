<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$events_table = htlleo_get_table('events');

// Fetch all events
$events = $wpdb->get_results("SELECT * FROM $events_table ORDER BY created_at DESC", ARRAY_A);

$event_interests_table = htlleo_get_table('event_interests');
$interests_table = htlleo_get_table('interests');

// Map event_id => array of interest names
$event_interests_map = [];
foreach ($events as $event) {
    $event_id = $event->id ?? $event['id'];
    $results = $wpdb->get_col($wpdb->prepare(
        "SELECT i.name 
         FROM $interests_table i
         INNER JOIN $event_interests_table ei ON ei.interest_id = i.id
         WHERE ei.event_id = %d",
        $event_id
    ));
    $event_interests_map[$event_id] = $results;
}

?>

<div class="wrap">
    <h1>Events</h1>

    <?php if ($events): ?>
        <table id="htlleo-events-table" class="widefat striped">
            <thead>
                <tr>
                    <th></th> <!-- Collapse toggle -->
                    <?php foreach(array_keys($events[0]) as $col) echo "<th>$col</th>"; ?>
                    <th>Interests</th> <!-- New column for interests -->
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Fetch all interests for events
                $event_interests_table = htlleo_get_table('event_interests');
                $interests_table = htlleo_get_table('interests');

                $event_interests_map = [];
                foreach ($events as $event_item) {
                    $event_id = $event_item['id'];
                    $results = $wpdb->get_col($wpdb->prepare(
                        "SELECT i.name 
                         FROM $interests_table i
                         INNER JOIN $event_interests_table ei ON ei.interest_id = i.id
                         WHERE ei.event_id = %d",
                        $event_id
                    ));
                    $event_interests_map[$event_id] = $results;
                }
                ?>

                <?php foreach($events as $event): ?>
                    <!-- Event Row -->
                    <tr class="event-row" data-event-id="<?php echo $event['id']; ?>">
                        <td><button class="button toggle-sessions">+</button></td>
                        
                        <?php foreach($event as $key => $val): ?>
                            <?php if (in_array($key, ['id', 'created_at'])): ?>
                                <td><?php echo esc_html($val); ?></td>
                            <?php else: ?>
                                <td contenteditable="true" data-col="<?php echo $key; ?>" data-id="<?php echo $event['id']; ?>"><?php echo esc_html($val); ?></td>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <!-- Interests Column -->
                        <td>
                            <?php
                            $eventId = $event['id'];
                            if (!empty($event_interests_map[$eventId])) {
                                echo esc_html(implode(', ', $event_interests_map[$eventId]));
                            } else {
                                echo '<em>None</em>';
                            }
                            ?>
                        </td>

                        <!-- Actions -->
                        <td>
                            <a class="button button-danger" href="<?php echo wp_nonce_url(admin_url('admin.php?page=htlleo_events&htlleo_delete_event=' . $event['id']), 'htlleo_delete_event_' . $event['id']); ?>" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>

                    <!-- Collapsible Session Rows -->
                    <tr class="sessions-container hidden" data-event-id="<?php echo $event['id']; ?>">
                        <td colspan="<?php echo count($event) + 3; ?>"> <!-- +3 to account for toggle, interests, actions -->
                            <?php 
                            $event_id = $event['id'];
                            include __DIR__ . '/../sessions/list-sessions.php';
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No events found.</p>
    <?php endif; ?>
</div>


<script>
jQuery(document).ready(function($){
    // Toggle session visibility
    $('.toggle-sessions').on('click', function(){
        var row = $(this).closest('tr');
        var eventId = row.data('event-id');
        var container = $('.sessions-container[data-event-id="'+eventId+'"]');
        container.toggleClass('hidden');
        $(this).text(container.hasClass('hidden') ? '+' : '-');
    });
});
</script>
