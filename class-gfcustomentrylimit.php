<?php

GFForms::include_addon_framework();

class GFCustomEntryLimit extends GFAddOn {

	protected $_version = GF_CUSTOM_ENTRY_LIMIT_VERSION;
	protected $_min_gravityforms_version = '1.9';
	protected $_slug = 'customentrylimit';
	protected $_path = 'customentrylimit/customentrylimit.php';
	protected $_full_path = __FILE__;
	protected $_title = 'Typewheel: Gravity Forms Custom Entry Limit';
	protected $_short_title = 'Custom Entry Limit';

	private static $_instance = null;

	/**
	 * Get an instance of this class.
	 *
	 * @return GFCustomEntryLimit
	 */
	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GFCustomEntryLimit();
		}

		return self::$_instance;
	}

	/**
	 * Handles hooks and loading of language files.
	 */
	public function init() {
		parent::init();
		$this->add_tooltip( 'custom_entry_limit', sprintf(
            '<h6>%s</h6> %s',
            __( 'Summed Fields As Entries', 'gravityperks' ),
            __( 'When limiting entries, this option will allow you to treat the sum of values for fields you have specified to be counted as form entries instead of the actual total of form entries submitted.', 'gravityperks' )
        ) );

	    load_plugin_textdomain( 'typewheel-gf-custom-entry-limit', false, basename( dirname( __file__ ) ) . '/languages/' );

        add_action( 'gform_field_advanced_settings', array( $this, 'add_setting' ), 10, 2 );

        add_action( 'gform_editor_js', array( $this, 'field_settings_js' ) );

        add_filter( 'gform_form_settings', array( $this, 'add_entry_option' ), 10, 2 );

        add_filter( 'gform_pre_form_settings_save', array( $this, 'save_entry_option' ), 10, 2 );

        add_filter( 'gform_pre_render', array( $this, 'render_sum' ) );

        add_filter( 'gform_shortcode_entries_left', array( $this, 'entries_left_shortcode' ), 10, 2 );
        add_filter( 'gform_shortcode_entry_count', array( $this, 'entry_count_shortcode' ), 10, 2 );

        add_filter( 'gform_validation', array( $this, 'validate_entry_margin' ) );
	}


	// # SCRIPTS & STYLES -----------------------------------------------------------------------------------------------

	/**
	 * Return the scripts which should be enqueued.
	 *
	 * @return array
	 */
	public function scripts() {
		$scripts = array(
			array(
				'handle'  => 'custom_entry_limit',
				'src'     => $this->get_base_url() . '/js/custom-entry-limit.js',
				'version' => $this->_version,
				'deps'    => array( 'jquery' ),
				'enqueue' => array(
					array(
						'admin_page' => array( 'form_settings' ),
					)
				)
			),

		);

		return array_merge( parent::scripts(), $scripts );
	}

	/**
	 * Return the stylesheets which should be enqueued.
	 *
	 * @return array
	 */
	public function styles() {
		return parent::styles();
	}


	// # FRONTEND FUNCTIONS --------------------------------------------------------------------------------------------

	/**
	 * Add the text in the plugin settings to the bottom of the form if enabled for this form.
	 *
	 * @param string $button The string containing the input tag to be filtered.
	 * @param array $form The form currently being displayed.
	 *
	 * @return string
	 */



	// # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

	/**
	 * Adds field setting to Perks tab
	 *
	 * @since    1.0.beta1
	 */
	function add_setting( $position, $form_id ) { if ( - 1 == $position ) {?>

			<li class="include-as-addend field_setting">
				<label for="visibility" class="section_label">
					<?php esc_html_e( 'Field Usage', 'typewheel-gf-custom-entry-limit' ); ?></label>

				<input type="checkbox" id="include-as-addend" value="1" onclick="SetFieldProperty( 'includeAsAddend', this.checked);">

				<label class="inline" for="include-as-addend">
					<?php
					esc_html_e( 'Include in sum for determining form entry limit', 'typewheel-gf-custom-entry-limit' );
					gform_tooltip( 'include-as-addend-toolip' );
					?>
				</label>

			</li>

	<?php }}

	/**
	 * Populates field setting value and controls display on specified field types
	 *
	 * @since    1.0.beta1
	 */
	public function field_settings_js() { ?>

		<script type="text/javascript">
			(function($) {
				$(document).bind( 'gform_load_field_settings', function( event, field, form ) {
					// populates the stored value from the field back into the setting when the field settings are loaded
					$( '#include-as-addend' ).attr( 'checked', field['includeAsAddend'] == true );
					// if our desired condition is met, we show the field setting; otherwise, hide it
					if( GetInputType( field ) == 'number' || GetInputType( field ) == 'singleproduct' || GetInputType( field ) == 'slider' ) {
						$( '.include-as-addend' ).show();
					} else {
						$( '.include-as-addend' ).hide();
					}
				} );
			})(jQuery);
		</script>

	<?php }

	/**
	 * Adds option to GF settings Restrictions section
	 *
	 * @since    1.0.beta1
	 */
	public function add_entry_option( $settings, $form ) {

		$settings['Restrictions'] =
			array_splice( $settings['Restrictions'], 0, 3, true ) +
			array( 'custom_entry_limit' =>
				'<tr id="custom_entry_limit" class="child_setting_row">
					<td colspan="2" class="gf_sub_settings_cell">
						<div class="gf_animate_sub_settings">
							<table>
								<tbody>
									<tr>
										<th><label for="form_limit_entries_custom">Summed Fields As Entries</label></th>
										<td><input value="summed-fields" id="form_limit_entries_custom" name="form_limit_entries_custom" type="checkbox" ' . checked( rgar($form, 'limitEntriesCustom'), 'summed-fields', false ) . '>Treat all values from specified fields as entries.</td>
									</tr>
								</tbody>
							</table>
						</div>
					</td>
				</tr>' ) +
			array_splice( $settings['Restrictions'], 3, count( $settings['Restrictions'] ) - 1, true );

		return $settings;

	}

	/**
	 * Saves option
	 *
	 * @since    1.0.beta1
	 */
	public function save_entry_option( $form ) {

		$form['limitEntriesCustom'] = rgpost( 'form_limit_entries_custom' );
		return $form;

	}

	/**
	 * Check current sum and return form object
	 *
	 * @since    0.1
	 */
	public function render_sum( $form ){

		if ( $form['limitEntries'] && 'summed-fields' == $form['limitEntriesCustom'] ) {

			if( $this->get_sum( $form ) >= $form['limitEntriesCount'] ) {

				add_filter( 'gform_get_form_filter', array( $this, 'limit_reached' ), 10, 2 );

			}

		}

		return $form;

	}

	/**
	 * Check current sum and return form object
	 *
	 * @since    0.1
	 */
	public function get_sum( $form ){

		$sum = 0;
		$leads = RGFormsModel::get_leads( $form['id'] );

		$addends = $this->get_addends($form);

		foreach( $leads as $id => $lead ) {

			$sum = $sum + $this->get_lead_sum( $lead, $addends );

		}

		return $sum;

	}

	/**
	 * Ensures field values will not be exceeding set entry limit
	 *
	 * @since    0.1
	 */
	public function validate_entry_margin( $validation_result ) {

		$form = $validation_result['form'];
		$current_page = rgpost( 'gform_source_page_number_' . $form['id'] ) ? rgpost( 'gform_source_page_number_' . $form['id'] ) : 1;
		$entry = GFFormsModel::get_current_lead();

		// Get number of remaining entries allowable
		$margin = $this->get_limit_margin($form);
		$addends = $this->get_addends($form);

		$entry_sum = $this->get_lead_sum($entry, $addends);

		if ( $entry_sum > $margin ) {

			$validation_result['is_valid'] = false;

			foreach( $form['fields'] as &$field ) {

				// If the field is not included as an addend, skip it
				if ( ! $field['includeAsAddend'] ) {
					continue;
				}

				// If the field is not on the current page OR if the field is hidden, skip it
				$field_page = $field->pageNumber;
				$is_hidden = RGFormsModel::is_field_hidden( $form, $field, array() );

				if ( $field_page != $current_page || $is_hidden ) {
					continue;
				}

				$field->failed_validation = true;
				$field->validation_message = add_filter( 'custom_entry_limit_will_exceed_message', 'Total exceeds allowable limit' );

			}

		}

		$validation_result['form'] = $form;

		return $validation_result;

	}

	/**
	 * Get the remaining allowable entries
	 *
	 * @since    0.1
	 */
	public function get_limit_margin( $form ) {

		if ( ! rgar( $form, 'limitEntriesCustom' ) ) {
			$entry_count = RGFormsModel::get_lead_count( $form['id'], '', null, null, null, null, 'active' );
		} elseif ( 'summed-fields' == rgar( $form, 'limitEntriesCustom' ) ) {
			$entry_count = $this->get_sum($form);
		}
		$entries_left = rgar( $form, 'limitEntriesCount' ) - $entry_count;

		return $entries_left;

	}

	/**
	 * Get the remaining allowable entries
	 *
	 * @since    0.1
	 */
	public function get_addends( $form ) {

		$addends = array();

		foreach ( $form['fields'] as $key => $field ) {

			if ( $field['includeAsAddend'] ) {

				$addends[ $field['id'] ] = $field['type'];

			}

		}

		return $addends;

	}

	/**
	 * Get the remaining allowable entries
	 *
	 * @since    0.1
	 */
	public function get_lead_sum( $lead, $addends ) {

		$sum = 0;

		foreach ( $addends as $addend => $type ) {

			switch ( $type ) {
				case 'number':
				case 'slider':
					$sum = $sum + (int) $lead[ $addend ];
					break;

				case 'product':
					$sum = $sum + (int) $lead[ $addend . '.3' ];

				default:
					# code...
					break;
			}

		}

		return $sum;

	}

	 /**
	 * Modify the limit reached message
	 *
	 * @since    0.1
	 */
	function limit_reached( $message, $form ) {

		if ( '' == $form['limitEntriesMessage'] ) {

			return '<div class="gf_submission_limit_message">Sorry. This form is no longer accepting new submissions.</div>';

		} else {

			return $form['limitEntriesMessage'];

		}

	}

	/**
	* Entries Left Shortcode
	* https://gravitywiz.com/shortcode-display-number-of-entries-left/
	*/

	function entries_left_shortcode( $output, $atts ) {

		extract( shortcode_atts( array(
			'id' => false,
			'format' => false // should be 'comma', 'decimal'
		), $atts ) );

		if( ! $id )
			return '';

		$form = RGFormsModel::get_form_meta( $id );
		if( ! rgar( $form, 'limitEntries' ) || ! rgar( $form, 'limitEntriesCount' ) )
			return '';

		$entries_left = $output = $this->get_limit_margin($form);

		if( $format ) {
			$format = $format == 'decimal' ? '.' : ',';
			$output = number_format( $entries_left, 0, false, $format );
		}

		return $entries_left > 0 ? $output : 0;

	}

	/**
	 * Gravity Wiz // Gravity Forms // Entry Count Shortcode
	 *
	 * Extends the [gravityforms] shortcode, providing a custom action to retrieve the total entry count and
	 * also providing the ability to retrieve counts by entry status (i.e. 'trash', 'spam', 'unread', 'starred').
	 *
	 * @version	  1.0
	 * @author    David Smith <david@gravitywiz.com>
	 * @license   GPL-2.0+
	 * @link      https://gravitywiz.com/shortcode-display-number-entries-submitted/
	 */

	function entry_count_shortcode( $output, $atts ) {

		extract( shortcode_atts( array(
			'id' => false,
			'status' => 'total', // accepts 'total', 'unread', 'starred', 'trash', 'spam', 'summed-fields'
			'format' => false // should be 'comma', 'decimal'
		), $atts ) );

		$valid_statuses = array( 'total', 'unread', 'starred', 'trash', 'spam', 'summed-fields' );

		if( ! $id || ! in_array( $status, $valid_statuses ) ) {
			return current_user_can( 'update_core' ) ? __( 'Invalid "id" (the form ID) or "status" (i.e. "total", "trash", etc.) parameter passed.' ) : '';
		}

		$form = RGFormsModel::get_form_meta( $id );

		if ( ! rgar( $form, 'limitEntriesCustom' ) ) {
			$counts = GFFormsModel::get_form_counts( $id );
			$output = rgar( $counts, $status );
		} elseif ( 'summed-fields' == rgar( $form, 'limitEntriesCustom' ) ) {
			$output = $this->get_sum($form);
		}

		if( $format ) {
			$format = $format == 'decimal' ? '.' : ',';
			$output = number_format( $output, 0, false, $format );
		}

		return $output;
	}

	public function add_tooltip( $key, $content ) {
		$this->tooltips[ $key ] = $content;
		add_filter( 'gform_tooltips', array( $this, 'load_tooltips' ) );
	}

	public function load_tooltips( $tooltips ) {
		return array_merge( $tooltips, $this->tooltips );
	}

}
