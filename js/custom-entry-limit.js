jQuery(document).ready(function( $ ) {

    // conditionally show option when limit entries is checked
    function DisplayLimitEntryFieldSum() {
        if ( $('#gform_limit_entries').is(':checked') ) {
             $('#custom_entry_limit').show().find('.gf_animate_sub_settings').slideDown();
         } else {
     		     $('#custom_entry_limit').hide();
         }
    }

    DisplayLimitEntryFieldSum();

    $('#gform_limit_entries').click(function() {
        DisplayLimitEntryFieldSum();
    });

});
