<?php
/**
 * File download and media library handling
 *
 * @package Pixxio
 * @since 1.0.0
 */

namespace Pixxio;

use WP_Error;

class MediaHandler extends Singleton {

	/**
	 * Initialize MediaHandler functionality
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function init() {
		self::add_hooks();
	}

	public static function add_hooks() {
		add_action(
			'wp_ajax_download_pixxio_image',
			array( self::class, 'download_pixxio_image_ajax_handler' )
		);

		add_filter(
			'wp_prepare_attachment_for_js',
			array( self::class, 'add_attachment_json_pixxio_id' ),
			10,
			3
		);
	}

	/**
	 * Downloads an image from pixx.io and adds it to the media library, returning the new attachment ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $image_url
	 * @param string $image_name
	 * @param int    $post_id
	 * @return int|WP_Error
	 */
	public static function download_to_media_library( $image_url, $image_name, $post_id = 0 ) {
		// Check for a valid URL
		if ( ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
			return new \WP_Error( 'invalid_url', __( 'Invalid image URL', 'pixxio' ) );
		}

		// Download the image
		$response = wp_remote_get( $image_url );

		if ( is_wp_error( $response ) || 200 != wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'download_error', __( 'Error downloading image', 'pixxio' ) );
		}

		// Get the image content
		$image_content = wp_remote_retrieve_body( $response );

		// Create a temporary file for the image
		$tmp_file = wp_tempnam( $image_name );
		if ( ! $tmp_file ) {
			return new \WP_Error( 'tmp_file_error', __( 'Error creating temporary file', 'pixxio' ) );
		}

		// Write the image content to the temporary file
		file_put_contents( $tmp_file, $image_content );

		// Get the image's mime type
		$filetype = wp_check_filetype( $image_name, null );

		// Prepare the image for the media library
		$file = array(
			'name'     => $image_name,
			'type'     => $filetype['type'],
			'tmp_name' => $tmp_file,
			'error'    => 0,
			'size'     => filesize( $tmp_file ),
		);

		// Load the WordPress upload handlers
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Upload the image and add it to the media library
		$attachment_id = media_handle_sideload( $file, $post_id );

		// Check for errors
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp_file );
			return $attachment_id;
		}

		return $attachment_id;
	}

	/**
	 * Handles the ajax request to download an image from pixx.io to the media library,
	 * linking the attachment to the pixx.io image ID via post meta.
	 *
	 * @return void
	 */
	public static function download_pixxio_image_ajax_handler() {
		// Check for permissions and validate the nonce
		// @TODO: verify nonce
		if ( ! current_user_can( 'upload_files' ) /*|| !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'download_pixxio_image')*/ ) {
			wp_send_json_error( 'Permission denied' );
		}

		$pixxio_id = (int) $_POST['files'][0][0]['id'];

		// check if an attachment for this pixxio ID already exists
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'meta_key'       => 'pixxio_id',
			'meta_value'     => $pixxio_id,
		);

		$attachment_id = null;
		$existed       = false;

		$attachments     = get_posts( $args );
		$returnMediaItem = isset( $_POST['returnMediaItem'] ) && $_POST['returnMediaItem'] == 'true';

		if ( $attachments && count( $attachments ) ) {
			$attachment_id = $attachments[0]->ID;
			$existed       = true;
		} else {
			// Check if the image URL is set
			$imageUrl = sanitize_url( $_POST['files'][0][0]['downloadURL'] );
			if ( ! isset( $imageUrl ) || empty( $imageUrl ) ) {
				wp_send_json_error( 'Image URL not provided' ); // @TODO: translatable string
			}

			// Process the POST variable containing the image URL
			$image_url = esc_url_raw( $imageUrl );

			// Set a default image name (you can customize this)
			$image_name = $imageUrl = sanitize_file_name( $_POST['files'][0][0]['fileName'] );

			// Call the download_to_media_library() function
			$result = self::download_to_media_library( $image_url, $image_name );

			// Check for errors and return the result as a JSON response
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( $result->get_error_message() );
			} else {
				$attachment_id = $result;
				update_post_meta( $attachment_id, 'pixxio_id', $pixxio_id );
			}
		}

		$attachmentData             = wp_prepare_attachment_for_js( $attachment_id );
		$attachmentData['_existed'] = $existed;

		if ( $returnMediaItem ) {
			$attachmentData['_returnMediaItemUrl'] = admin_url( 'async-upload.php' );
		}

		wp_send_json_success( $attachmentData );
	}

	/**
	 * Add the pixx.io image ID to linked attachments when outputting attachment data via an ajax request.
	 *
	 * @since 1.0.0
	 *
	 * @param array    $response
	 * @param \WP_Post $attachment
	 * @param [type]   $meta
	 * @return array
	 */
	public static function add_attachment_json_pixxio_id( $response, $attachment, $meta ) {
		$pixxio_id = get_post_meta( $attachment->ID, 'pixxio_id', true );
		if ( ! empty( $pixxio_id ) ) {
			$response['pixxio_id'] = (int) $pixxio_id;
		}
		return $response;
	}


}
