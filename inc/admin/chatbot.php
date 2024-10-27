<?php



require ABSPATH . 'wp-load.php';

add_action( 'wp_ajax_sage_ai_update_chatbot_settings', 'sage_ai_update_chatbot_settings' );
// delete_option( 'wp_ai_content_chatbot_settings' );
// var_dump( get_option( 'wp_ai_content_chatbot_settings' ) );


function sage_ai_update_chatbot_settings() {

	$chatbot_data = $_POST['chatbotData'];
	$chatbot_data = stripslashes( $chatbot_data );

	$new_chatbot_data = json_decode( $chatbot_data, true );

	$wp_ai_content_chatbot_settings   = get_option( 'wp_ai_content_chatbot_settings' );
	$updated_content_chatbot_settings = array();

	if ( ! empty( $wp_ai_content_chatbot_settings ) ) { // chatbot settings exist in database
		foreach ( $wp_ai_content_chatbot_settings as $wp_ai_content_chatbot_setting ) {
			if ( $wp_ai_content_chatbot_setting['mainSettings']['id'] === $new_chatbot_data['mainSettings']['id'] ) { // current chatbot which is being updated
				$updated_content_chatbot_settings[] = $new_chatbot_data;
			} else {
				$updated_content_chatbot_settings[] = $wp_ai_content_chatbot_setting;
			}
		}
	} else { // very first instanse of saving settings in database
		$updated_content_chatbot_settings[] = $new_chatbot_data;
	}

	update_option( 'wp_ai_content_chatbot_settings', $updated_content_chatbot_settings );

	wp_send_json_success( $new_chatbot_data );
}

add_action( 'wp_ajax_sage_ai_save_chat_log', 'sage_ai_save_chat_log' );
add_action( 'wp_ajax_noprivsage_ai_save_chat_log', 'sage_ai_save_chat_log' );

function sage_ai_save_chat_log() {

	$chatbot_log_data = $_POST['chatLogData'];
	
	$chatbot_log_data = stripslashes( $chatbot_log_data );

	$chatbot_log_data = json_decode( $chatbot_log_data, true );

	$chat_id = $chatbot_log_data['chatId'];

	$chat             = $chatbot_log_data['chat'];
	$chatbot_response = $chatbot_log_data['chatApiResponse'];

	$wp_ai_content_chatbot_log_data = get_option( 'wp_ai_content_chatbot_log_data', array() );

	$chat_data = isset( $wp_ai_content_chatbot_log_data[ $chat_id ] ) ? $wp_ai_content_chatbot_log_data[ $chat_id ] : array();

	if ( empty( $chat_id ) ) {
		// create chat id.
		$chat_id = isset( $chatbot_response['data']['id'] ) ? $chatbot_response['data']['id'] : sage_ai_chatbot_uniqid();
	}

	if ( empty( $chat_data ) ) {

		$chat_data['tokens']    = isset( $chatbot_response['data']['usage']['total_tokens'] ) ? (int) $chatbot_response['data']['usage']['total_tokens'] : 0;
		$chat_data['timestamp'] = ( new DateTime() )->getTimestamp();
		$chat_data['model']     = isset( $chatbot_response['data']['model'] ) ? $chatbot_response['data']['model'] : 'N/A';

	} else {
		$chat_data['tokens'] = isset( $chatbot_response['data']['usage']['total_tokens'] ) ? $chat_data['tokens'] + (int) $chatbot_response['data']['usage']['total_tokens'] : $chat_data['tokens'];
	}

	// Create a formatted string from the array
	$formattedString = '';

	foreach ( $chat as $message ) {
		$formattedString .= $message['role'] . ': ' . $message['content'] . "\n";
	}

	// Path to the WordPress upload folder.
	$wp_upload  = wp_upload_dir();
	$upload_dir = $wp_upload['basedir'];
	$upload_url = $wp_upload['baseurl'];

	$chat_log_dir_path = array( 'sage-ai-writer', 'chatbot', 'log' );
	$chat_log_dir      = sage_ai_writer_create_dir( $chat_log_dir_path );

	// Specify the file name and path
	$chat_file_name     = $chat_id . '.txt';
	$chat_log_file_path = $chat_log_dir . '/' . $chat_file_name;

	// Write the formatted string to the text file
	file_put_contents( $chat_log_file_path, $formattedString );

	// we were saving log files directly under sage-ai-writer dir
	$chat_log_file_url = $upload_url . '/sage-ai-writer/' . $chat_file_name;

	// now we are saving it under log in chatbot.
	if ( ! file_exists( $chat_log_file_url ) ) {
		$chat_log_file_url = $upload_url . '/sage-ai-writer/chatbot/log/' . $chat_file_name;
	}

	$chat_data['log_file'] = $chat_log_file_url;

	$wp_ai_content_chatbot_log_data[ $chat_id ] = $chat_data;
	update_option( 'wp_ai_content_chatbot_log_data', $wp_ai_content_chatbot_log_data );

	$response = array(
		'chatId' => $chat_id,
	);
	wp_send_json_success( $response );
}

