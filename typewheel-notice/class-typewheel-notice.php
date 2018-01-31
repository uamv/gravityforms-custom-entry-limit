<?php

/**
* Registers administrative stylesheets and JavaScript
*
* @since    1.0
*/
if ( ! function_exists( 'typewheel_notices_add_stylesheets_and_javascript' ) ) {

	add_action( 'admin_enqueue_scripts', 'typewheel_notices_add_stylesheets_and_javascript' );
	function typewheel_notices_add_stylesheets_and_javascript() {

		wp_enqueue_style( 'typewheel-notice', plugins_url( 'typewheel-notice/typewheel-notice.css', dirname(__FILE__) ) );
		wp_enqueue_script( 'typewheel-notice', plugins_url( 'typewheel-notice/typewheel-notice.js', dirname(__FILE__) ), array( 'jquery' ) );
		wp_localize_script( 'typewheel-notice', 'TypewheelNotice', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );

	} // end add_stylesheets_and_javascript

}

if ( ! class_exists( 'Typewheel_Notice' ) ) {
	/**
	 * Typewheel Notice Class
	 *
	 * Adds Typewheel notices to the WP admin
	 *
	 * @package Typewheel Notice
	 * @author  UaMV
	 */
	class Typewheel_Notice {

		/*---------------------------------------------------------------------------------*
		 * Attributes
		 *---------------------------------------------------------------------------------*/

		/**
		 * Notices.
		 *
		 * @since    1.0
		 *
		 * @var      array
		 */
		public $notices;

		/**
		 * User.
		 *
		 * @since    1.0
		 *
		 * @var      array
		 */
		public $user;

		/**
		 * Notices.
		 *
		 * @since    1.0
		 *
		 * @var      array
		 */
		public $prefix;

		/*---------------------------------------------------------------------------------*
		 * Constructor
		 *---------------------------------------------------------------------------------*/

		/**
		 * Initialize the plugin by setting localization, filters, and administration functions.
		 *
		 * @since     1.0
		 */
		public function __construct( $prefix, $notices = array(), $activation = array() ) {

			$this->prefix = $prefix;
			if ( ! empty( $activation ) ) { $this->activation = $activation; }
			$this->user = array();

			// Display activation notice when plugin is activated or display notices to specific user from DB
			if ( ! get_option( $this->prefix . '_activated' ) ) {

				update_option( $prefix . '_activated', true );
				$this->display_activation();

				$this->notices = $notices;
				update_option( $this->prefix . '_typewheel_notices', $this->notices );

			} else {

				$this->notices = get_option( $this->prefix . '_typewheel_notices', array() );

				$this->process_user();

				$this->display();

			}

		} // end constructor

		/*---------------------------------------------------------------------------------*
		 * Public Functions
		 *---------------------------------------------------------------------------------*/

		 /**
 		 * Check user and set/get notices
 		 *
 		 * @since    1.0
 		 */
 		public function process_user() {

 			$current_user = wp_get_current_user();

			$this->user['ID'] = $current_user->ID;

 			// Get the notice options from the user
 			$this->user['notices'] = get_user_meta( $this->user['ID'], $this->prefix . '_typewheel_notices', true );

 			// If not yet set, then set the usermeta as an array
			if ( '' == $this->user['notices'] ) {
				add_user_meta( $this->user['ID'], $this->prefix . '_typewheel_notices', array(), true );
				$this->user['notices'] = array();
			}

 			// Create specific notices if they do not exist, otherwise set to current notice state
 			foreach ( $this->notices as $notice => $args ) {

 				if ( ! isset( $this->user['notices'][ $notice ] ) ) {

 					$this->user['notices'][ $notice ] = array(
 						'trigger' => $args['trigger'],
 						'time'    => $args['time'],
 						);

 				}

 			}

 			// Update the user meta
 			update_user_meta( $this->user['ID'], $this->prefix . '_typewheel_notices', $this->user['notices'] );

		}

		/**
		 * Displays active plugin notices.
		 *
		 * @since    1.0
		 */
		public function display() {

			global $pagenow;

			// If page parameter exists, append to $pagenow for finer control of where to show notices
			$page = isset( $_GET['page'] ) ? $pagenow . '?page=' . $_GET['page'] : $pagenow;

			$html = '';

			// Loop though the notices
			foreach ( $this->notices as $notice => $args ) {

				// Set capability for viewing notice, defaulting to `read`
				$cap = ( isset( $args['capability'] ) && '' != $args['capability'] ) ? $args['capability'] : 'read';
				$type = ( isset( $args['type'] ) && '' != $args['type'] ) ? $args['type'] : 'info';

				// Check that the notice is supposed to be displayed on this page and is active for the user
				if ( in_array( $page, $args['location'] ) && $this->user['notices'][ $notice ]['trigger'] && $this->user['notices'][ $notice ]['time'] < time() && current_user_can( $cap ) ) {

					// Assemble the notice
					$html .= '<div id="' . $notice . '-typewheel-notice" class="notice notice-' . $args['type'] . ' typewheel-notice' . '" style="' . esc_attr( $this->styles( $args['style'] ) ) . '">';
						$html .= '<p>';
							$html .= $this->get_dismissals( $notice, $args['dismiss'] );
							$html .= isset( $args['icon'] ) && '' != $args['icon'] ? '<i class="dashicons dashicons-' . $args['icon'] . ' featured-icon"></i>' : '';
							$html .= apply_filters( $notice . '_typewheel_notice_content', $args['content'], $notice );
						$html .= '</p>';
					$html .= '</div>';

				}

			}

			echo $html;

		} // end display_notices

		/**
		 * Displays activation notice.
		 *
		 * @since    1.0
		 */
		public function display_activation() {

			global $pagenow;

			// Set capability for viewing notice, defaulting to `read`
			$cap = ( isset( $this->activation['capability'] ) && '' != $this->activation['capability'] ) ? $this->activation['capability'] : 'read';

			// Check that the notice is supposed to be displayed on this page and is active for the user
			if ( $pagenow == 'plugins.php' && current_user_can( $cap ) ) {

				// Assemble notice
				$html = '<div id="activation-typewheel-notice" class="notice notice-info typewheel-notice' . '" style="' . esc_attr( $this->styles( $this->activation['style'] ) ) . '">';
					$html .= '<p>';
						$html .= isset( $this->activation['icon'] ) && '' != $this->activation['icon'] ? '<i class="dashicons dashicons-' . $this->activation['icon'] . ' featured-icon"></i>' : '';
						$html .= apply_filters( 'activation_typewheel_notice_content', $this->activation['content'] );
					$html .= '</p>';
				$html .= '</div>';

				echo $html;

			}

		} // end display_activation

		/**
		 * Assemble the styles from array or string
		 *
		 * @since    1.0
		 */
		private function styles( $styles ) {

			if ( is_array( $styles ) ) {

				$style = '';

				foreach ( $styles as $att => $value ) {

					$style .= $att . ':' . $value . ';';

				}

				return $style;

			} else {

				return $styles;

			}

		}

		/**
		 * Assemble and return any assigned dismissal notices
		 *
		 * @since    1.0
		 */
		public function get_dismissals( $notice, $dismiss ) {

			$html = '<span id="' . $notice . '-typewheel-notice-dismissals" style="float:right;">';

			foreach ( $dismiss as $dismissal ) {
				if ( 'week' == $dismissal ) {
					$html .= __( '<i class="dashicons dashicons-calendar" data-user="' . $this->user['ID'] . '" data-plugin="' . $this->prefix . '" data-notice="' . $notice . '" data-dismissal-duration="' . $dismissal . '" title="Remind me in one week"></i>', 'typewheel-locale' );
				} elseif ( 'month' == $dismissal ) {
					$html .= __( '<i class="dashicons dashicons-calendar-alt" data-user="' . $this->user['ID'] . '" data-plugin="' . $this->prefix . '" data-notice="' . $notice . '" data-dismissal-duration="' . $dismissal . '" title="Remind me in one month"></i>', 'typewheel-locale' );
				}
			}

			$html .= __( '<i class="dashicons dashicons-dismiss" data-user="' . $this->user['ID'] . '" data-plugin="' . $this->prefix . '" data-notice="' . $notice . '" data-dismissal-duration="forever" title="Hide forever" style="font-size: 15px; position: relative; top: 3px;"></i>', 'typewheel-locale' );

			$html .= '</span>';

			return $html;

		}

	}
}

