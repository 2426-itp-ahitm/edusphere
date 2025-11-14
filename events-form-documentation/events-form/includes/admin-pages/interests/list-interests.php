<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$interests_table = htlleo_get_table('interests');

$interests = $wpdb->get_results("SELECT * FROM $interests_table ORDER BY id ASC", ARRAY_A);
?>

<div class="wrap">
<h2>All Interests</h2>

<?php if ($interests): ?>
<table class="widefat striped" id="htlleo-interests-table">
<thead>
<tr>
<th>ID</th>
<th>Name</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($interests as $interest): ?>
<tr data-id="<?php echo $interest['id']; ?>">
<td><?php echo esc_html($interest['id']); ?></td>
<td contenteditable="true" class="editable-name"><?php echo esc_html($interest['name']); ?></td>
<td>
<button class="button button-danger delete-interest">Delete</button>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php else: ?>
<p>No interests found.</p>
<?php endif; ?>
</div>

<script>
jQuery(document).ready(function($){
    // Inline edit
    $('.editable-name').on('blur', function(){
        var td = $(this);
        var id = td.closest('tr').data('id');
        var value = td.text().trim();

        $.post(ajaxurl, {
            action: 'htlleo_update_interest',
            nonce: '<?php echo wp_create_nonce("htlleo_inline_edit"); ?>',
               id: id,
               value: value
        }, function(response){
            if(!response.success){
                alert('Update failed: ' + response.data);
            }
        });
    });

    // Delete interest
    $('.delete-interest').on('click', function(){
        if(!confirm('Are you sure you want to delete this interest?')) return;

        var tr = $(this).closest('tr');
        var id = tr.data('id');

        $.post(ajaxurl, {
            action: 'htlleo_delete_interest',
            nonce: '<?php echo wp_create_nonce("htlleo_inline_edit"); ?>',
               id: id
        }, function(response){
            if(response.success){
                tr.remove();
            } else {
                alert('Delete failed: ' + response.data);
            }
        });
    });
});
</script>