/**
 * creates unique id for chatbot
 *
 * @param integer $lenght number
 * @return string 'unique id'
 */
function sage_ai_chatbot_uniqid( $lenght = 13 ) {
	// uniqid gives 13 chars, but you could adjust it to your needs.
	if ( function_exists( 'random_bytes' ) ) {
		$bytes = random_bytes( ceil( $lenght / 2 ) );
	} elseif ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
		$bytes = openssl_random_pseudo_bytes( ceil( $lenght / 2 ) );
	} else {
		throw new Exception( 'no cryptographically secure random function available' );
	}
	return substr( bin2hex( $bytes ), 0, $lenght );
}


add_action( 'wp_ajax_sage_ai_chatbot_log_data', 'sage_ai_chatbot_log_data' );

function sage_ai_chatbot_log_data() {

	$wp_ai_content_chatbot_log_data = get_option( 'wp_ai_content_chatbot_log_data', array() );

	if ( empty( $wp_ai_content_chatbot_log_data ) ) {

		wp_send_json_error( 'data not found' );
	}

	wp_send_json_success( $wp_ai_content_chatbot_log_data );
}

add_action( 'wp_ajax_sage_ai_chatbot_log_delete_chat', 'sage_ai_chatbot_log_delete_chat' );

function sage_ai_chatbot_log_delete_chat() {

	$chat_id = $_POST['chatId'];

	$wp_ai_content_chatbot_log_data = get_option( 'wp_ai_content_chatbot_log_data', array() );

	// remove item from array.
	if ( isset( $wp_ai_content_chatbot_log_data[ $chat_id ] ) ) {
		unset( $wp_ai_content_chatbot_log_data[ $chat_id ] );
	}

	// Path to the WordPress upload folder.
	$wp_upload          = wp_upload_dir();
	$chat_log_file_path = $wp_upload['basedir'] . '/sage-ai-writer/' . $chat_id . '.txt';

	if ( file_exists( $chat_log_file_path ) ) {
		wp_delete_file( $chat_log_file_path );
	}

	update_option( 'wp_ai_content_chatbot_log_data', $wp_ai_content_chatbot_log_data );

	wp_send_json_success( $wp_ai_content_chatbot_log_data );
}

// add new chatbot
add_action( 'wp_ajax_sage_ai_add_chatbot', 'sage_ai_add_chatbot' );

function sage_ai_add_chatbot() {
	$chatbot_data = $_POST['chatbotsData'];
	$chatbot_data = stripslashes( $chatbot_data );

	$new_chatbot_data = json_decode( $chatbot_data, true );

	// $wp_ai_content_chatbot_settings = get_option( 'wp_ai_content_chatbot_settings' );

	// $wp_ai_content_chatbot_settings[] = $new_chatbot_data;

	update_option( 'wp_ai_content_chatbot_settings', $new_chatbot_data );

	wp_send_json_success( $new_chatbot_data );
}

