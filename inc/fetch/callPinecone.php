<?php

add_action( 'wp_ajax_sage_ai_call_pinecone_upsert', 'sage_ai_call_pinecone_upsert' );

function sage_ai_call_pinecone_upsert( $content ) {

	$settings = get_option( 'wp_ai_content_gen_settings' );
	if ( empty( $settings ) || empty( $settings['api_key'] ) ) {
		return 'API Key not set';
	}
	$apiKey = $settings['api_key'];

	$open_ai = new OpenAi( $apiKey );

	$response = $open_ai->embeddings(
		array(
			'model' => 'text-embedding-ada-002',
			'input' => 'batman',
		)
	);

	$response = json_decode( $response );
	// error_log( print_r( $response->data[0]->embedding, true ) );
	if ( ! empty( $response->data[0]->embedding ) ) {

		wp_send_json_success( $response->data[0]->embedding );
	}

	wp_send_json_error( $response );
}