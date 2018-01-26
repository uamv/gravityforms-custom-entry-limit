<?php
/*
Plugin Name: Gravity Forms Custom Entry Limit
Plugin URI: https://typewheel.xyz/project/custom-entry-limit
Description: Adds options for custom limiting of number of entries to a Gravity Form.
Version: 1.0.beta9
Author: Typewheel
Author URI: https://typewheel.xyz/
Typewheel Update ID: 2

------------------------------------------------------------------------
Copyright 2012-2016 Typewheel LLC

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

define( 'GF_CUSTOM_ENTRY_LIMIT_VERSION', '1.0.beta9' );
define( 'GF_CUSTOM_ENTRY_LIMIT_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'GF_CUSTOM_ENTRY_LIMIT_DIR_URL', plugin_dir_url( __FILE__ ) );

require_once( 'class-typewheel-updater.php' );
require_once( 'typewheel-notice/class-typewheel-notice.php' );

add_action( 'gform_loaded', array( 'GF_Custom_Entry_Limit_Bootstrap', 'load' ), 5 );

class GF_Custom_Entry_Limit_Bootstrap {

    public static function load() {

        if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
            return;
        }

        require_once( 'class-gfcustomentrylimit.php' );

        GFAddOn::register( 'GFCustomEntryLimit' );

    }

}

function gf_custom_entry_limit() {
    return GFCustomEntryLimit::get_instance();
}

/**** DECLARE TYPEWHEEL NOTICES ****/

add_action( 'admin_notices', 'gfcustomentrylimit_notices' );
/**
 * Displays a plugin notices
 *
 * @since    1.0
 */
function gfcustomentrylimit_notices() {

	$prefix = str_replace( '-', '_', dirname( plugin_basename(__FILE__) ) );

	if ( ! get_option( $prefix . '_activated' ) ) {

		// // Notice to show on plugin activation
		// $html = '<div class="updated">';
		// 	$html .= '<p style="display: inline-block">';
		// 		$html .= __( "<strong>Nice!</strong> We're up and running! Enjoy your experience with Gravity Forms: Custom Entry Limit.", 'typewheel' );
		// 	$html .= '</p>';
		// $html .= '</div><!-- /.updated -->';
        //
		// echo $html;

		// Define the notices
		$typewheel_notices = array(
			$prefix . '-give' => array(
				'trigger' => true,
				'time' => time() + 604800,
				'dismiss' => array( 'month' ),
				// 'type' => 'success',
				'content' => 'Is <strong>GF Custom Entry Limit</strong> working well for you? Please consider a <a href="https://typewheel.xyz/give/?ref=GF%20Custom%20Entry%20Limit" target="_blank">small donation</a> to encourage further development.',
				'icon' => 'heart',
				'style' => array( 'background-image' => 'linear-gradient( to bottom right, rgb(215, 215, 215), rgb(231, 211, 186) )', 'border-left' => '0' ),
				'location' => array( 'admin.php?page=gf_edit_forms', 'admin.php?page=gf_entries', 'admin.php?page=gf_settings', 'admin.php?page=gf_addons' ),
			),
		);

		// get the notice class
		$notices = new Typewheel_Notice( $prefix, $typewheel_notices );

		update_option( $prefix . '_activated', true );

	} else {

		$notices = new Typewheel_Notice( $prefix );

	}

} // end display_plugin_notices

/**
 * Deletes activation marker so it can be displayed when the plugin is reinstalled or reactivated
 *
 * @since    1.0
 */
function gfcustomentrylimit_remove_activation_marker() {

	$prefix = str_replace( '-', '_', dirname( plugin_basename(__FILE__) ) );

	delete_option( $prefix . '_activated' );

}
register_deactivation_hook( dirname(__FILE__) . '/custom-entry-limit.php', 'gfcustomentrylimit_remove_activation_marker' );
