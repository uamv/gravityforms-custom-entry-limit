<?php
/**
 * Class:       Typewheel Update
 * Description: Checks available updates for Typewheel plugins
 * Version:     1.0
 * Author:      Trevor Anderson
 * Author URI:  https://github.com/andtrev
 * License:     GPLv2 or later
 * Text Domain: typewheel-update
 * Domain Path: /languages
 *
 * (C) 2017, Trevor Anderson
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @package twxyzThemePluginUpdater
 * @version 1.0
 */

if ( ! class_exists( 'twxyzThemePluginUpdate') ) {

	/**
	 * Updater manager class.
	 *
	 * Bootstraps the plugin.
	 *
	 * @since 1.0.0
	 */
	class twxyzThemePluginUpdate {

		/**
		 * Update api url.
		 *
		 * @since  1.0.0
		 * @access private
		 * @var string
		 */
		private $api_url = 'https://my.typewheel.xyz/wp-json/';

		/**
		 * Theme and plugin header for update id.
		 *
		 * @since  1.0.0
		 * @access private
		 * @var string
		 */
		private $id_header = 'Typewheel Update ID';

		/**
		 * twxyzThemePluginUpdate instance.
		 *
		 * @since  1.0.0
		 * @access private
		 * @static
		 * @var twxyzThemePluginUpdate
		 */
		private static $instance = false;

		/**
		 * Get the instance.
		 *
		 * Returns the current instance, creates one if it
		 * doesn't exist. Ensures only one instance of
		 * twxyzThemePluginUpdate is loaded or can be loaded.
		 *
		 * @since 1.0.0
		 * @static
		 *
		 * @return twxyzThemePluginUpdate
		 */
		public static function get_instance() {

			if ( ! self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;

		}

		/**
		 * Constructor.
		 *
		 * Initializes and adds functions to filter and action hooks.
		 *
		 * @since 1.0.0
		 */
		public function __construct() {

			add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
			add_action( 'admin_menu', array( $this, 'add_admin_menu_page' ) );
			add_filter( 'extra_plugin_headers', array( $this, 'extra_headers' ) );
			add_filter( 'extra_theme_headers', array( $this, 'extra_headers' ) );
			add_filter( 'site_transient_update_themes', array( $this, 'add_theme_updates' ) );
			add_filter( 'transient_update_themes', array( $this, 'add_theme_updates' ) );
			add_filter( 'site_transient_update_plugins', array( $this, 'add_plugin_updates' ) );
			add_filter( 'transient_update_plugins', array( $this, 'add_plugin_updates' ) );
			add_action( 'load-plugins.php', array( $this, 'admin_plugins' ), 30 );
			add_action( 'core_upgrade_preamble', array( $this, 'fix_dashboard_updates_view_version_links' ) );

		}

		/**
		 * Load text domain.
		 *
		 * Attached to the plugins_loaded action.
		 *
		 * @since 1.0.0
		 */
		public function load_plugin_textdomain() {

			load_plugin_textdomain( 'typewheel-update', false, basename( dirname( __FILE__ ) ) . '/languages/' );

		}

		/**
		 * Add ID header to plugins and themes.
		 *
		 * Attached to the extra_plugin_headers and extra_theme_headers filters.
		 *
		 * @since 1.0.0
		 *
		 * @param array $headers Plugin and theme headers.
		 *
		 * @return array Plugin and theme headers.
		 */
		public function extra_headers( $headers ) {

			$headers['twxyzUpdateID'] = $this->id_header;

			return $headers;

		}

		/**
		 * Add admin page.
		 *
		 * Adds the upload settings page as settings sub-menu.
		 *
		 * @since 1.0.0
		 */
		public function add_admin_menu_page() {

			$hook_suffix = add_options_page(
				__( 'Typewheel Update', 'typewheel-update' ),
				__( 'Typewheel Update', 'typewheel-update' ),
				'update_plugins',
				'twxyz-update',
				array( $this, 'admin_page' )
			);

			if ( false !== $hook_suffix ) {
				add_action( "load-{$hook_suffix}", array( $this, 'admin_save_update_keys' ) );
			}
		}

		/**
		 * Admin page.
		 *
		 * Outputs upload settings admin page.
		 *
		 * @since 1.0.0
		 */
		public function admin_page() {

			if ( ! current_user_can( 'update_plugins' ) ) {
				return;
			}

			$updates = get_option( 'twxyz_updates' );
			if ( empty( $updates ) ) {
				$updates = $this->get_updates();
			}

			$updatable = array(
				'plugins' => array(),
				'themes'  => array(),
			);

			$installed = get_plugins();
			foreach ( $installed as $id => $plugin ) {
				if ( ! empty( $plugin[ $this->id_header ] ) ) {
					$expires = __( 'No info', 'typewheel-update' );
					if ( isset( $updates['plugins'][ $id ]->expires, $updates['cache_time'] ) ) {
						if ( $updates['plugins'][ $id ]->expires < 0 ) {
							$expires = __( 'Never', 'typewheel-update' );
						} else {
							$expires = ( (int) $updates['plugins'][ $id ]->expires - floor( ( time() - $updates['cache_time'] ) / DAY_IN_SECONDS ) ) . ' ' . __( 'days', 'typewheel-updater' );
						}
					}
					$updatable['plugins'][] = array(
						'id'     => absint( $plugin[ $this->id_header ] ),
						'name'   => $plugin['Name'],
						'notice' => isset( $updates['plugins'][ $id ] ) ?
							'<span class="dashicons dashicons-yes" style="color:green;width:18px;height:18px;font-size:18px;"></span> ' . $expires :
							'<span class="dashicons dashicons-no" style="color:red;width:18px;height:18px;font-size:18px;"></span> ' .
							'<span style="color:red;">' . __( 'No license', 'typewheel-updater' ) . '</span>',
					);
				}
			}

			$installed = wp_get_themes();
			foreach ( $installed as $theme ) {
				$theme_id_header = $theme->get( $this->id_header );
				if ( ! empty( $theme_id_header ) ) {
					$id      = $theme->get_stylesheet();
					$expires = __( 'No info', 'typewheel-update' );
					if ( isset( $updates['themes'][ $id ]['expires'], $updates['cache_time'] ) ) {
						if ( $updates['themes'][ $id ]['expires'] < 0 ) {
							$expires = __( 'Never', 'typewheel-update' );
						} else {
							$expires = ( (int) $updates['themes'][ $id ]['expires'] - floor( ( time() - $updates['cache_time'] ) / DAY_IN_SECONDS ) ) . ' ' . __( 'days', 'typewheel-updater' );
						}
					}
					$updatable['themes'][] = array(
						'id'     => absint( $theme_id_header ),
						'name'   => $theme->Name,
						'notice' => isset( $updates['themes'][ $id ] ) ?
							'<span class="dashicons dashicons-yes" style="color:green;width:18px;height:18px;font-size:18px;"></span> ' . $expires :
							'<span class="dashicons dashicons-no" style="color:red;width:18px;height:18px;font-size:18px;"></span> ' .
							'<span style="color:red;">' . __( 'No license', 'typewheel-updater' ) . '</span>',
					);
				}
			}

			?>
			<style>
				.theme-plugin-list {
					width: 400px;
					max-width: 100%;
				}

				.tpl-title-container {
					width: 100%;
					border-bottom: 2px solid #0073aa;
				}

				.tpl-info-container {
					border-bottom: 1px dashed #555;
				}

				.tpl-title {
					padding: 4px 2%;
					width: 46%;
					float: left;
				}

				.tpl-info {
					padding: 8px 2%;
					width: 46%;
					float: left;
				}
			</style>
			<div class="wrap">
				<h1 class="wp-heading-inline"><?php esc_html_e( 'Typewheel Update Settings', 'typewheel-update' ); ?></h1>
				<hr class="wp-header-end">
				<?php if ( ! empty( $updates['update_key']['error']['message'] ) ) : ?>
					<div class="notice notice-error">
						<p>
							<?php echo esc_html( $updates['update_key']['error']['message'] ); ?>
						</p>
					</div>
				<?php endif; ?>
				<h3><?php esc_html_e( 'Themes', 'typewheel-update' ); ?></h3>
				<?php if ( ! empty( $updatable['themes'] ) ) : ?>
					<div class="theme-plugin-list">
						<div class="tpl-title-container">
							<div class="tpl-title">
								<?php esc_html_e( 'Name', 'typewheel-update' ); ?>
							</div>
							<div class="tpl-title">
								<?php esc_html_e( 'License / Expiration', 'typewheel-update' ); ?>
							</div>
							<div class="clear"></div>
						</div>
						<div class="tpl-info-container">
							<?php foreach ( $updatable['themes'] as $theme ) : ?>
								<div class="tpl-info">
									<?php echo esc_html( $theme['name'] ); ?>
								</div>
								<div class="tpl-info">
									<?php echo $theme['notice']; ?>
								</div>
								<div class="clear"></div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php else : ?>
					<p>
						<?php esc_html_e( 'No themes found.', 'typewheel-update' ); ?>
					</p>
				<?php endif; ?>
				<br>
				<h3><?php esc_html_e( 'Plugins', 'typewheel-update' ); ?></h3>
				<?php if ( ! empty( $updatable['plugins'] ) ) : ?>
					<div class="theme-plugin-list">
						<div class="tpl-title-container">
							<div class="tpl-title">
								<?php esc_html_e( 'Name', 'typewheel-update' ); ?>
							</div>
							<div class="tpl-title">
								<?php esc_html_e( 'License / Expiration', 'typewheel-update' ); ?>
							</div>
							<div class="clear"></div>
						</div>
						<div class="tpl-info-container">
							<?php foreach ( $updatable['plugins'] as $plugin ) : ?>
								<div class="tpl-info">
									<?php echo esc_html( $plugin['name'] ); ?>
								</div>
								<div class="tpl-info">
									<?php echo $plugin['notice']; ?>
								</div>
								<div class="clear"></div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php else : ?>
					<p>
						<?php esc_html_e( 'No plugins found.', 'typewheel-update' ); ?>
					</p>
				<?php endif; ?>
				<br>
				<form action="<?php echo esc_url( admin_url( 'options-general.php?page=twxyz-update' ) ); ?>" method="post">
					<?php wp_nonce_field( 'twxyz_save_update_keys', 'twxyz_update_nonce' ); ?>
					<h3>
						<label for="twxyz_update_key"><?php esc_html_e( 'Update Key', 'typewheel-update' ); ?></label>
					</h3>
					<input id="twxyz_update_key" name="twxyz_update_key" type="text" style="width:300px;max-width:85%;" value="<?php echo esc_attr( ! empty( $updates['update_key']['key'] ) ? $updates['update_key']['key'] : '' ); ?>">
					<?php if ( ! empty( $updates['update_key']['key'] ) && isset( $updates['update_key']['found'] ) ) :
						echo $updates['update_key']['found'] ? '<span class="dashicons dashicons-yes" style="color:green;width:27px;height:27px;font-size:27px;"></span>' : '<span class="dashicons dashicons-no" style="color:red;width:27px;height:27px;font-size:27px;"></span>';
					endif; ?>
				<?php if ( ! empty( $updates['update_key']['key'] ) && ! empty( $updates['update_key']['disabled'] ) ) : ?>
					<p>
						<span class="dashicons dashicons-no" style="color:red;"></span> <span style="color:red;"><?php esc_html_e( 'Updates for this key have been disabled.', 'typewheel-update' ); ?></span><br>
						<?php esc_html_e( 'Please contact the site\'s support with any questions or for more information.', 'typewheel-update' ); ?>
					</p>
				<?php endif; ?>
				<p>
					<input type="submit" class="button button-primary button-large" name="twxyz_save_key" value="<?php esc_attr_e( 'Save Changes / Reload Updates', 'typewheel-update' ); ?>">
				</p>
				</form>
				<br>
				<h3><span class="dashicons dashicons-sos" style="color:#d54e21;"></span> <a href="https://talk.typewheel.xyz/"><?php esc_html_e( 'Support', 'typewheel-update' ); ?></a></h3>
			</div>
			<?php

		}

		/**
		 * Save admin page changes.
		 *
		 * Save update key changes, if any. Will always reload updates from the update server.
		 *
		 * @since 1.0.0
		 */
		public function admin_save_update_keys() {

			if ( current_user_can( 'update_plugins' ) && isset( $_POST['twxyz_update_key'], $_POST['twxyz_update_nonce'] ) && wp_verify_nonce( $_POST['twxyz_update_nonce'], 'twxyz_save_update_keys' ) ) {
				$update_key = sanitize_text_field( $_POST['twxyz_update_key'] );
				$this->get_updates( $update_key );
			}
		}

		/**
		 * Get updates from update server.
		 *
		 * Pass $update_key to update the key and reload updates from the update server.
		 * Otherwise a cached response from the update server may be returned.
		 *
		 * @since 1.0.0
		 *
		 * @global string $wp_version  WordPress version.
		 *
		 * @param string  $update_key  Optional. Update key. Default null.
		 *
		 * @return array {
		 * Response from update server, update key and error info.
		 *
		 * @type array    $plugins     {
		 * Plugin updates.
		 *
		 * @type object   $id          {
		 * Update product info, array key is the product id.
		 *
		 * @type string   $package     Download file url.
		 * @type string   $url         Update info url.
		 * @type string   $new_version Update version.
		 * @type bool     $autoupdate  Should product be updated automatically?
		 * @type int      $expires     Amount of days the license will expire in, -1 for never.
		 * }
		 * }
		 * @type array    $themes      {
		 * Theme updates.
		 *
		 * @type array    $id          {
		 * Update product info, array key is the product id.
		 *
		 * @type string   $package     Download file url.
		 * @type string   $url         Update info url.
		 * @type string   $new_version Update version.
		 * @type bool     $autoupdate  Should product be updated automatically?
		 * @type int      $expires     Amount of days the license will expire in, -1 for never.
		 * }
		 * }
		 * @type array    $update_key  {
		 * Update key and error info.
		 *
		 * @type string   $key         Update key.
		 * @type bool     $found       If update key is found.
		 * @type bool     $disabled    If update key is disabled.
		 * @type array    $error       {
		 * Error info.
		 *
		 * @type string   $code        Error code (INVALID_UPDATE_KEY_FORMAT or SERVER_ERROR).
		 * @type string   $message     Human readable error message.
		 * }
		 * }
		 * }
		 */
		public function get_updates( $update_key = null ) {

			$doing_cron   = wp_doing_cron();
			$current_time = time();
			$cache_expire = 12 * HOUR_IN_SECONDS;
			if ( $doing_cron ) {
				$cache_expire = 0;
			} elseif ( is_admin() && function_exists( 'get_current_screen' ) ) {
				$screen = get_current_screen();
				if ( isset( $screen->id ) && ( 'update-core' === $screen->id || 'plugins' === $screen->id || 'themes' === $screen->id || 'settings_page_twxyz-update' === $screen->id ) ) {
					$cache_expire = HOUR_IN_SECONDS;
				}
			}
			$cache_expire = $current_time - $cache_expire;
			$updates      = get_option( 'twxyz_updates' );

			if ( null === $update_key && ! empty( $updates['cache_time'] ) && $updates['cache_time'] > $cache_expire ) {
				return $updates;
			}

			global $wp_version;
			$updates_request = array(
				'update_key' => '',
			);

			if ( null !== $update_key ) {
				$updates_request['update_key'] = $update_key;
			} else {
				if ( ! empty( $updates['update_key'] ) ) {
					$updates_request['update_key'] = $updates['update_key']['key'];
				}
			}

			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$installed = get_plugins();
			foreach ( $installed as $id => $plugin ) {
				if ( ! empty( $plugin[ $this->id_header ] ) ) {
					$update_id                                  = absint( $plugin[ $this->id_header ] );
					$updates_request['ids'][]                   = $update_id;
					$updates_request['versions'][ $update_id ]  = $plugin['Version'];
					$updates_request['wp_id'][ $update_id ]     = $id;
					$updates_request['is_plugin'][ $update_id ] = true;
				}
			}

			$installed = wp_get_themes();
			foreach ( $installed as $theme ) {
				$theme_id_header = $theme->get( $this->id_header );
				if ( ! empty( $theme_id_header ) ) {
					$update_id                                  = absint( $theme->get( $this->id_header ) );
					$updates_request['ids'][]                   = $update_id;
					$updates_request['versions'][ $update_id ]  = $theme->Version;
					$updates_request['wp_id'][ $update_id ]     = $theme->get_stylesheet();
					$updates_request['is_plugin'][ $update_id ] = false;
				}
			}

			$updates['plugins'] = array();
			$updates['themes']  = array();
			unset( $updates['update_key']['error'] );

			if ( isset( $updates_request['ids'] ) && count( $updates_request['ids'] ) > 0 ) {
				if ( $doing_cron ) {
					$timeout = 30;
				} else {
					/* Three seconds, plus one extra second for every 10 plugins */
					$timeout = 3 + (int) ( count( $updates_request['ids'] ) / 10 );
				}

				$options = array(
					'timeout'    => $timeout,
					'body'       => array(
						'ids'           => implode( ',', $updates_request['ids'] ),
						'versions'      => implode( ',', $updates_request['versions'] ),
						'update_key'    => isset( $updates_request['update_key'] ) ? $updates_request['update_key'] : '',
						'activation_id' => get_bloginfo( 'url' ),
					),
					'user-agent' => 'WordPress/' . $wp_version . ' PHP/' . phpversion() . ' (' . php_uname( 's' ) . ';)',
				);

				$raw_response = wp_remote_post( trailingslashit( $this->api_url ) . 'tuxedo-updater/v1/get-updates/', $options );

				if ( is_wp_error( $raw_response ) || 200 !== wp_remote_retrieve_response_code( $raw_response ) ) {
					$updates['update_key']['error'] = array(
						'code'    => 'SERVER_ERROR',
						'message' => __( 'Updates unavailable. Something may be wrong with the update server or this server&#8217;s configuration.', 'typewheel-update' ),
					);
					$updates['update_key']['found'] = false;
					unset( $updates['update_key']['disabled'] );
				} else {
					$response = json_decode( wp_remote_retrieve_body( $raw_response ), true );

					$updates['update_key'] = $response['update_key'];
					unset( $response['update_key'] );

					foreach ( $response as $id => $item ) {
						if ( isset( $updates_request['is_plugin'][ $id ] ) ) {
							if ( true === $updates_request['is_plugin'][ $id ] ) {
								$updates['plugins'][ $updates_request['wp_id'][ $id ] ]         = (object) $item;
								$updates['plugins'][ $updates_request['wp_id'][ $id ] ]->plugin = $updates_request['wp_id'][ $id ];
							}
							if ( false === $updates_request['is_plugin'][ $id ] ) {
								$updates['themes'][ $updates_request['wp_id'][ $id ] ]          = $item;
								$updates['themes'][ $updates_request['wp_id'][ $id ] ]['theme'] = $updates_request['wp_id'][ $id ];
							}
						}
					}
				}
			} // End if().

			$updates['update_key']['key'] = $updates_request['update_key'];
			if ( isset( $updates['update_key']['error']['code'] ) && 'INVALID_UPDATE_KEY_FORMAT' === $updates['update_key']['error']['code'] ) {
				if ( empty( $updates['update_key']['key'] ) ) {
					$updates['update_key']['error']['message'] = '';
				} else {
					$updates['update_key']['error']['message'] = __( 'Invalid update key format. Please try entering your update key again.', 'typewheel-update' );
				}
			}
			$updates['cache_time'] = $current_time;

			update_option( 'twxyz_updates', $updates, false );

			return $updates;

		}

		/**
		 * Add plugin updates to WordPress updates.
		 *
		 * Attached to the site_transient_update_plugins and transient_update_plugins filters.
		 *
		 * @since 1.0.0
		 *
		 * @param object $value WordPress plugin update info.
		 *
		 * @return object
		 */
		public function add_plugin_updates( $value ) {

			if ( ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) || ! isset( $value->response ) ) {
				return $value;
			}

			$updates = $this->get_updates();
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$installed = get_plugins();
			foreach ( $updates['plugins'] as $id => $plugin ) {
				if ( version_compare( $installed[ $id ]['Version'], $plugin->new_version, '>=' ) ) {
					unset( $updates['plugins'][ $id ] );
				}
			}
			$value->response = array_merge( $value->response, $updates['plugins'] );

			return $value;

		}

		/**
		 * Add theme updates to WordPress updates.
		 *
		 * Attached to the site_transient_update_themes and transient_update_themes filters.
		 *
		 * @since 1.0.0
		 *
		 * @param object $value WordPress theme update info.
		 *
		 * @return object
		 */
		public function add_theme_updates( $value ) {

			if ( ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) || ! isset( $value->response ) ) {
				return $value;
			}

			$updates = $this->get_updates();
			$installed = wp_get_themes();
			foreach ( $updates['themes'] as $id => $theme ) {
				if ( version_compare( $installed[ $id ]->Version, $theme['new_version'], '>=' ) ) {
					unset( $updates['themes'][ $id ] );
				}
			}
			$value->response = array_merge( $value->response, $updates['themes'] );

			return $value;

		}

		/**
		 * Fix view version links in Dashboard -> Updates.
		 *
		 * @since 1.0.0
		 */
		public function fix_dashboard_updates_view_version_links() {

			?>
			<script>
				jQuery(document).ready(function($){
					<?php $updates = $this->get_updates(); ?>
					<?php foreach ( $updates['plugins'] as $id => $plugin ) : ?>
					$('input[value="<?php echo esc_attr( $id ); ?>"]').parent().next('.plugin-title').find('.open-plugin-details-modal').attr('href','<?php echo esc_attr( $plugin->url ); ?>').removeClass('thickbox open-plugin-details-modal');
					<?php endforeach; ?>
				});
			</script>
			<?php

		}

		/**
		 * Take over admin plugin update info.
		 *
		 * Switch from standard WordPress update info and add any error messages
		 * for plugins we are updating. Attached to the load-plugins.php action.
		 *
		 * @since 1.0.0
		 */
		public function admin_plugins() {

			$updates = get_option( 'twxyz_updates' );
			$installed = get_plugins();
			foreach ( $updates['plugins'] as $id => $plugin ) {
				if ( version_compare( $installed[ $id ]['Version'], $plugin->new_version, '<' ) ) {
					remove_action( "after_plugin_row_{$plugin->plugin}", 'wp_plugin_update_row', 10 );
					add_action( "after_plugin_row_{$plugin->plugin}", array( $this, 'plugin_update_row' ), 10, 2 );
				}
			}

			foreach ( $installed as $id => $plugin ) {
				if ( ! empty( $plugin[ $this->id_header ] ) && empty( $updates['plugins'][ $id ] ) ) {
					add_action( "after_plugin_row_{$id}", array( $this, 'plugin_error_row' ), 10, 2 );
				}
			}
		}

		/**
		 * Output update info for plugins admin page.
		 *
		 * @since 1.0.0
		 *
		 * @param string $file        Plugin basename.
		 * @param array  $plugin_data Plugin info.
		 *
		 * @return bool|void
		 */
		public function plugin_update_row( $file, $plugin_data ) {

			$updates = get_option( 'twxyz_updates' );
			if ( ! isset( $updates['plugins'][ $file ] ) ) {
				return false;
			}

			$response = $updates['plugins'][ $file ];

			$plugins_allowedtags = array(
				'a'       => array( 'href' => array(), 'title' => array() ),
				'abbr'    => array( 'title' => array() ),
				'acronym' => array( 'title' => array() ),
				'code'    => array(),
				'em'      => array(),
				'strong'  => array(),
			);

			$plugin_name = wp_kses( $plugin_data['Name'], $plugins_allowedtags );
			$details_url = '';
			if ( ! empty( $response->url ) ) {
				$details_url = $response->url;
			} elseif ( ! empty( $plugin_data['PluginURI'] ) ) {
				$details_url = $plugin_data['PluginURI'];
			} elseif ( ! empty( $plugin_data['AuthorURI'] ) ) {
				$details_url = $plugin_data['AuthorURI'];
			}
			$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );

			if ( is_network_admin() || ! is_multisite() ) {
				if ( is_network_admin() ) {
					$active_class = is_plugin_active_for_network( $file ) ? ' active' : '';
				} else {
					$active_class = is_plugin_active( $file ) ? ' active' : '';
				}

				echo '<tr class="plugin-update-tr' . $active_class . '" id="' . esc_attr( $file . '-update' ) . '" data-slug="' . esc_attr( $file ) . '" data-plugin="' . esc_attr( $file ) . '"><td colspan="' . esc_attr( $wp_list_table->get_column_count() ) . '" class="plugin-update colspanchange"><div class="update-message notice inline notice-warning notice-alt"><p>';

				if ( ! current_user_can( 'update_plugins' ) ) {
					/* translators: 1: plugin name */
					printf( __( 'There is a new version of %1$s available.', 'typewheel-update' ), $plugin_name );
					if ( ! empty( $details_url ) ) {
						/* translators: 1: details URL, 2: additional link attributes, 3 version number */
						printf( __( ' <a href="%1$s" %2$s>View version %3$s details</a>.', 'typewheel-update' ),
							esc_url( $details_url ),
							sprintf( 'aria-label="%s"',
								/* translators: 1: plugin name, 2: version number */
								esc_attr( sprintf( __( 'View %1$s version %2$s details', 'typewheel-update' ), $plugin_name, $response->new_version ) )
							),
							$response->new_version
						);
					}
				} elseif ( empty( $response->package ) ) {
					/* translators: 1: plugin name */
					printf( __( 'There is a new version of %1$s available.', 'typewheel-update' ), $plugin_name );
					if ( ! empty( $details_url ) ) {
						/* translators: 1: details URL, 2: additional link attributes, 3 version number */
						printf( __( ' <a href="%1$s" %2$s>View version %3$s details</a>.', 'typewheel-update' ),
							esc_url( $details_url ),
							sprintf( 'aria-label="%s"',
								/* translators: 1: plugin name, 2: version number */
								esc_attr( sprintf( __( 'View %1$s version %2$s details', 'typewheel-update' ), $plugin_name, $response->new_version ) )
							),
							$response->new_version
						);
					}
					echo '<em>' . __( 'Automatic update is unavailable for this plugin.', 'typewheel-update' ) . '</em>';
				} else {
					/* translators: 1: plugin name */
					printf( __( 'There is a new version of %1$s available.', 'typewheel-update' ), $plugin_name );
					if ( ! empty( $details_url ) ) {
						/* translators: 1: details URL, 2: additional link attributes, 3 version number */
						printf( __( ' <a href="%1$s" %2$s>View version %3$s details</a>, or ', 'typewheel-update' ),
							esc_url( $details_url ),
							sprintf( 'aria-label="%s"',
								/* translators: 1: plugin name, 2: version number */
								esc_attr( sprintf( __( 'View %1$s version %2$s details', 'typewheel-update' ), $plugin_name, $response->new_version ) )
							),
							$response->new_version
						);
					}
					/* translators: 1: update URL, 2: additional link attributes */
					printf( __( ' <a href="%1$s" %2$s>Update now</a>.', 'typewheel-updater' ),
						wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $file, 'upgrade-plugin_' . $file ),
						sprintf( 'class="update-link" aria-label="%s"',
							/* translators: %s: plugin name */
							esc_attr( sprintf( __( 'Update %s now', 'typewheel-update' ), $plugin_name ) )
						)
					);
				}

				/** This action is documented in wp-admin/includes/update.php */
				do_action( "in_plugin_update_message-{$file}", $plugin_data, $response );

				echo '</p></div></td></tr>';
			}
		}

		/**
		 * Output error info for plugins admin page.
		 *
		 * @since 1.0.0
		 *
		 * @param string $file        Plugin basename.
		 * @param array  $plugin_data Plugin info.
		 *
		 * @return bool|void
		 */
		public function plugin_error_row( $file, $plugin_data ) {

			$updates       = get_option( 'twxyz_updates' );
			$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );

			if ( is_network_admin() || ! is_multisite() ) {
				if ( is_network_admin() ) {
					$active_class = is_plugin_active_for_network( $file ) ? ' active' : '';
				} else {
					$active_class = is_plugin_active( $file ) ? ' active' : '';
				}

				echo '<tr class="plugin-update-tr' . $active_class . '" id="' . esc_attr( $file . '-update' ) . '" data-slug="' . esc_attr( $file ) . '" data-plugin="' . esc_attr( $file ) . '"><td colspan="' . esc_attr( $wp_list_table->get_column_count() ) . '" class="plugin-update colspanchange"><div class="update-message notice inline notice-alt notice-error"><p>';
				if ( isset( $updates['update_key']['error']['code'] ) && 'SERVER_ERROR' === $updates['update_key']['error']['code'] ) {
					echo esc_html( $updates['update_key']['error']['message'] );
					/* translators: 1: update settings url */
					printf( ' ' . __( '<a href="%s">Update settings</a>', 'typewheel-update' ), esc_url( admin_url( 'options-general.php?page=twxyz-update' ) ) );
				} else {
					/* translators: 1: update settings url */
					printf( __( 'Updates unavailable. No license found. <a href="%s">Update settings</a>', 'typewheel-update' ), esc_url( admin_url( 'options-general.php?page=twxyz-update' ) ) );
				}
				echo '</p></div></td></tr>';
			}
		}
	}

}

// Instantiate the plugin class.
$twxyz_theme_plugin_update = twxyzThemePluginUpdate::get_instance();
