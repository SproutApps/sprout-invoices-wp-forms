<?php

class SI_WPForms extends SI_WPForms_Controller {
	const WPFORMS_FORM_ID = 'si_wpforms_invoice_submissions_id';
	const GENERATION = 'si_wpforms_record_generation';
	// Integration options
	protected static $wpforms_form_id;
	protected static $generation;

	public static function init() {
		// Store options
		self::$wpforms_form_id = get_option( self::WPFORMS_FORM_ID, 0 );
		self::$generation = get_option( self::GENERATION, 'estimate' );

		add_filter( 'si_settings_options', array( __CLASS__, 'add_settings_options' ) );

		add_action( 'si_settings_saved', array( get_class(), 'save_mappings' ) );

		add_filter( 'si_settings', array( __CLASS__, 'register_settings' ) );

		// plugin menu
		add_filter( 'plugin_action_links', array( __CLASS__, 'plugin_action_links' ), 10, 2 );

		if ( self::$wpforms_form_id ) {
			// Create invoice before confirmation
			add_action( 'wpforms_process_complete', array( __CLASS__, 'maybe_process_wpforms_form' ), 10, 4 );
		}
	}

	/**
	 * Add settings link to the plugin actions.
	 *
	 * @param  array  $actions Plugin actions.
	 * @param  string $plugin_file Path to the plugin file.
	 * @return array
	 */

	public static function plugin_action_links( $actions, $plugin_file ) {
		static $si_wp_forms;
		if ( ! isset( $plugin ) ) {
			$si_wp_forms = plugin_basename( SI_WP_FORMS_PLUGIN_FILE );
		}
		if ( $si_wp_forms === $plugin_file ) {
			$settings = array( 'settings' => '<a href="admin.php?page=sprout-invoices-addons#addons">' . __( 'Settings', 'General' ) . '</a>' );
			$actions  = array_merge( $settings, $actions );
		}
		return $actions;
	}

	public static function register_settings( $settings = array() ) {

		if ( ! function_exists( 'wpforms' ) ) {
			return;
		}

		$wpforms_options = array( 0 => __( 'No forms found', 'sprout-invoices' ) );
		$forms = wpforms()->form->get( '', array(
				'orderby' => 'title',
		) );
		if ( ! empty( $forms ) ) {
			$wpforms_options = array();
			foreach ( $forms as $key => $form ) {
				$wpforms_options[ $form->ID ] = ( ! isset( $form->post_title ) ) ? __( '(no title)', 'wpforms' ) : $form->post_title;
			}
		}

		$settings['wpforms_integration'] = array(
			'title' => __( 'WP Forms Submissions', 'sprout-invoices' ),
				'weight' => 6,
				'tab' => 'addons',
				'description' => sprintf( __( 'Refer to <a href="%s">this documentation</a> if you are unsure about these settings.', 'sprout-invoices' ), 'https://docs.sproutapps.co/article/8-integrating-gravity-forms-ninja-forms-or-custom-estimate-submissions' ),
				'settings' => array(
					self::WPFORMS_FORM_ID => array(
						'label' => __( 'WP Form', 'sprout-invoices' ),
						'option' => array(
							'type' => 'select',
							'options' => $wpforms_options,
							'default' => self::$wpforms_form_id,
							'description' => sprintf( __( 'Select the submission form built with <a href="%s">WP Forms</a>.', 'sprout-invoices' ), 'https://sproutapps.co/link/wpforms-forms' ),
						),
					),
					self::GENERATION => array(
						'label' => __( 'Submission Records', 'sprout-invoices' ),
						'option' => array(
							'type' => 'select',
							'options' => array( 'estimate' => __( 'Estimate', 'sprout-invoices' ), 'invoice' => __( 'Invoice', 'sprout-invoices' ), 'client' => __( 'Client Only', 'sprout-invoices' ) ),
							'default' => self::$generation,
							'description' => __( 'Select the type of records you would like to be created. Note: estimates and invoices create client records.', 'sprout-invoices' ),
						),
					),
					self::FORM_ID_MAPPING => array(
						'label' => __( 'WP Forms ID Mapping', 'sprout-invoices' ),
						'option' => array( __CLASS__, 'show_form_field_mapping' ),
					),
				),
		);

		return $settings;
	}

