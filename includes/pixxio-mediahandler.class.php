<?php
/**
 * File download and media library handling
 *
 * @package Pixxio
 * @since 2.0.0
 */

namespace Pixxio;

use WP_Error;

class MediaHandler extends Singleton {

	/**
	 * Initialize MediaHandler functionality
	 *
	 * @since 2.0.0
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
			array( self::class, 'add_attachment_json_pixxio_meta' ),
			10,
			3
		);

		add_filter(
			'wp_get_attachment_image_attributes',
			array( self::class, 'add_pixxio_id_class' ),
			10,
			3
		);

		add_filter(
			'media_row_actions',
			array( self::class, 'add_view_in_mediaspace' ),
			10,
			3
		);
	}

	/**
	 * Returns a chunked response by flushing successive JSON responses containing the upload progress
	 *
	 * @since 2.0.0
	 *
	 * @param void   $resource
	 * @param double $download_size
	 * @param double $downloaded_size
	 * @param double $upload_size
	 * @param double $uploaded_size
	 * @return void
	 */
	public static function downloadProgress( $resource, $download_size, $downloaded_size, $upload_size, $uploaded_size ) {
		static $previousProgress = 0;

		if ( $download_size == 0 ) {
			$progress = 0;
		} else {
			$progress = round( $downloaded_size * 100 / $download_size );
		}

		if ( $progress > $previousProgress ) {
			if ( ! headers_sent() ) {
				header( 'Content-Type: application/json' );
				@ini_set( 'output_buffering', 'off' );
				@ini_set( 'zlib.output_compression', 'off' );
			}
			if ( ! defined( 'PROGRESS_BUFFER_SENT' ) ) {
				// fill buffer to minimum size for flushing
				echo random_bytes( 64 * 1024 + 1 ) . "\n";
				define( 'PROGRESS_BUFFER_SENT', true );
				ob_flush();
				flush();
			}
			echo json_encode( array( 'progress' => $progress ) ) . "\n";
			ob_flush();
			flush();
			$previousProgress = $progress;
		}
	}

