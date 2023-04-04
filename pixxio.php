<?php
/*
 * Plugin Name: pixx.io
 * Version: 0.1.0
 * Description: The official WordPress plugin for pixx.io. Bring Digital Asset Management to your WordPress sites by importing assets into your media library.
 * Plugin URI: https://www.pixx.io/
 * Author: 48DESIGN GmbH
 * Author URI: https://48design.com
 * Text Domain: pixxio
 * Requires PHP: 7.4
 *
 */

namespace Pixxio;

use TypeError;

require_once 'includes/pixxio-singleton.class.php';

 /**
  * Pixxio Plugin Main Class
  *
  * @package Pixxio
  * @since 1.0.0
  * @method static i18n i18n()
  * @method static Downloader Downloader()
  * @method static Admin Admin()
  */
class Pixxio extends Singleton {
	private static $init = false;

	/**
	 * Pixxio plugin directory
	 */
	public const DIR = __DIR__;
	/**
	 * Pixxio plugin main file
	 */
	public const ENTRY = __FILE__;

	public static $version = '0.0.0';

	/**
	 * @param string $className
	 * @return void
	 */
	private static function autoload( $className ) {
		if ( substr( $className, 0, 7 ) !== 'Pixxio\\' ) {
			return;
		}

		$base_dir  = plugin_dir_path( __FILE__ ) . 'includes/';
		$file      = str_replace( '\\', '-', strtolower( $className ) ) . '.class.php';
		$file_path = $base_dir . $file;

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}

	public static function __callStatic( $name, $arguments ) {
		$className = 'Pixxio\\' . $name;
		if ( class_exists( $className ) ) {
			$instance      = $className::get_instance();
			$instanceClass = get_class( $instance );
			if ( get_class( $instance ) === $className ) {
				return $instance;
			} else {
				throw new \Exception( 'Class name case mismatch: Did you mean to use Pixxio::' . substr( $instanceClass, 7 ) . '?' );
			}
		}
	}

	/**
	 * Register autoload and initialize sub classes
	 *
	 * @return void
	 * @throws TypeError
	 */
	public static function init() {
		if ( static::$init ) {
			return;
		}

		add_action( 'plugins_loaded', function() {
			if( !function_exists('get_plugin_data') ){
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}
			static::$version = \get_plugin_data( __FILE__, false, false )['Version'];
		} );

		spl_autoload_register( array( static::class, 'autoload' ) );

		i18n::init();

		static::$init = true;
	}
}
Pixxio::init();

add_action(
	'admin_enqueue_scripts',
	function() {
		wp_enqueue_script(
			'pixxio-admin-media',
			plugins_url( 'admin/js/admin-media.js', __FILE__ ),
			array( 'media-views' ),
			Pixxio::$version,
			true
		);

		wp_enqueue_style(
			'pixxio-admin-media',
			plugins_url( 'admin/css/admin-media.css', __FILE__ ),
			array( 'media-views' ),
			Pixxio::$version
		);

		add_action(
			'print_media_templates',
			function() {
				// @TODO: iframe URL from user locale
				$locale      = Pixxio::i18n()->getLocale();
				$iframe_lang = 'en';
				if ( substr( $locale, 0, 3 ) === 'de_' ) {
					$iframe_lang = 'de';
				}
				?>
	<script type="text/html" id="tmpl-pixxio-content">
	<iframe id="pixxio_sdk" src="https://plugin.pixx.io/static/v0/<?php echo $iframe_lang; ?>/media" width="100%" height="100%"></iframe>
	</script>
				<?php
			}
		);

		add_action(
			'pre-plupload-upload-ui',
			function() {
				global $pagenow;
				if ( ! in_array( $pagenow, array( 'upload.php', 'media-new.php' ) ) ) {
					return;
				}

				$buttonClass = 'button';
				if ( $pagenow === 'upload.php' ) {
					$buttonClass .= ' button-hero';
				}
				?>
		<button type="button" class="<?php echo esc_attr( $buttonClass ); ?>" id="pixxio-uploader">
				<?php
				esc_html_e( 'Import from pixx.io', 'pixxio' );
				?>
		</button>
				<?php
			}
		);
	}
);

function download_and_add_image_to_media_library( $image_url, $image_name, $post_id = 0 ) {
	 // Check for a valid URL
	if ( ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
		return new WP_Error( 'invalid_url', __( 'Invalid image URL', 'pixxio' ) );
	}

	 // Download the image
	 $response = wp_remote_get( $image_url );

	if ( is_wp_error( $response ) || 200 != wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'download_error', __( 'Error downloading image', 'pixxio' ) );
	}

	 // Get the image content
	 $image_content = wp_remote_retrieve_body( $response );

	 // Create a temporary file for the image
	 $tmp_file = wp_tempnam( $image_name );
	if ( ! $tmp_file ) {
		return new WP_Error( 'tmp_file_error', __( 'Error creating temporary file', 'pixxio' ) );
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

// Register the AJAX action
add_action( 'wp_ajax_download_image_from_url', 'Pixxio\download_image_from_url_ajax_handler' );

function download_image_from_url_ajax_handler() {
	 // Check for permissions and validate the nonce
	 // @TODO: verify nonce
	if ( ! current_user_can( 'upload_files' ) /*|| !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'download_image_from_url')*/ ) {
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

		 // Call the download_and_add_image_to_media_library() function
		 $result = download_and_add_image_to_media_library( $image_url, $image_name );

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

function add_pixxio_id_class( $class, $attachment_id ) {
	 $pixxio_id = get_post_meta( $attachment_id, 'pixxio_id', true );
	if ( ! empty( $pixxio_id ) ) {
		$class[] = 'has-pixxio-id';
	}
	 return $class;
}
add_filter( 'post_class', 'add_pixxio_id_class', 10, 2 );

function add_attachment_json_pixxio_id( $response, $attachment, $meta ) {
	 $pixxio_id = get_post_meta( $attachment->ID, 'pixxio_id', true );
	if ( ! empty( $pixxio_id ) ) {
		$response['pixxio_id'] = (int) $pixxio_id;
	}
	 return $response;

};
add_filter( 'wp_prepare_attachment_for_js', 'Pixxio\add_attachment_json_pixxio_id', 10, 3 );
