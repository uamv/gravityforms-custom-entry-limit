/**
 * Handles Glance That icon and form interaction
 */
 jQuery(document).ready(function($) {

	 $('span[id$="-typewheel-notice-dismissals"] i').click(
		 function() {
			 $.post(TypewheelNotice.ajaxurl, {
				 action: 'dismiss_notice',
				 typewheel_notice_duration: $(this).data('dismissal-duration'),
				 typewheel_notice: $(this).data('notice'),
				 typewheel_notice_plugin: $(this).data('plugin'),

			 }, function (response) {

				 if ( response.success ) {

					 $('div#'+response.notice+'-typewheel-notice').hide();

				 }
			 }
	 )});

 });
