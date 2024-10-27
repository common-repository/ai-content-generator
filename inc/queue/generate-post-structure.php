<?php

add_action( 'wp_ajax_sage_ai_generate_bulk_post_manual_call', 'sage_ai_generate_bulk_post_manual_call' );

function sage_ai_generate_bulk_post_manual_call() {

	$job = stripslashes( $_POST['articlesDetails'] );

	$settings   = stripslashes( $_POST['settings'] );
	$job        = json_decode( $job, true );
	$settings   = json_decode( $settings, true );
	$title      = $job['title'];
	$postType   = ! empty( $settings['postType'] ) ? $settings['postType'] : 'post';
	$taxonomies = isset( $settings['taxonomies'] ) ? $settings['taxonomies'] : array();

	// time in unix timestamp.
	$post_publish_date       = ! empty( $settings['postPublishDate'] ) ? $settings['postPublishDate'] : '';
	$post_publish_date       = new DateTime( $post_publish_date );
	$post_publish_date_stamp = $post_publish_date->getTimestamp();

	$structure              = sage_ai_generate_post_structure( $job, $settings );
	$classic_editor_content = sage_ai_make_content_for_classic_editor( $structure );

	$featured_image = array();

	if ( ! empty( $structure ) ) {
		foreach ( $structure as $index => $structure_item ) {
			if ( $structure_item['type'] === 'featuredImage' ) {
				$featured_image = $structure[ $index ];
			}
		}
	}

	$post_url = sage_ai_generate_post( $classic_editor_content, $title, $settings['postStatus'], $postType, $settings['postCategories'], $post_publish_date_stamp, $taxonomies, $featured_image );

	wp_send_json_success( $post_url );
}

/**
 * Generate structue of post.
 *
 * @param array $job Article details. eg. title, keywords to include and exclude.
 * @param array $settings Article settings. eg. language, no. of heading, write style etc. from bulk sidebar.
 * @return array Strucutre of the post.
 */
