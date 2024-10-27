<?php

require WP_SAGE_AI_DIR . '/vendor/autoload.php'; // remove this line if you use a PHP Framework.

use Orhanerday\OpenAi\OpenAi;

function sage_ai_call_whisper( $file_upload_url ) {

	$settings = get_option( 'wp_ai_content_gen_settings' );
	if ( empty( $settings ) || empty( $settings['api_key'] ) ) {
		return 'API Key not set';
	}
	$apiKey = $settings['api_key'];

	$open_ai = new OpenAi( $apiKey );

	$c_file = curl_file_create( $file_upload_url );

	$response = $open_ai->transcribe(
		array(
			'model' => 'whisper-1',
			'file'  => $c_file,
		)
	);

	return $response;
}
