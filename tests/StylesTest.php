<?php
/**
 * Asset Manager Tests: Styles.
 *
 * Tests stylesheet-specific behavior: printing, async/defer
 * load methods, and the loadCSS dependency.
 *
 * @package Asset_Manager
 */

declare(strict_types=1);

namespace Alley\WP\Asset_Manager\Tests;

use Alley\WP\Asset_Manager\Preload;
use Alley\WP\Asset_Manager\Scripts;
use Alley\WP\Asset_Manager\Styles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

use function Mantle\Support\Helpers\capture;

/**
 * StylesTest class.
 */
#[CoversClass( Styles::class )]
#[Group( 'assets' )]
class StylesTest extends TestCase {

	/**
	 * Test that a stylesheet is registered with the WordPress dependency registry.
	 *
	 * @link https://github.com/alleyinteractive/wp-asset-manager/issues/66
	 */
	public function test_stylesheet_is_registered_to_wp_deps(): void {
		$this->assertFalse( wp_style_is( $this->test_style['handle'], 'registered' ) );
		$this->assertFalse( wp_style_is( $this->test_style['handle'], 'enqueued' ) );
		$this->assertFalse( wp_style_is( $this->test_style['handle'], 'done' ) );
		$this->assertFalse( wp_style_is( $this->test_style['handle'], 'queue' ) );

		$async_style = array_merge(
			$this->test_style,
			[ 'load_method' => 'async' ]
		);

		add_filter( 'am_register_assets_to_wordpress_dependency', '__return_true' );

		am_enqueue_style( $async_style );

		remove_filter( 'am_register_assets_to_wordpress_dependency', '__return_true' );

		$this->assertFalse( wp_style_is( $this->test_style['handle'], 'enqueued' ), 'Style should not be enqueued.' );
		$this->assertFalse( wp_style_is( $this->test_style['handle'], 'queue', 'Style should not be added to the queue.' ) );
		$this->assertTrue( wp_style_is( $this->test_style['handle'], 'registered' ), 'Style is not registered.' );
		$this->assertTrue( wp_style_is( $this->test_style['handle'], 'done' ), 'Style is not marked as done.' );
	}

	public function test_print_asset(): void {
		// Inline load method with array provided for src attribute
		$inline_src            = [
			'handle'      => 'inline-src-asset',
			'src'         => 'tests/mocks/test-css.css',
			'load_method' => 'inline',
		];
		$expected_style_output = "<style class=\"wp-asset-manager inline-src-asset\" type=\"text/css\">body {
\tbackground: blue;
\tfont: Helvetica, times, serif;
}
</style>";
		$actual_style_output   = capture( fn () => Styles::instance()->print_asset( $inline_src ) );
		$this->assertEquals( $expected_style_output, $actual_style_output, 'Inline load_method should print the contents of a CSS file in a <style> tag' );

		// Async load method
		$async_style           = [
			'handle'      => 'inline-async-asset',
			'src'         => 'client/css/test.css',
			'load_method' => 'async',
		];
		$expected_style_output = '<link rel="stylesheet" class="wp-asset-manager inline-async-asset" href="http://client/css/test.css" media="print" onload="this.onload=null;this.media=\'all\'" /><noscript><link rel="stylesheet" href="http://client/css/test.css" class="wp-asset-manager inline-async-asset" /></noscript>';
		$actual_style_output   = capture( fn () => Styles::instance()->print_asset( $async_style ) );
		$this->assertEquals( $this->expected_after_kses( $expected_style_output ), $actual_style_output, 'Should load CSS via <link> tag that, on load, will switch to the media attribute from `print` to `all`' );

		// Async load with media method
		$async_media_style     = [
			'handle'      => 'inline-async-asset',
			'src'         => 'client/css/test.css',
			'load_method' => 'async',
			'media'       => 'screen and (min-width: 1200px)',
		];
		$expected_style_output = '<link rel="stylesheet" class="wp-asset-manager inline-async-asset" href="http://client/css/test.css" media="print" onload="this.onload=null;this.media=\'screen and (min-width: 1200px)\'" /><noscript><link rel="stylesheet" href="http://client/css/test.css" media="screen and (min-width: 1200px)" class="wp-asset-manager inline-async-asset" /></noscript>';
		$actual_style_output   = capture( fn () => Styles::instance()->print_asset( $async_media_style ) );
		$this->assertEquals( $this->expected_after_kses( $expected_style_output ), $actual_style_output, 'Should load CSS via <link> tag that, on load, will switch to the media attribute from `print` to the media attribute value specified in the config' );