function sage_ai_generate_post_structure( $job, $settings ) {

	$title = $job['title'];

	$article_image_keyword  = ! empty( $job['imageKeyword'] ) ? $job['imageKeyword'] : $settings['imageKeyword'];
	$featured_image_keyword = ! empty( $job['featuredImageKeywords'] ) ? $job['featuredImageKeywords'] : $settings['featuredImageKeywords'];

	$keywords_to_include = isset( $job['includeKeywords'] ) ? $job['includeKeywords'] : '';
	$keywords_to_exclude = isset( $job['excludeKeywords'] ) ? $job['excludeKeywords'] : '';
	$custom_prompt       = isset( $settings['customPrompt'] ) ? $settings['customPrompt'] : '';
	$image_size          = isset( $settings['imageSize'] ) ? $settings['imageSize'] : '';
	$featured_image_size = isset( $settings['featuredImageSize'] ) ? $settings['featuredImageSize'] : '';

	$structure = array();

	// $settings['imageKeyword'] is from sidebar keyword input whichi will add images based on same keyword.
	// $article_image_keyword is the individual keyword which is related to article.
	if ( ! empty( $article_image_keyword ) ) {

		$images         = array(); // array of image urls form media library.
		$imagesRequired = 1;

		if ( $settings['imageSource'] === 'pixabay' ) {
			$images = sage_ai_call_pixabay( $article_image_keyword, $imagesRequired );
		}

		if ( $settings['imageSource'] === 'dall-e-2' || $settings['imageSource'] === 'dall-e-3' ) {

			$images = sage_ai_get_dall_e_images( $article_image_keyword, $imagesRequired, $image_size, $settings['imageSource'] );

		}

		// only add image if found
		if ( ! empty( $images[0] ) ) {
			$imageLink = $images[0];

			$structure[] = array(
				'type'        => 'image',
				'headingTag'  => false,
				'headingText' => false,
				'text'        => $imageLink,
				'alt'         => $article_image_keyword,
			);
		}
		// insertImageInEditor( imageLink );
	}

	if ( ! empty( $featured_image_keyword ) ) {

		$featuredImages = array(); // array of image urls form media library.
		$imagesRequired = 1;

		if ( $settings['featuredImageSource'] === 'pixabay' ) {
			$featuredImages = sage_ai_call_pixabay( $featured_image_keyword, $imagesRequired );
		}

		if ( $settings['featuredImageSource'] === 'dall-e-2' || $settings['featuredImageSource'] === 'dall-e-3' ) {
			$featuredImages = sage_ai_get_dall_e_images( $featured_image_keyword, $imagesRequired, $featured_image_size, $settings['featuredImageSource'] );
		}

		// only add image if found
		if ( ! empty( $featuredImages[0] ) ) {
			$featuredImageLink = $featuredImages[0];

		}

		$structure[] = array(
			'type'        => 'featuredImage',
			'headingTag'  => false,
			'headingText' => false,
			'text'        => $featuredImageLink,
			'alt'         => $featured_image_keyword,
		);
	}

	if ( $settings['promptMode'] === 'custom' && ! empty( $custom_prompt ) ) {

		$custom_prompt = str_replace( '[title]', $title, $custom_prompt );
		$custom_prompt = str_replace( '[include_keywords]', $keywords_to_include, $custom_prompt );
		$custom_prompt = str_replace( '[exclude_keywords]', $keywords_to_exclude, $custom_prompt );
		$article_data  = sage_ai_call_open_ai( $custom_prompt );
		$structure[]   = array(
			'type'        => 'body',
			'headingTag'  => '',
			'headingText' => '',
			'text'        => $article_data,
		);

		return $structure;
	}

	// Add Introduction
	if ( $settings['addIntroduction'] ) {
		$apiIntroPromptText = $settings['introPromptText'] . $title;
		$intro_text         = sage_ai_call_open_ai( $apiIntroPromptText );
		$structure[]        = array(
			'type'        => 'intro',
			'headingTag'  => false,
			'headingText' => false,
			'text'        => $intro_text,
		);
		// insertparagraphInEditor( introtext, 'intro' );
	}

	$headings_prompt = $settings['headingsPromptText'] . $title;

	$headings_text = sage_ai_call_open_ai( $headings_prompt );

	$headings = preg_replace( '/[0-9]\.\s/', '', $headings_text );
	$headings = preg_split( '/\r?\n|\r|\n/', $headings );
	$headings = array_filter( $headings ); // remove empty array index

	if ( $settings['addToc'] && is_array( $headings ) ) {

		$items = array();

		foreach ( $headings as $heading ) {
			$id      = preg_replace( '/\W/', '-', $heading );
			$id      = preg_replace( '/^\d+\s*/', '', $id ); // replace starting number if any
			$id      = strtolower( $id ); // make it lower case
			$items[] = array(
				'anchorText' => $heading,
				'id'         => $id,
			);
		}

		$structure[] = array(
			'type'  => 'toc',
			'style' => $settings['tocListStyle'],
			'title' => $settings['tocTitle'],
			'items' => $items,
		);
	}

	if ( is_array( $headings ) ) {
		foreach ( $headings as $heading ) {

			$paragraph_prompt = $heading . ' ' . $settings['stylePromptText'] . ' ' . $settings['writingStylePrompt'] . ' ' . $settings['writingTonePrompt'];

			$paragraph = sage_ai_call_open_ai( $paragraph_prompt );
			// $paragraph = 'paragraph';
			$structure[] = array(
				'type'        => 'body',
				'headingTag'  => 'h2',
				'headingText' => $heading,
				'text'        => $paragraph,
			);
		}
	}

	// Add Introduction
	if ( $settings['addConclusion'] ) {
		$apiConclusionPromptText = $settings['conclusionPromptText'] . $title;
		$conclusion_text         = sage_ai_call_open_ai( $apiConclusionPromptText );
		$structure[]             = array(
			'type'        => 'conclusion',
			'headingTag'  => false,
			'headingText' => false,
			'text'        => $conclusion_text,
		);
	}

	return $structure;
}
