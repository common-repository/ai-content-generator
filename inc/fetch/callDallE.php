<?php

require WP_SAGE_AI_DIR . '/vendor/autoload.php'; // remove this line if you use a PHP Framework.

use Orhanerday\OpenAi\OpenAi;

add_action( 'wp_ajax_sage_ai_call_dall_e', 'sage_ai_call_dall_e' );

function sage_ai_call_dall_e() {

	if ( ! isset( $_POST['prompt'] ) ) {
		wp_send_json_error( 'Prompt not set' );
	}

	$image_size = isset( $_POST['size'] ) ? $_POST['size'] : '';
	if ( empty( $image_size ) ) {
		$image_size = 'dall-e-3' === $image_source ? '1024x1024' : '256x256';
	}

	$prompt          = isset( $_POST['prompt'] ) ? $_POST['prompt'] : '';
	$images_required = isset( $_POST['imagesRequired'] ) ? (int) $_POST['imagesRequired'] : 1;
	$image_source    = isset( $_POST['imageSource'] ) ? $_POST['imageSource'] : 'dall-e-2';
	$upload_to_media = isset( $_POST['uploadToMedia'] ) ? $_POST['uploadToMedia'] : 'true';
	$hd_quality      = isset( $_POST['hdQuality'] ) ? $_POST['hdQuality'] : false;
	$image_style     = isset( $_POST['imageStyle'] ) ? $_POST['imageStyle'] : '';
	$image_urls      = sage_ai_get_dall_e_images( $prompt, $images_required, $image_size, $image_source, $upload_to_media, $hd_quality );

	wp_send_json_success( $image_urls );
}


function sage_ai_get_dall_e_images( $prompt = '', $images_required = 1, $image_size = '', $image_source = 'dall-e-2', $upload_to_media = 'true', $hd_quality = false ) {

	$image_urls          = array();
	$uploaded_image_urls = array();

	$settings = get_option( 'wp_ai_content_gen_settings' );
	$apiKey   = $settings['api_key'];
	if ( empty( $settings ) || empty( $apiKey ) ) {
		wp_send_json_error( 'API Key not set' );
	}

	// create no of images.
	$images_count = 'dall-e-3' === $image_source ? (int) $images_required : 1;

	for ( $image = 1; $image <= $images_count; $image++ ) {

		$images_required = 'dall-e-3' === $image_source ? 1 : $images_required;

		$open_ai = new OpenAi( $apiKey );

		$image_params = array(
			'model'           => $image_source,
			'prompt'          => $prompt,
			'n'               => $images_required,
			'size'            => $image_size,
			'response_format' => 'url',
		);

		// quality hd is only availabe in dalle-3
		if ( 'dall-e-3' === $image_source && $hd_quality === true ) {
			$image_params['quality'] = 'hd';
		}

		// style is only availabe in dalle-3
		if ( 'dall-e-3' === $image_source && ! empty( $image_style ) ) {
			$image_params['style'] = $image_style;
		}

		// error_log( print_r( $image_params, true ) );

		$completion = $open_ai->image(
			$image_params
		);
		$response   = json_decode( $completion );
		// handle error
		if ( isset( $response->error ) ) {
			wp_send_json_error( $response->error->message );
			return $response->error->message;
		}

		if ( ! empty( $response->data ) ) {
			foreach ( $response->data as $image_data ) {
				if ( isset( $image_data->url ) ) {
					$image_urls[] = $image_data->url;
				}
			}
		}
	}

	// if upload to media is enable only then save it to media and return it's links.
	// 'true' because after stringify boolean got converted to string.
	if ( ! empty( $image_urls ) && $upload_to_media === 'true' ) {
		$uploaded_image_urls = sage_ai_upload_url_to_media( $image_urls );
		return $uploaded_image_urls;
	}

	return $image_urls;
}
