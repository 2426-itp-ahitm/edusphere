jQuery(document).ready(function($){
    function saveCell(td){
        var value = td.text();
        var col = td.data('col');
        var row = td.closest('tr');
        var id = row.data('id');

        // Convert datetime-local format to MySQL DATETIME
        if(col === 'start_datetime' || col === 'end_datetime'){
            value = value.replace('T', ''); // remove T
            value += ':00'; // add seconds
        }

        $.post(htlleo_ajax.ajax_url, {
            action: td.closest('table').hasClass('sessions-table') ? 'htlleo_update_session' : 'htlleo_update_event',
            nonce: htlleo_ajax.nonce,
            id: id,
            col: col,
            value: value
        }, function(response){
            if(response.success){
                td.css('background-color', '#d4edda');
                setTimeout(() => td.css('background-color', ''), 500);
            } else {
                td.css('background-color', '#f8d7da');
                alert('Update failed: '+response.data);
                setTimeout(() => td.css('background-color', ''), 500);
            }
        });
    }

    $('td[contenteditable="true"]').on('blur', function(){
        saveCell($(this));
    });
});
