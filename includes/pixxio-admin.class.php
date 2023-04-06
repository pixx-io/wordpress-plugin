<?php
/**
 * Admin backend related functionality
 *
 * @package Pixxio
 * @since 1.0.0
 */

namespace Pixxio;

class Admin extends Singleton {
	/**
	 * Initialize admin functionality
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function init() {
		self::add_admin_hooks();
	}

	/**
	 * add hook callbacks
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function add_admin_hooks() {
		if ( ! is_admin() ) {
			return;
		}

		add_action(
			'admin_enqueue_scripts',
			array( self::class, 'enqueue_scripts_and_styles' ),
		);

		add_action(
			'print_media_templates',
			array( self::class, 'print_media_templates' )
		);

		add_action(
			'pre-plupload-upload-ui',
			array( self::class, 'pre_plupload_upload_ui' )
		);

		add_action(
			'attachment_submitbox_misc_actions',
			array( self::class, 'editor_show_meta' )
		);
	}


	public static function editor_show_meta( $attachment ) {
		$meta = get_metadata( 'post', $attachment->ID, '', true );
		if ( ! empty( $meta['pixxio_id'] ) ) {
			echo '<div class="misc-pub-section misc-pub-pixxio">
				<strong>' . esc_html__( 'pixx.io', 'pixxio' ) . '</strong>
				<dl>
					<dt>' . esc_html__( 'Media space:', 'pixxio' ) . '</dt>
					<dd><a href="https://' . esc_attr( $meta['pixxio_mediaspace'][0] ) . '" target="_blank">' . esc_html( $meta['pixxio_mediaspace'][0] ) . '</a></dd>
					
					<dt>' . esc_html__( 'ID:', 'pixxio' ) . '</dt>
					<dd><a href="https://' . esc_attr( $meta['pixxio_mediaspace'][0] ) . '/media/overview/file/' . esc_attr( $meta['pixxio_id'][0] ) . '" target="_blank"  title="' . esc_html__( 'View in media space', 'pixxio' ) . '">' . (int) $meta['pixxio_id'][0] . '</a></dd>
				</dl>
			</div>';
		}
	}

	/**
	 * Adds the "import from pixx.io" tab content
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function print_media_templates() {
		$locale      = Pixxio::i18n()->getLocale();
		$iframe_lang = 'en';
		if ( substr( $locale, 0, 3 ) === 'de_' ) {
			$iframe_lang = 'de';
		}

		$nonce = wp_create_nonce( 'download_pixxio_image' );
		?>
		<script type="text/html" id="tmpl-pixxio-content">
		<iframe id="pixxio_sdk" src="https://plugin.pixx.io/static/v0/<?php echo $iframe_lang; ?>/media" data-nonce="<?php echo esc_attr( $nonce ); ?>" width="100%" height="100%"></iframe>
		</script>
		<script type="text/html" id="tmpl-pixxio-meta">
			<div class="pixxio-meta">
				<strong><?php \esc_html_e( 'pixx.io', 'pixxio' ); ?></strong>
				<dl>
					<dt><?php esc_html_e( 'Media space:', 'pixxio' ); ?></dt>
					<dd><a href="https://{{ data.pixxio_mediaspace }}" target="_blank">{{ data.pixxio_mediaspace }}</a></dd>
					<dt><?php esc_html_e( 'ID:', 'pixxio' ); ?></dt>
					<dd><a href="https://{{ data.pixxio_mediaspace }}/media/overview/file/{{ data.pixxio_id }}" target="_blank" title="<?php esc_html_e( 'View in media space', 'pixxio' ); ?>">{{ data.pixxio_id }}</dd>
				</dl>
			</div>
		</script>
		<?php
	}

	/**
	 * Adds the "import from pixx.io" button to the uploader
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function pre_plupload_upload_ui() {
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

	/**
	 * Enqueues scripts and styles
	 *
	 * @return void
	 */
	public static function enqueue_scripts_and_styles() {
		global $pagenow;

		// only on specific pages or if media already enqueued
		$media_pages    = array( 'upload.php', 'media-new.php', 'post.php' );
		$media_enqueued = did_action( 'wp_enqueue_media' );

		if ( $media_enqueued || in_array( $pagenow, $media_pages ) ) {
			// make sure all media related functions and files are available
			wp_enqueue_media();

			wp_enqueue_script(
				'pixxio-admin-media',
				plugins_url( 'admin/js/admin-media.js', Pixxio::MAIN ),
				array( 'media-views' ),
				Pixxio::$version,
				true
			);

			wp_enqueue_style(
				'pixxio-admin-media',
				plugins_url( 'admin/css/admin-media.css', Pixxio::MAIN ),
				array( 'media-views' ),
				Pixxio::$version
			);
		}
	}
}
