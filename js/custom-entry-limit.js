jQuery(document).ready(function( $ ) {

    // conditionally show option when limit entries is checked
    function DisplayLimitEntryFieldSum() {
        if ( $('#gform_limit_entries').is(':checked') ) {
                $('#limit_entries_custom').show().find('.gf_animate_sub_settings').slideDown();
            } else {
        		$('#limit_entries_custom').hide();
            }
    }

    DisplayLimitEntryFieldSum();

    $('#gform_limit_entries').click(function() {
        DisplayLimitEntryFieldSum();
    })

});