// AJAX processing of dismissals
if ( ! function_exists( 'typewheel_notices_process' ) ) {

	add_action( 'wp_ajax_dismiss_notice', 'typewheel_notices_process' );
	function typewheel_notices_process() {

		$duration = $_POST['typewheel_notice_duration'];
		$notice = $_POST['typewheel_notice'];
		$plugin = $_POST['typewheel_notice_plugin'];
		$userid = $_POST['typewheel_user'];

		// Get the notice options from the user
		$user = get_user_meta( $userid, $plugin . '_typewheel_notices', true );

		switch ( $duration ) {
			case 'week':
				$user[ $notice ]['time'] = time() + 604800;
				break;
			case 'month':
				$user[ $notice ]['time'] = time() + 2592000;
				break;
			case 'forever':
				$user[ $notice ]['trigger'] = FALSE;
				break;
			case 'undismiss':
				foreach ( $user as $name => $args ) {
					$user[ $name ]['trigger'] = TRUE;
					$user[ $name ]['time'] = time() - 5;
				}
				break;
			default:
				break;
		}

		// Update the user meta
		update_user_meta( $userid, $plugin . '_typewheel_notices', $user );

		$response = array( 'success' => true, 'notice' => $notice, 'plugin' => $plugin );

		wp_send_json( $response );

	}
}
