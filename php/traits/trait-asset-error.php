<?php
/**
 * Trait file for asset errors.
 *
 * @package AssetManager
 */

trait Asset_Error {
	/**
	 * Generate and echo a WP_Error based on a provided error code
	 *
	 * @param array        $code  Error code.
	 * @param array        $asset Offending asset.
	 * @param array|string $info  Additional information about a dependency or dependent.
	 */
	public function generate_asset_error( $code, $asset, $info = false ) {
		// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
		switch ( $code ) {
			case 'circular_dependency':
				$message = sprintf( __( 'You have a circular dependency in your enqueues. <strong>%1$s</strong> and <strong>%2$s</strong> require each other as dependencies.', 'am' ), $asset['handle'], $info );
				break;

			case 'invalid_load_hook':
				$message = sprintf( __( 'Asset <strong>%1$s</strong> is using an invalid load_hook. The asset is configured to load on hook <strong>%2$s</strong>, but this hook does not exist.', 'am' ), $asset['handle'], $asset['load_hook'] );
				break;

			case 'unsafe_load_hook':
				$message = sprintf( __( 'Asset <strong>%1$s</strong>, configured to load on hook <strong>%2$s</strong>, is loading after an asset that depends on it: <strong>%3$s</strong>, configured to load on hook <strong>%4$s</strong>', 'am' ), $asset['handle'], $asset['load_hook'], $info['handle'], $info['load_hook'] );
				break;

			case 'missing':
				$message = sprintf( __( 'A dependency you listed for this asset is invalid. <strong>%1$s</strong> lists <strong>%2$s</strong> as a dependency, but that asset is not configured to load on this page.', 'am' ), $asset['handle'], $info );
				break;

			case 'cannot_print':
				$message = sprintf( __( 'Asset of type <strong>%1$s</strong> does not exist or does not have a print_asset() function configured.', 'am' ), $asset['type'] );
				break;

			case 'invalid_enqueue_function':
				$message = sprintf( __( 'You attempted to enqueue an asset with function %1$s, which does not exist.', 'am' ), $info );
				break;

			case 'unsafe_load_method':
				$message = sprintf( __( 'Asset <strong>%1$s</strong> uses the <strong>%2$s</strong> load method, meaning there is no guarantee it will be available for its dependent asset <strong>%3$s</strong>, using <strong>%4$s</strong> load method.', 'am' ), $asset['handle'], $asset['load_method'], $info['handle'], $info['load_method'] );
				break;

			case 'unsafe_inline':
				$message = sprintf( __( 'You attempted to load <strong>%1$s</strong> using the "inline" load method, but it is an external asset or the asset does not exist.', 'am' ), $asset['src'] );
				break;

			case 'invalid_preload_as_attribute':
				$message = sprintf( __( 'You attempted to preload <strong>%1$s</strong> with a missing or invalid <strong>as</strong> attribute. The `as` attribute helps the browser prioritize and accept the preloaded asset.', 'am' ), $asset['src'] );
				break;

			default:
				$message = sprintf( __( 'Something went wrong when enqueueing <strong>%s</strong>.', 'am' ), $asset['handle'] );
				break;
		}
		// phpcs:enable

		$this->format_error( new WP_Error( $code, $message, $asset ) );
	}

	/**
	 * Display an error to the user
	 *
	 * @param WP_Error $error Error to display to user.
	 */
	public function format_error( $error ) {
		if ( current_user_can( 'manage_options' ) ) {
			$code = $error->get_error_code();
			echo wp_kses(
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				'<div class="enqueue-error"><strong>ENQUEUE ERROR</strong>: <em>' . $code . '</em> - ' . $error->get_error_message( $code ) . ' Bad asset: <br><pre>' . print_r( $error->get_error_data( $code ), true ) . '</pre></div>',
				[
					'div'    => [ 'class' ],
					'strong' => [],
					'em'     => [],
					'pre'    => [],
				]
			);
		}
	}
}