// delete chatbot
add_action( 'wp_ajax_sage_ai_delete_chatbot', 'sage_ai_delete_chatbot' );

function sage_ai_delete_chatbot() {
	$chatbot_data = $_POST['updatedChatbots'];
	$chatbot_data = stripslashes( $chatbot_data );

	$new_chatbot_data = json_decode( $chatbot_data, true );

	// $wp_ai_content_chatbot_settings = get_option( 'wp_ai_content_chatbot_settings' );

	// $wp_ai_content_chatbot_settings[] = $new_chatbot_data;

	update_option( 'wp_ai_content_chatbot_settings', $new_chatbot_data );

	wp_send_json_success( $new_chatbot_data );
}



add_action( 'wp_ajax_nopriv_sage_ai_upload_audio_file', 'sage_ai_upload_audio_file' );
add_action( 'wp_ajax_sage_ai_upload_audio_file', 'sage_ai_upload_audio_file' );

function sage_ai_upload_audio_file() {

	$chatbot_id = isset( $_POST['chatbotId'] ) ? $_POST['chatbotId'] : '';
	$settings   = get_option( 'wp_ai_content_gen_settings' );

	$data       = file_get_contents( $_FILES['audio']['tmp_name'] );
	$audioName  = sage_ai_chatbot_uniqid() . '.wav';
	$upload_dir = wp_upload_dir();

	$dir_path   = array( 'sage-ai-writer', 'chatbot', 'audio', $chatbot_id );
	$uploadPath = sage_ai_writer_create_dir( $dir_path );

	// audio file directory
	$file_upload_dir = $uploadPath . '/' . $audioName;

	// write file with data.
	$fp = fopen( $uploadPath . '/' . $audioName, 'wb' );
	fwrite( $fp, $data );
	fclose( $fp );

	$whisper_text = '';
	if ( file_exists( $file_upload_dir ) ) {
		$whisper_text = sage_ai_call_whisper( $file_upload_dir );
		// after the call delete the audio file
		wp_delete_file( $file_upload_dir );
	} else {
		wp_send_json_error( 'Audio File not found' );
	}

	$response = array(
		'text' => $whisper_text,
		// 'audio_url' => $file_upload_url,
	);

	wp_send_json_success( $response );
}

add_action( 'wp_ajax_sage_ai_chatbot_save_custom_icon', 'sage_ai_chatbot_save_custom_icon' );

function sage_ai_chatbot_save_custom_icon() {

	if ( ! empty( $_FILES['customIcon']['name'] ) ) {

		$icon_file  = $_FILES['customIcon'];
		$upload_dir = wp_upload_dir();

		$icon_path_structure  = array( 'sage-ai-writer', 'chatbot', 'icons' );
		$icon_upload_path_dir = sage_ai_writer_create_dir( $icon_path_structure );

		$fileName = basename( $icon_file['name'] );
		$fileName = str_replace( ' ', '_', $fileName );

		// Generate a unique filename based on coupon ID
		$target_file = $icon_upload_path_dir . '/' . $fileName;

		if ( file_exists( $target_file ) ) {
			wp_delete_file( $target_file );
		}

		// Move the uploaded file to the target directory
		if ( move_uploaded_file( $icon_file['tmp_name'], $target_file ) ) {

			$icon_url = $upload_dir['baseurl'] . '/sage-ai-writer/chatbot/icons/' . $fileName;

			wp_send_json_success( $icon_url );
		}
	}

	wp_send_json_error( 'Icon not received' );
}



function sage_ai_writer_create_dir( $path = array() ) {

	$upload_path = wp_upload_dir();
	$upload_dir  = $upload_path['basedir'];
	$path_folder = $upload_dir;

	if ( ! empty( $path ) ) {

		foreach ( $path as $dir ) {

			$path_folder .= '/' . $dir;

			if ( ! file_exists( $path_folder ) ) {
				mkdir( $path_folder );
			}
		}
	}

	return $path_folder;
}
