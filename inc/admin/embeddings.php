<?php

// Allow AJAX actions for non-logged-in users
add_action( 'wp_ajax_save_sage_ai_embedding', 'save_sage_ai_embedding' );


function save_sage_ai_embedding() {

	$embedding_text = isset( $_POST['embeddingText'] ) ? $_POST['embeddingText'] : '';

	$post_id = wp_insert_post(
		array(
			'post_title'   => 'Your Post Title', // Modify as needed
			'post_type'    => 'sage-ai-embeddings',
			'post_content' => $embedding_text,
			'post_status'  => 'publish',
		)
	);

	if ( empty( $post_id ) ) {
		wp_send_json_error( 'not able to process this text.' );
	}

	wp_send_json_success( $post_id );
}

add_action( 'wp_ajax_sage_ai_save_embeddings_settings', 'sage_ai_save_embeddings_settings' );

function sage_ai_save_embeddings_settings() {

	$settings_data = isset( $_POST['settings'] ) ? $_POST['settings'] : '';
	$settings_data = stripslashes( $settings_data );
	$settings_data = json_decode( $settings_data, true );
	// Save settings
	$has_settings_updated = update_option( 'sage_ai_embeddings_settings', $settings_data );
	wp_send_json_success( 'Settings saved successfully.' );
}


add_action( 'wp_ajax_sage_ai_get_embeddings_entries', 'sage_ai_get_embeddings_entries' );


function sage_ai_get_embeddings_entries() {

	$args = array(
		'numberposts' => -1,
		'post_type'   => 'sage-ai-embeddings',
	);

	$posts = get_posts( $args );

	$embeddings_data = array();

	// error_log( print_r( $posts, true ) );

	if ( ! empty( $posts ) ) {

		foreach ( $posts as $post ) {
			$content = isset( $post->post_content ) ? $post->post_content : '';
			$date    = isset( $post->post_date ) ? $post->post_date : '';
			$id      = isset( $post->ID ) ? $post->ID : '';

			$embeddings_data[] = array(
				'content' => $content,
				'date'    => $date,
				'id'      => $id,
			);
		}
	}

	wp_send_json_success( $embeddings_data );
}
