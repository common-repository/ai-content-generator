<?php


// add_action('wp_ajax_sage_ai_upload_url_to_media', 'sage_ai_upload_url_to_media');


function sage_ai_upload_url_to_media( $images_data ) {
	// $imageURLs = explode(',', $_POST['urls']);

	$uploaded_image_urls = array();

	foreach ( $images_data as $image_data ) {

		$title       = '';
		$alt         = '';
		$description = '';
		$caption     = '';

		if ( is_string( $image_data ) ) {
			$image_url = $image_data;
		} elseif ( is_array( $image_data ) ) {
			$image_url   = $image_data['url'];
			$title       = $image_data['title'];
			$alt         = $image_data['alt'];
			$description = $image_data['description'];
			$caption     = $image_data['caption'];
		} else {
			$image_url = '';
		}

		// Extract the file extension from the image URL
		$path          = parse_url( $image_url, PHP_URL_PATH );
		$fileExtension = pathinfo( $path, PATHINFO_EXTENSION );

		// Create a unique file name for the image with the original file extension
		$filename = uniqid() . '.' . $fileExtension;

		// Get the contents of the image from the URL
		$imageData = file_get_contents( $image_url );

		// Upload the image to the WordPress uploads directory
		$upload = wp_upload_bits( $filename, null, $imageData );

		// Check if the upload was successful
		if ( ! $upload['error'] ) {
			// Include the WordPress media management library
			require_once ABSPATH . 'wp-admin/includes/image.php';

			// Generate attachment metadata and create a post in the media library
			$attachmentData = array(
				'file'           => $upload['file'],
				'post_mime_type' => $upload['type'],
				'post_title'     => isset( $title ) ? $title : sanitize_file_name( $filename ),
				'post_excerpt'   => $caption,
				'post_content'   => $description,
				'post_status'    => 'inherit',
			);

			$attachmentId = wp_insert_attachment( $attachmentData, $upload['file'] );

			// Generate attachment metadata
			$attachmentMetadata = wp_generate_attachment_metadata( $attachmentId, $upload['file'] );

			// Update the attachment metadata
			wp_update_attachment_metadata( $attachmentId, $attachmentMetadata );

			// Update attachment metadata (Alternative Text, Title, Caption, Description)
			update_post_meta( $attachmentId, '_wp_attachment_image_alt', $alt );

			// Get the image URL
			$imageUrl = wp_get_attachment_url( $attachmentId );

			// Return the attachment ID
			$uploaded_image_urls[] = $imageUrl;

		} else {
			return( $upload['error'] );
		}
	}

	return( $uploaded_image_urls );
}

add_action( 'wp_ajax_sage_ai_upload_images_to_media', 'sage_ai_upload_images_to_media' );

function sage_ai_upload_images_to_media() {

	$images_data = stripslashes( $_POST['imagesData'] );

	$images_data = json_decode( $images_data, true );

	// error_log( print_r( $images_data, true ) );

	$images_media_urls = sage_ai_upload_url_to_media( $images_data );

	if ( ! empty( $images_media_urls ) ) {
		wp_send_json_success( $images_media_urls );
	}

	wp_send_json_error( 'error' );
}

function sage_ai_upload_csv( $files ) {

	if ( ! empty( $files['file']['name'] ) ) {

		$csv_file   = $files['file'];
		$upload_dir = wp_upload_dir();

		$csv_path_structure  = array( 'sage-ai-writer', 'bulk', 'csv_files' );
		$csv_upload_path_dir = sage_ai_writer_create_dir( $csv_path_structure );

		$fileName = basename( $csv_file['name'] );
		$fileName = str_replace( ' ', '_', $fileName );

		// Generate a unique filename
		$target_file = $csv_upload_path_dir . '/' . $fileName;

		if ( file_exists( $target_file ) ) {
			wp_delete_file( $target_file );
		}

		// Move the uploaded file to the target directory
		if ( move_uploaded_file( $csv_file['tmp_name'], $target_file ) ) {

			$csv_url = $upload_dir['baseurl'] . '/sage-ai-writer/bulk/csv_files/' . $fileName;

			return $csv_url;
		}
	}
		return false;
}



add_action( 'wp_ajax_sage_ai_read_csv', 'sage_ai_read_csv' );

function sage_ai_read_csv() {

	if ( isset( $_FILES['file'] ) ) {

		$csv_data = array();

		// Set up the upload arguments
		$upload_overrides = array( 'test_form' => false );
		$file_url         = sage_ai_upload_csv( $_FILES );

		if ( ! empty( $file_url ) ) {

			// Read the CSV file
			$file_handle = fopen( $file_url, 'r' );

			if ( $file_handle !== false ) {
				while ( ( $data = fgetcsv( $file_handle ) ) !== false ) {
					// Process each row of the CSV data
					$csv_data[] = $data;
					// Move the file pointer to the next line
					rewind( $file_handle );
				}

				fclose( $file_handle );

				// Delete the CSV file
				if ( file_exists( $file_url ) ) {
					unlink( $file_url );
				}
			} else {
				wp_send_json_error( 'error reading csv file' );
			}

			wp_send_json_success( $csv_data );

		} else {
			// Error occurred during file upload
			wp_send_json_error( 'Error uploading file' );
		}
	}
	wp_die();
}