	public static function add_settings_options( $options = array() ) {
		$save_options = array();
		$form_mapping_options = get_option( self::FORM_ID_MAPPING, array() );
		$mapping_options = self::mapping_options();
		foreach ( $mapping_options as $key => $title ) {
			$value = ( isset( $form_mapping_options[ $key ] ) ) ? $form_mapping_options[ $key ] : '' ;
			$save_options[ SI_Settings_API::_sanitize_input_for_vue( 'si_invoice_sub_mapping_' . $key ) ] = $value;
		}
		return array_merge( $save_options, $options );
	}

	public static function show_form_field_mapping( $fields = array() ) {
		$fields = self::mapping_options();
		foreach ( $fields as $name => $label ) {
			$value = ( isset( self::$form_mapping[ $name ] ) ) ? self::$form_mapping[ $name ] : '' ;
			printf( '<div class="si_input_field_wrap si_field_wrap_input_select si_form_int"><label>%2$s</label><input v-model="vm.si_invoice_sub_mapping_%4$s" type="text" name="si_invoice_sub_mapping_%1$s" id="si_invoice_sub_mapping_%1$s" value="%3$s"></div><br/>', $name, $label, $value, SI_Settings_API::_sanitize_input_for_vue( $name ) );
		}

		printf( '<p class="description">%s</p>', __( 'Map the field IDs of your form to the data name.', 'sprout-invoices' ) );
	}

	public static function save_mappings() {
		$mappings = array();
		$fields = self::mapping_options();
		foreach ( $fields as $key => $label ) {
			$mappings[ $key ] = isset( $_POST[ 'si_invoice_sub_mapping_' . $key ] ) ? $_POST[ 'si_invoice_sub_mapping_' . $key ] : '';
		}
		update_option( self::FORM_ID_MAPPING, $mappings );
	}

	public static function mapping_options() {
		$options = array(
				'subject' => __( 'Subject/Title', 'sprout-invoices' ),
				'requirements' => __( 'Requirements', 'sprout-invoices' ),
				// 'line_item_list' => __( 'Pre-defined Item Selection (Checkboxes Field)', 'sprout-invoices' ),
				'email' => __( 'Email', 'sprout-invoices' ),
				'website' => __( 'Website', 'sprout-invoices' ),
				'client_name' => __( 'Client/Company Name', 'sprout-invoices' ),
				'full_name' => __( 'Name', 'sprout-invoices' ),
				'address' => __( 'Address', 'sprout-invoices' ),
			);
		return $options;
	}


	////////////////////
	// Process forms //
	////////////////////