		// Defer load method
		$defer_style           = [
			'handle'      => 'inline-defer-asset',
			'src'         => 'client/css/test.css',
			'load_method' => 'defer',
		];
		$expected_style_output = '<script class="wp-asset-manager inline-defer-asset" type="text/javascript">document.addEventListener("DOMContentLoaded",function(){loadCSS("http://client/css/test.css");});</script><noscript><link rel="stylesheet" href="http://client/css/test.css" class="wp-asset-manager inline-defer-asset" /></noscript>';
		$actual_style_output   = capture( fn () => Styles::instance()->print_asset( $defer_style ) );
		$this->assertEquals( $expected_style_output, $actual_style_output, 'Should load CSS via loadCSS() function called on DOMContentLoaded' );

		// Inline load method with missing file
		$inline_fail  = [
			'handle'      => 'inline-missing',
			'src'         => 'client/css/file-does-not-exist.css',
			'load_method' => 'inline',
		];
		$style_output = capture( fn () => Styles::instance()->print_asset( $inline_fail ) );
		$this->assertStringContainsString( '<strong>ENQUEUE ERROR</strong>: <em>unsafe_inline</em>', $style_output, 'Should throw an error if file provided does not exist' );

		// Inline load method with external asset
		$inline_external = [
			'handle'      => 'inline-external',
			'src'         => 'https://ajax.googleapis.com/ajax/libs/jquerymobile/1.4.5/jquery.mobile.min.css',
			'load_method' => 'inline',
		];
		$style_output    = capture( fn () => Styles::instance()->print_asset( $inline_external ) );
		$this->assertStringContainsString( '<strong>ENQUEUE ERROR</strong>: <em>unsafe_inline</em>', $style_output, 'Should throw an error if file provided is not hosted on the same domain' );
	}

	public function test_pre_add_asset(): void {
		$async_style = array_merge(
			$this->test_style,
			[
				'load_method' => 'async',
			]
		);
		am_enqueue_style( $async_style );
		$this->assertNotContains( 'loadCSS', Scripts::instance()->asset_handles );

		$defer_style = array_merge(
			$this->test_style_two,
			[
				'load_method' => 'defer',
			]
		);
		am_enqueue_style( $defer_style );
		$this->assertContains( 'loadCSS', Scripts::instance()->asset_handles );
		$this->assertContains(
			[
				'handle'      => 'loadCSS',
				'src'         => AM_BASE_DIR . '/js/loadCSS.min.js',
				'deps'        => [],
				'condition'   => 'global',
				'load_method' => 'inline',
				'version'     => '1.0.0',
				'load_hook'   => 'am_critical',
				'type'        => 'script',
				'in_footer'   => false,
			],
			Scripts::instance()->assets
		);
	}

	/**
	 * Test that `am_enqueue_style()` no longer accepts the `preload` load method.
	 *
	 * It was deprecated in 0.1.1 and removed in 2.0.0. Callers should use `am_preload()`.
	 */
	public function test_preload_load_method_is_not_supported(): void {
		$this->assertNotContains( 'preload', Styles::instance()->load_methods, '`preload` should not be a style load method.' );

		$this->setExpectedIncorrectUsage( 'am_enqueue_style' );

		am_enqueue_style(
			[
				'handle'      => 'style-preload-removed',
				'src'         => 'http://www.example.org/wp-content/themes/example/static/css/test-patch.css',
				'load_method' => 'preload',
			]
		);

		$this->assertSame(
			'sync',
			Styles::instance()->assets_by_handle['style-preload-removed']['load_method'],
			'An unrecognised load method should fall back to `sync`.'
		);

		$this->assertNotContains(
			'style-preload-removed',
			Preload::instance()->asset_handles,
			'`am_enqueue_style()` should no longer patch in a call to `am_preload()`.'
		);
	}

	/**
	 * Test that an inline style is printed after the style it depends on.
	 *
	 * Inline styles are printed by the plugin rather than handed to `wp_enqueue_style()`, so
	 * core's dependency resolution never sees them. The order they were registered in used to
	 * be the order they printed in, which makes `deps` meaningless for critical CSS.
	 *
	 * @link https://github.com/alleyinteractive/wp-asset-manager/issues/22
	 */
	public function test_inline_styles_print_in_dependency_order(): void {
		$this->enqueue_inline_style( 'critical-second', [ 'critical-first' ] );
		$this->enqueue_inline_style( 'critical-first' );

		$this->assertSame(
			[ 'critical-first', 'critical-second' ],
			$this->printed_handles(),
			'A dependency should print before the style that depends on it.'
		);
	}

	/**
	 * Test that a chain of dependencies is resolved, not just a direct one.
	 */
	public function test_inline_styles_resolve_a_dependency_chain(): void {
		$this->enqueue_inline_style( 'critical-third', [ 'critical-second' ] );
		$this->enqueue_inline_style( 'critical-second', [ 'critical-first' ] );
		$this->enqueue_inline_style( 'critical-first' );

		$this->assertSame(
			[ 'critical-first', 'critical-second', 'critical-third' ],
			$this->printed_handles(),
			'A transitive dependency should print before everything downstream of it.'
		);
	}

	/**
	 * Test that unrelated styles keep the order they were registered in.
	 *
	 * Critical CSS order is often intentional beyond what `deps` expresses, so sorting must
	 * only move an asset when a dependency forces it to.
	 */
	public function test_inline_styles_without_deps_keep_registration_order(): void {
		$this->enqueue_inline_style( 'critical-beta', [ 'critical-alpha' ] );
		$this->enqueue_inline_style( 'critical-alpha' );
		$this->enqueue_inline_style( 'critical-gamma' );

		$this->assertSame(
			[ 'critical-alpha', 'critical-beta', 'critical-gamma' ],
			$this->printed_handles(),
			'Only the style forced to move by a dependency should move.'
		);
	}

	/**
	 * Test that a circular dependency still prints every style exactly once.
	 *
	 * The cycle itself is reported by `validate_assets()`. The sort only has to avoid
	 * deadlocking on it.
	 */
	public function test_circular_dependencies_still_print_every_style(): void {
		$this->enqueue_inline_style( 'critical-ping', [ 'critical-pong' ] );
		$this->enqueue_inline_style( 'critical-pong', [ 'critical-ping' ] );

		$printed = $this->printed_handles();

		sort( $printed );

		$this->assertSame(
			[ 'critical-ping', 'critical-pong' ],
			$printed,
			'Both styles in a cycle should print, exactly once each.'
		);
	}

	/**
	 * Test that a dependency the plugin doesn't manage is ignored by the sort.
	 *
	 * Core handles and anything enqueued directly aren't in the asset manifest, so they can't
	 * be positioned. A missing dependency is already reported by `validate_assets()`.
	 */
	public function test_unmanaged_dependencies_do_not_block_printing(): void {
		$this->enqueue_inline_style( 'critical-orphan', [ 'a-handle-that-does-not-exist' ] );

		$this->assertSame(
			[ 'critical-orphan' ],
			$this->printed_handles(),
			'A style should still print when it depends on a handle the plugin does not manage.'
		);
	}

	/**
	 * Enqueue an inline style on `wp_head`.
	 *
	 * @param string   $handle Handle for the style.
	 * @param string[] $deps   Handles this style depends on.
	 */
	private function enqueue_inline_style( string $handle, array $deps = [] ): void {
		am_enqueue_style(
			[
				'handle'      => $handle,
				'deps'        => $deps,
				'src'         => 'tests/mocks/test-css.css',
				'load_method' => 'inline',
				'load_hook'   => 'wp_head',
			]
		);
	}

	/**
	 * Print the queued assets and return the handles in the order they appeared.
	 *
	 * @return string[]
	 */
	private function printed_handles(): array {
		$output = capture( fn () => do_action( 'wp_head' ) );

		preg_match_all( '/wp-asset-manager (critical-\w+)/', $output, $matches );

		return $matches[1];
	}

	public function test_post_validate_asset(): void {
		$sync_style = array_merge(
			$this->test_style,
			[
				'deps' => [ 'defer-style-test' ],
			]
		);

		$defer_style = array_merge(
			$this->test_style_two,
			[
				'handle'      => 'defer-style-test',
				'load_method' => 'defer',
			]
		);

		am_enqueue_style( $sync_style );

		// Defer style test
		$defer_style['dependents'] = Styles::instance()->find_dependents( $defer_style );
		$output                    = capture( fn () => Styles::instance()->post_validate_asset( $defer_style ) );
		$this->assertStringContainsString( '<strong>ENQUEUE ERROR</strong>: <em>unsafe_load_method</em>', $output, 'Should throw an error if a synchronously-loaded stylesheet depends on a stylesheet with a defer attribute' );
	}
}