	/**
	 * Downloads an image from pixx.io and adds it to the media library, returning the new attachment ID.
	 *
	 * @since 2.0.0
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

		// Create a temporary file for the image
		$tmp_file        = wp_tempnam( $image_name );
		$tmp_file_handle = fopen( $tmp_file, 'w' );
		if ( ! $tmp_file ) {
			return new \WP_Error( 'tmp_file_error', __( 'Error creating temporary file', 'pixxio' ) );
		}

		add_action(
			'http_api_curl',
			function( $handle ) use ( $tmp_file_handle ) {
				curl_setopt( $handle, CURLOPT_PROGRESSFUNCTION, array( static::class, 'downloadProgress' ) );
				curl_setopt( $handle, CURLOPT_FILE, $tmp_file_handle );
				curl_setopt( $handle, CURLOPT_NOPROGRESS, false );
			},
			PHP_INT_MAX
		);

		// Download the image
		$response = wp_remote_get( $image_url );

		if ( is_wp_error( $response ) || 200 != wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'download_error', __( 'Error downloading image', 'pixxio' ) );
		}

		fclose( $tmp_file_handle );

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
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function download_pixxio_image_ajax_handler() {
		// Check for permissions and validate the nonce
		if (
			! current_user_can( 'upload_files' ) ||
			! isset( $_POST['nonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'download_pixxio_image' )
		) {
			wp_send_json_error( __( 'Permission denied', 'pixxio' ) );
		}

		// check required parameters
		if (
			! isset( $_POST['file'] ) ||
			! is_array( $_POST['file'] ) ||
			! isset( $_POST['file']['id'] ) ||
			empty( $_POST['file']['id'] ) ||
			! isset( $_POST['file']['fileName'] ) ||
			empty( $_POST['file']['fileName'] ) ||
			! isset( $_POST['file']['downloadURL'] ) ||
			empty( $_POST['file']['downloadURL'] )
		) {
			wp_send_json_error( __( 'Invalid download request', 'pixxio' ) );
		}

		if ( isset( $_POST['file']['downloadFormat'] ) ) {
			$downloadFormat = sanitize_text_field( wp_unslash( $_POST['file']['downloadFormat'] ?? '' ) );
		} else {
			$downloadFormat = strtolower( wp_check_filetype( $_POST['file']['fileName'] )['ext'] );
		}

		$pixxio_id  = (int) $_POST['file']['id'];
		$image_url  = esc_url_raw( $_POST['file']['downloadURL'] );
		$mediaspace = parse_url( $image_url, PHP_URL_HOST );

		// check if an attachment for this pixxio ID and mediaspace already exists
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => 'pixxio_id',
					'value' => $pixxio_id,
				),
				array(
					'key'   => 'pixxio_mediaspace',
					'value' => $mediaspace,
				),
				array(
					'key'   => 'pixxio_downloadFormat',
					'value' => $downloadFormat,
				),
			),
		);

		$attachment_id = null;
		$existed       = false;

		$attachments     = get_posts( $args );
		$returnMediaItem = isset( $_POST['returnMediaItem'] ) && $_POST['returnMediaItem'] == 'true';

		// Linked attachment is already present
		// @TODO: update handling when attachment has been modified?
		if ( $attachments && count( $attachments ) ) {
			$attachment_id = $attachments[0]->ID;
			$existed       = true;
		} else {
			// Process the POST variable containing the image URL
			$image_url = esc_url_raw( $image_url );

			// Set image name
			$image_name = sanitize_file_name( $_POST['file']['fileName'] );

			// Trigger download
			$result = self::download_to_media_library( $image_url, $image_name );

			// Check for errors and return the result as a JSON response
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( $result->get_error_message() );
			} else {
				$attachment_id = $result;
				$timestamp     = get_post_field( 'post_modified_gmt', $attachment_id );
				// @TODO: get and store additional metadata from pixx.io
				update_post_meta( $attachment_id, 'pixxio_id', $pixxio_id );
				update_post_meta( $attachment_id, 'pixxio_import_gmt', $timestamp );
				update_post_meta( $attachment_id, 'pixxio_mediaspace', $mediaspace );
				update_post_meta( $attachment_id, 'pixxio_downloadFormat', $downloadFormat );
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
	 * @since 2.0.0
	 *
	 * @param array    $response
	 * @param \WP_Post $attachment
	 * @param [type]   $meta
	 * @return array
	 */
	public static function add_attachment_json_pixxio_meta( $response, $attachment, $meta ) {
		$meta = get_metadata( 'post', $attachment->ID, '', true );
		if ( ! empty( $meta['pixxio_id'] ) ) {
			$keys = array( 'pixxio_id', 'pixxio_mediaspace', 'pixxio_downloadFormat', 'pixxio_import_gmt' );
			foreach ( $keys as $key ) {
				$response[ $key ] = is_numeric( $meta[ $key ][0] ) ? (int) $meta[ $key ][0] : $meta[ $key ][0];
			}

			$response['pixxio_import_formatted'] = mysql2date( Pixxio::i18n()->__d( 'F j, Y' ), $response['pixxio_import_gmt'] );
		}
		return $response;
	}

	/**
	 * Adds the class "pixxio" to attachment images for linked attachments
	 *
	 * @since 2.0.0
	 *
	 * @param string[]     $attr
	 * @param \WP_Post     $attachment
	 * @param string|int[] $size
	 * @return string[]
	 */
	public static function add_pixxio_id_class( $attr, $attachment, $size ) {
		$pixxio_id = get_post_meta( $attachment->ID, 'pixxio_id', true );
		if ( ! empty( $pixxio_id ) ) {
			$attr['class'] .= ' pixxio';
		}
		return $attr;
	}

	/**
	 * Adds a "View in media space" link to the attachment row actions
	 *
	 * @since 2.0.0
	 *
	 * @param string[] $actions
	 * @param \WP_Post $post
	 * @param bool     $detached
	 */
	public static function add_view_in_mediaspace( $actions, $post, $detached ) {
		$meta = get_metadata( 'post', $post->ID, '', true );
		if ( ! empty( $meta['pixxio_id'] ) ) {
			$actions['pixxio_open'] =
				'<a href="https://' . esc_attr( $meta['pixxio_mediaspace'][0] ) . '/media/overview/file/' . esc_attr( $meta['pixxio_id'][0] ) . '" class="pixxio-icon" target="_blank">' .
					esc_html__( 'View in media space', 'pixxio' ) .
				'</a>';
		}
		return $actions;
	}
}