	public static function maybe_process_wpforms_form( $fields, $entry, $form_data, $entry_id ) {
		/**
		 * Only a specific form do this process
		 */
		if ( (int) $form_data['id'] !== (int) self::$wpforms_form_id ) {
			return;
		}
		/**
		 * Set variables
		 * @var string
		 */
		$mapped_field_values = array();
		foreach ( $fields as $key => $field ) {
			$field_id = $field['id'];
			$map_key = array_search( $field_id, self::$form_mapping );
			if ( $map_key ) {
				if ( 'address' === $map_key ) {
					$mapped_field_values[ $map_key ] = array(
						'contact_street' => isset( $field['address1'] ) ? $field['address1'] . ' ' . $field['address2']  : '',
						'contact_city' => isset( $field['city'] ) ? $field['city'] : '',
						'contact_zone' => isset( $field['state'] ) ? $field['state'] : '',
						'contact_postal_code' => isset( $field['postal'] ) ? $field['postal'] : '',
						'contact_country' => isset( $field['country'] ) ? $field['country'] : '',
					);
				} else {
					$mapped_field_values[ $map_key ] = $field['value'];
				}
			}
		}

		$subject = isset( $mapped_field_values['subject'] ) ? $mapped_field_values['subject'] : '';
		$requirements = isset( $mapped_field_values['requirements'] ) ? $mapped_field_values['requirements'] : '';
		$email = isset( $mapped_field_values['email'] ) ? $mapped_field_values['email'] : '';
		$client_name = isset( $mapped_field_values['client_name'] ) ? $mapped_field_values['client_name'] : '';
		$full_name = isset( $mapped_field_values['full_name'] ) ? $mapped_field_values['full_name'] : '';
		$website = isset( $mapped_field_values['website'] ) ? $mapped_field_values['website'] : '';

		$address = isset( $mapped_field_values['address'] ) ? $mapped_field_values['address'] : '';

		$doc_id = 0;

		if ( 'invoice' === self::$generation ) {
			/**
			 * Create invoice
			 * @var array
			 */
			$invoice_args = array(
				'status' => SI_Invoice::STATUS_PENDING,
				'subject' => $subject,
				'fields' => $fields,
				'form' => $entry_id,
				'history_link' => sprintf( '<a href="%s">#%s</a>', add_query_arg( array( 'entry_id' => $entry_id ), admin_url( 'admin.php?page=wpforms-entries&view=details' ) ), $entry_id ),
			);
			$invoice = self::maybe_create_invoice( $invoice_args, $entry_id );
			$doc_id = $invoice->get_id();
		}

		if ( 'estimate' === self::$generation ) {
			/**
			 * Create estimate
			 * @var array
			 */
			$estimate_args = array(
				'status' => SI_Estimate::STATUS_PENDING,
				'subject' => $subject,
				'fields' => $fields,
				'form' => $entry_id,
				'history_link' => sprintf( '<a href="%s">#%s</a>', add_query_arg( array( 'entry_id' => $entry_id ), admin_url( 'admin.php?page=wpforms-entries&view=details' ) ), $entry_id ),
			);
			$estimate = self::maybe_create_estimate( $estimate_args, $entry_id );
			$doc_id = $estimate->get_id();
		}

		/**
		 * Make sure an invoice was created, if so create a client
		 */
		$client_args = array(
			'email' => $email,
			'client_name' => $client_name,
			'full_name' => $full_name,
			'website' => $website,
			'contact_street' => isset( $address['contact_street'] ) ? $address['contact_street'] : '',
			'contact_city' => isset( $address['contact_city'] ) ? $address['contact_city'] : '',
			'contact_zone' => isset( $address['contact_zone'] ) ? $address['contact_zone'] : '',
			'contact_postal_code' => isset( $address['contact_postal_code'] ) ? $address['contact_postal_code'] : '',
			'contact_country' => isset( $address['contact_country'] ) ? $address['contact_country'] : '',
		);

		if ( 'estimate' === self::$generation ) {
			$client_args = apply_filters( 'si_estimate_submission_maybe_process_wpforms_client_args', $client_args, $fields, $entry_id, $form_data['id'] );
			$doc = $estimate;
		} elseif ( 'invoice' === self::$generation ) {
			$client_args = apply_filters( 'si_invoice_submission_maybe_process_wpforms_client_args', $client_args, $fields, $entry_id, $form_data['id'] );
			$doc = $invoice;
		}

		self::maybe_create_client( $doc, $client_args );

		do_action( 'si_wpforms_submission_complete', $doc_id );

		self::maybe_redirect_after_submission( $doc_id );
	}

	public static function maybe_redirect_after_submission( $doc_id ) {
		if ( apply_filters( 'si_invoice_submission_redirect_to_invoice', false ) ) {
			if ( get_post_type( $doc_id ) == ( SI_Invoice::POST_TYPE || SI_Estimate::POST_TYPE ) ) {
				$url = get_permalink( $doc_id );
				wp_redirect( $url );
				die();
			}
		}
	}
}
SI_WPForms::init();
