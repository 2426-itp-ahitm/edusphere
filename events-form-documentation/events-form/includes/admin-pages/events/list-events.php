<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$events_table = htlleo_get_table('events');
$event_interests_table = htlleo_get_table('event_interests');
$interests_table = htlleo_get_table('interests');

// Fetch all events
$events = $wpdb->get_results("SELECT * FROM $events_table ORDER BY created_at DESC", ARRAY_A);

// Map event_id => array of interest IDs
$event_interests_map = [];
foreach ($events as $event) {
    $event_id = $event['id'];
    $results = $wpdb->get_col($wpdb->prepare(
        "SELECT interest_id FROM $event_interests_table WHERE event_id = %d",
        $event_id
    ));
    $event_interests_map[$event_id] = array_map('intval', $results);
}

// Fetch all interests once
$all_interests = $wpdb->get_results("SELECT * FROM $interests_table", ARRAY_A);

?>

<div class="wrap">
    <h1>Events</h1>

    <?php if ($events): ?>
        <table id="htlleo-events-table" class="widefat striped">
            <thead>
                <tr>
                    <th></th> <!-- Collapse toggle -->
                    <?php foreach(array_keys($events[0]) as $col) echo "<th>$col</th>"; ?>
                    <th>Interests</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($events as $event): ?>
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
                            <select class="event-interests" data-id="<?php echo $event['id']; ?>" multiple>
                                <?php foreach($all_interests as $interest): 
                                    $selected = in_array((int)$interest['id'], $event_interests_map[$event['id']] ?? []) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo esc_attr($interest['id']); ?>" <?php echo $selected; ?>>
                                        <?php echo esc_html($interest['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>

                        <!-- Actions -->
                        <td>
                            <a class="button button-danger" href="<?php echo wp_nonce_url(admin_url('admin.php?page=htlleo_events&htlleo_delete_event=' . $event['id']), 'htlleo_delete_event_' . $event['id']); ?>" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>

                    <!-- Collapsible Session Rows -->
                    <tr class="sessions-container hidden" data-event-id="<?php echo $event['id']; ?>">
                        <td colspan="<?php echo count($event) + 3; ?>">
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

<!-- Enqueue Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

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

    // Initialize Select2 for multi-select interests
    $('.event-interests').select2({
        placeholder: 'Select interests',
        allowClear: true,
        width: '100%'
    });

    // Inline edit on blur
    $('#htlleo-events-table td[contenteditable="true"]').on('blur', function(){
        var cell = $(this);
        var id   = cell.data('id');       
        var col  = cell.data('col');      
        var value = cell.text().trim();   

        if(!id || !col) return;

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'htlleo_update_event',
                nonce: '<?php echo wp_create_nonce("htlleo_inline_edit"); ?>',
                id: id,
                col: col,
                value: value
            },
            success: function(response){
                if(response.success){
                    cell.css('background-color', '#d4edda'); 
                    setTimeout(()=>cell.css('background-color',''), 800);
                } else {
                    alert('Update failed: ' + response.data);
                    cell.css('background-color', '#f8d7da'); 
                }
            },
            error: function(xhr, status, error){
                alert('AJAX error: ' + error);
                cell.css('background-color', '#f8d7da');
            }
        });
    });

    // Update interests on change
    $('.event-interests').on('change', function(){
        var select = $(this);
        var eventId = select.data('id');
        var interestIds = select.val() || []; // Array of selected IDs

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'htlleo_update_event_interests',
                nonce: '<?php echo wp_create_nonce("htlleo_inline_edit"); ?>',
                event_id: eventId,
                interests: interestIds
            },
            success: function(response){
                if(response.success){
                    console.log('Updated interests for event', eventId);
                } else {
                    alert('Failed to update interests: ' + response.data);
                }
            },
            error: function(xhr, status, error){
                alert('AJAX error: ' + error);
            }
        });
    });

});
</script>
