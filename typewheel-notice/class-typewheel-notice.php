<?php

/**
* Registers the plugin's administrative stylesheets and JavaScript
*
* @since    1.0
*/
if ( ! function_exists( 'typewheel_notices_add_stylesheets_and_javascript' ) ) {
	function typewheel_notices_add_stylesheets_and_javascript() {

	   wp_enqueue_script( 'typewheel-notice', plugins_url( 'typewheel-notice/typewheel-notice.js', dirname(__FILE__) ), array( 'jquery' ) );
	   wp_localize_script( 'typewheel-notice', 'TypewheelNotice', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );

	} // end add_stylesheets_and_javascript
}
// Load the administrative Stylesheets and JavaScript
add_action( 'admin_enqueue_scripts', 'typewheel_notices_add_stylesheets_and_javascript' );

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
		 * Version
		 *
		 * @since    1.0
		 *
		 * @var      array
		 */
		public $version;

		/**
		 * Notices.
		 *
		 * @since    1.0
		 *
		 * @var      array
		 */
		public $prefix;

		/*---------------------------------------------------------------------------------*
		 * Consturctor
		 *---------------------------------------------------------------------------------*/

		/**
		 * Initialize the plugin by setting localization, filters, and administration functions.
		 *
		 * @since     1.0
		 */
		public function __construct( $prefix, $notices = array() ) {

			$this->prefix = $prefix;
			$this->user = array();

			// If passing new notices, then add them to the DB
			if ( ! empty( $notices ) ) {

				$this->notices = $notices;

				update_option( $this->prefix . '_typewheel_notices', $this->notices );

			}
			// Otherwise, pull notices from the DB
			else {

				$this->notices = get_option( $this->prefix . '_typewheel_notices', array() );

				$this->process_user();

				$this->display();

			}

		} // end constructor

		/*---------------------------------------------------------------------------------*
		 * Public Functions
		 *---------------------------------------------------------------------------------*/

		 /**
 		 * Returns the active plugin notices for display on the settings page summary.
 		 *
 		 * @since    1.0
 		 */
 		public function process_user() {

 			global $pagenow;
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

 			// Update the users meta
 			update_user_meta( $this->user['ID'], $this->prefix . '_typewheel_notices', $this->user['notices'] );

		}

		/**
		 * Returns the active plugin notices for display on the settings page summary.
		 *
		 * @since    1.0
		 */
		public function display() {

			global $pagenow;

			if ( isset( $_GET['page'] ) ) {
				$page = $pagenow . '?page=' . $_GET['page'];
			} else {
				$page = $pagenow;
			}

			$html = '<style>
                            .typewheel-notice i.dashicons.featured-icon {
                                margin: 0 9px 0 -3px;
                            }
							span[id$="-typewheel-notice-dismissals"] i.dashicons {
								margin: 0 0 0 9px;
							}
							span[id$="-typewheel-notice-dismissals"] i.dashicons:hover {
								cursor: pointer;
							}
					</style>';

			// Loop though the notices
			foreach ( $this->notices as $notice => $args ) {

				// Check that the notice is supposed to be displayed on this page and that it is active for the user
				if ( in_array( $page, $args['location'] ) && $this->user['notices'][ $notice ]['trigger'] && $this->user['notices'][ $notice ]['time'] < time() ) {
					if ( is_array( $args['style'] ) ) {
						$style = '';
						foreach ( $args['style'] as $att => $value ) {
							$style .= $att . ':' . $value . ';';
						}
					}

					$html .= '<div id="' . $notice . '-typewheel-notice" class="notice notice-' . $args['type'] . ' typewheel-notice' . '" style="' . esc_attr( $style ) . '">';
						$html .= '<p>';
							$html .= isset( $args['icon'] ) ? '<i class="dashicons dashicons-' . $args['icon'] . ' featured-icon"></i>' : '';
							$html .= apply_filters( $notice . '_typewheel_notice_content', $args['content'], $notice );
							$html .= $this->get_dismissals( $notice, $args['dismiss'] );
						$html .= '</p>';
					$html .= '</div>';

				}

			}

			echo $html;

		} // end display_notices


		/**
		 * Get any assigned dismissal notices
		 *
		 * @since    1.0
		 */
		public function get_dismissals( $notice, $dismiss ) {

			global $pagenow;

			$html = '<span id="' . $notice . '-typewheel-notice-dismissals" style="float:right;">';

			foreach ( $dismiss as $dismissal ) {
				if ( 'week' == $dismissal ) {
					$html .= __( '<i class="dashicons dashicons-calendar" data-user="' . $this->user['ID'] . '" data-plugin="' . $this->prefix . '" data-notice="' . $notice . '" data-dismissal-duration="' . $dismissal . '" title="Remind me in one week"></i>', 'typewheel-locale' );
				} elseif ( 'month' == $dismissal ) {
					$html .= __( '<i class="dashicons dashicons-calendar-alt" data-user="' . $this->user['ID'] . '" data-plugin="' . $this->prefix . '" data-notice="' . $notice . '" data-dismissal-duration="' . $dismissal . '" title="Remind me in one month"></i>', 'typewheel-locale' );
				}
			}

			$html .= __( '<i class="dashicons dashicons-no" data-user="' . $this->user['ID'] . '" data-plugin="' . $this->prefix . '" data-notice="' . $notice . '" data-dismissal-duration="forever" title="Hide forever"></i>', 'typewheel-locale' );

			$html .= '</span>';

			return $html;

		}

	}
}

// Process the form
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

		// Update the option
		update_user_meta( $userid, $plugin . '_typewheel_notices', $user );

		$response = array( 'success' => true, 'notice' => $notice, 'plugin' => $plugin );

		wp_send_json( $response );

	}
}
