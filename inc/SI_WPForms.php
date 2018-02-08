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

		add_filter( 'si_add_options', array( __CLASS__, 'remove_integration_addon_option' ) );

		// filter options
		self::register_settings();

		if ( self::$wpforms_form_id ) {
			// Create invoice before confirmation
			add_action( 'wpforms_process_complete', array( __CLASS__, 'maybe_process_wpforms_form' ), 10, 4 );
		}
	}

	///////////////
	// Settings //
	///////////////

	public static function remove_integration_addon_option( $options = array() ) {
		// remove the integration addon ad
		unset( $options['settings']['estimate_submissions'] );
		return $options;
	}

	public static function register_settings() {

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

		$settings = array(
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
				'sanitize_callback' => array( __CLASS__, 'save_wpforms_form_field_mapping' ),
			),
		);

		$all_settings = array(
			'form_submissions' => array(
				'title' => __( 'WP Forms Submissions', 'sprout-invoices' ),
				'weight' => 6,
				'tab' => 'settings',
				'settings' => $settings,
			),
		);

		do_action( 'sprout_settings', $all_settings );
	}

	public static function show_form_field_mapping() {
		$mappings = array(
			'subject' => isset( self::$form_mapping['subject'] ) ? self::$form_mapping['subject'] : '',
			'requirements' => isset( self::$form_mapping['requirements'] ) ? self::$form_mapping['requirements'] : '',
			'email' => isset( self::$form_mapping['email'] ) ? self::$form_mapping['email'] : '',
			'client_name' => isset( self::$form_mapping['client_name'] ) ? self::$form_mapping['client_name'] : '',
			'first_name' => isset( self::$form_mapping['first_name'] ) ? self::$form_mapping['first_name'] : '',
			'last_name' => isset( self::$form_mapping['last_name'] ) ? self::$form_mapping['last_name'] : '',
			'full_name' => isset( self::$form_mapping['full_name'] ) ? self::$form_mapping['full_name'] : '',
			'website' => isset( self::$form_mapping['website'] ) ? self::$form_mapping['website'] : '',
			'contact_address' => isset( self::$form_mapping['contact_address'] ) ? self::$form_mapping['contact_address'] : '',
			'contact_street' => isset( self::$form_mapping['contact_street'] ) ? self::$form_mapping['contact_street'] : '',
			'contact_city' => isset( self::$form_mapping['contact_city'] ) ? self::$form_mapping['contact_city'] : '',
			'contact_zone' => isset( self::$form_mapping['contact_zone'] ) ? self::$form_mapping['contact_zone'] : '',
			'contact_postal_code' => isset( self::$form_mapping['contact_postal_code'] ) ? self::$form_mapping['contact_postal_code'] : '',
			'contact_country' => isset( self::$form_mapping['contact_country'] ) ? self::$form_mapping['contact_country'] : '',
		);
		self::wpforms_options( $mappings );
	}

	public static function wpforms_options( $mappings = array() ) {
		$form = wpforms()->form->get( self::$wpforms_form_id );
		if ( empty( $form ) ) {
			printf( '<p class="description">%s</p>', __( 'Select the form and save before mapping the inputs.', 'sprout-invoices' ) );
		}
		print '<p>';
		foreach ( self::mapping_options() as $key => $label ) {
			printf( '<label>%1$s %2$s</label><br/>', self::wpforms_forms_select_options( $key, $mappings[ $key ] ), $label );
		}
		print '</p>';
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

	public static function wpforms_forms_select_options( $input_name = '', $selected = 0 ) {
		$form = wpforms()->form->get( self::$wpforms_form_id );
		$fields = wpforms_get_form_fields( $form );
		if ( empty( $fields ) ) {
			return '<code>&nbsp;&nbsp;&nbsp;</code>';
		}
		$option = sprintf( '<select type="select" name="sa_form_map_%s"><option></option>', $input_name );
		foreach ( $fields as $key => $field ) {
			$option .= sprintf( '<option value="%s" %s>%s (%s)</option>', $field['id'], selected( $selected, $field['id'], false ), $field['label'], $field['type'] );
		}
		$option .= '</select>';
		return $option;
	}

	public static function save_wpforms_form_field_mapping() {
		$mappings = array();
		foreach ( self::mapping_options() as $name => $label ) {
			$mappings[ $name ] = isset( $_POST[ 'sa_form_map_'.$name ] ) ? $_POST[ 'sa_form_map_'.$name ] : '';
		}
		return $mappings;
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
			'contact_street' => $address['contact_street'],
			'contact_city' => $address['contact_city'],
			'contact_zone' => $address['contact_zone'],
			'contact_postal_code' => $address['contact_postal_code'],
			'contact_country' => $address['contact_country'],
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
