<?php


class Sage_AI_Public_Chatbot {

	public function __construct() {

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		add_action( 'init', array( $this, 'sage_ai_public_init' ) );
	}

	function sage_ai_public_init() {

		add_shortcode( 'sage_ai_chatbot', array( $this, 'sage_ai_chatbot_shortcode_callback' ) );

		add_action( 'wp_footer', array( $this, 'sage_ai_create_chatbot_element' ) );
	}

	function sage_ai_create_chatbot_element() {

		$chatbot_settings     = get_option( 'wp_ai_content_chatbot_settings' );
		$sitewide_chatbot_ids = '';
		if ( ! empty( $chatbot_settings ) ) {
			foreach ( $chatbot_settings as $chatbot_setting ) {
				if ( isset( $chatbot_setting['appearanceSettings']['popup'] )
					&& $chatbot_setting['appearanceSettings']['popup'] === true && isset( $chatbot_setting['appearanceSettings']['showSitewide'] ) && $chatbot_setting['appearanceSettings']['showSitewide'] === true ) {
						$sitewide_chatbot_ids .= $chatbot_setting['mainSettings']['id'];
				}
			}
		}

		if ( ! empty( $sitewide_chatbot_ids ) ) {

			echo '<script> var wp_ai_content_chatbot_ids = ["' . $sitewide_chatbot_ids . '"] </script><div id="sage-ai-chatbot"></div>';
		}
	}

	function enqueue_scripts() {

		wp_register_script( 'sage_ai_public_content_writer_pro_script', WP_SAGE_AI_URL . '/build/index.js', array( 'react', 'react-dom', 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n', 'wp-plugins', 'wp-primitives', 'lodash' ), '', true );

		wp_register_style( 'sage_ai_public_content_writer_pro_style', WP_SAGE_AI_URL . '/build/index.css' );

		$chatbot_settings = get_option( 'wp_ai_content_chatbot_settings' );

		if ( ! empty( $chatbot_settings ) ) {
			foreach ( $chatbot_settings as $chatbot_setting ) {
				if ( isset( $chatbot_setting['appearanceSettings']['popup'] )
					&& $chatbot_setting['appearanceSettings']['popup'] === true && isset( $chatbot_setting['appearanceSettings']['showSitewide'] ) && $chatbot_setting['appearanceSettings']['showSitewide'] === true ) {

					wp_enqueue_style( 'sage_ai_public_content_writer_pro_style' );
					wp_enqueue_script( 'sage_ai_public_content_writer_pro_script' );
				}
			}
		}

		$settings_array = $this->sage_ai_chatbot_localize_data();

		wp_localize_script( 'sage_ai_public_content_writer_pro_script', 'wp_ai_content_var', json_encode( $settings_array ) );
	}

	function sage_ai_chatbot_shortcode_callback( $atts ) {

		$atts = shortcode_atts(
			array( 'id' => 'default' ),
			$atts
		);

		wp_enqueue_style( 'sage_ai_public_content_writer_pro_style' );
		wp_enqueue_script( 'sage_ai_public_content_writer_pro_script' );
		return '<script> var wp_ai_content_chatbot_ids = ["' . $atts['id'] . '"] </script><div id="sage-ai-chatbot"></div>';
	}


	function sage_ai_chatbot_localize_data() {

		$license_status = get_option( 'sage_ai_licenses_pro_status' );

		$pro_plugin = 'ai-content-generator-pro/ai-content-generator-pro.php';

		if ( false === $license_status && is_plugin_active( $pro_plugin ) ) {
			$license_status = 'invalid';
		}

		$settings = get_option( 'wp_ai_content_gen_settings' );

		// delete_option( 'wp_ai_content_chatbot_settings' );

		$chatbot_data = get_option( 'wp_ai_content_chatbot_settings' );

		$chatbot_data = isset( $chatbot_data ) ? $chatbot_data : array();

		$settings_array = array(
			'apiKey'            => ! empty( $settings['api_key'] ) ? $settings['api_key'] : '',
			'model'             => ! empty( $settings['model'] ) ? $settings['model'] : 'text-davinci-003',
			'temperature'       => ! empty( $settings['temperature'] ) ? $settings['temperature'] : '0.7',
			'max_tokens'        => ! empty( $settings['max_tokens'] ) ? $settings['max_tokens'] : '700',
			'top_p'             => ! empty( $settings['top_p'] ) ? $settings['top_p'] : '1',
			'best_of'           => ! empty( $settings['best_of'] ) ? $settings['best_of'] : '1',
			'frequency_penalty' => ! empty( $settings['frequency_penalty'] ) ? $settings['frequency_penalty'] : '0.01',
			'presence_penalty'  => ! empty( $settings['presence_penalty'] ) ? $settings['presence_penalty'] : '0.01',
			'image_size'        => ! empty( $settings['image_size'] ) ? $settings['image_size'] : '512x512',
			'sageAjaxUrl'       => admin_url( 'admin-ajax.php' ),
			'pluginUrl'         => WP_SAGE_AI_URL,
			'adminUrl'          => admin_url(),
			'chatbot'           => $chatbot_data,
			'licenses'          => array(
				'pro' => $license_status,
			),
		);

		return $settings_array;
	}
}

new Sage_AI_Public_Chatbot();
