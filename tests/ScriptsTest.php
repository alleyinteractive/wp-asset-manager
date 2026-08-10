<?php
/**
 * Asset Manager Tests: Scripts.
 *
 * Tests script-specific behavior: printing, async/defer attributes,
 * and load-method changes.
 *
 * @package Asset_Manager
 */

declare(strict_types=1);

namespace Alley\WP\Asset_Manager\Tests;

use Alley\WP\Asset_Manager\Scripts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

use function Mantle\Support\Helpers\capture;

/**
 * ScriptsTest class.
 */
#[CoversClass( Scripts::class )]
#[Group( 'assets' )]
class ScriptsTest extends TestCase {

	/**
	 * Test that a script is registered with the WordPress dependency registry.
	 *
	 * @link https://github.com/alleyinteractive/wp-asset-manager/issues/66
	 */
	public function test_script_is_registered_to_wp_deps(): void {
		$asset = [
			'handle'      => 'my-test-asset-test-test',
			'src'         => 'http://www.example.org/wp-content/themes/example/static/js/cool-test.bundle.js',
			'load_method' => 'async',
		];

		$this->assertFalse( wp_script_is( $asset['handle'], 'registered' ) );
		$this->assertFalse( wp_script_is( $asset['handle'], 'done' ) );
		$this->assertFalse( wp_script_is( $asset['handle'], 'enqueued' ) );
		$this->assertFalse( wp_script_is( $asset['handle'], 'queue' ) );

		add_filter( 'am_register_assets_to_wordpress_dependency', '__return_true' );

		am_enqueue_script( $asset );

		remove_filter( 'am_register_assets_to_wordpress_dependency', '__return_true' );

		$this->assertTrue( wp_script_is( $asset['handle'], 'enqueued' ), 'Script should be enqueued.' );
		$this->assertTrue( wp_script_is( $asset['handle'], 'registered' ), 'Script should be registered.' );
		$this->assertTrue( wp_script_is( $asset['handle'], 'queue' ), 'Script should be in the queue.' );
		$this->assertFalse( wp_script_is( $asset['handle'], 'done' ), 'Script is not marked as done.' );
	}

	public function test_async_attribute(): void {
		am_enqueue_script(
			[
				'handle'      => 'async-asset',
				'src'         => 'http://www.example.org/wp-content/themes/example/static/js/async-test.js',
				'load_method' => 'async',
			]
		);

		$this->assertScriptHasAttribute( 'async', capture( fn () => wp_print_scripts( 'async-asset' ) ) );
	}

	public function test_modify_load_method(): void {
		$sync_asset = [
			'handle' => 'sync-asset',
			'src'    => 'http://www.example.org/wp-content/themes/twentytwelve/static/js/async-test.js',
		];

		$expected_async_result = [
			'handle'      => 'sync-asset',
			'src'         => 'http://www.example.org/wp-content/themes/twentytwelve/static/js/async-test.js',
			'deps'        => [],
			'condition'   => 'global',
			'load_method' => 'async',
			'version'     => '1.0.0',
			'load_hook'   => 'wp_head',
			'type'        => 'script',
			'in_footer'   => false,
			'loaded'      => true,
		];

		am_enqueue_script( $sync_asset );
		am_modify_load_method( 'sync-asset', 'async' );

		$actual_async_result = Scripts::instance()->assets_by_handle['sync-asset'];

		$this->assertContains( $actual_async_result, Scripts::instance()->assets, 'Assets with a modified load method should be added to the asset manifest' );
		$this->assertEquals( $expected_async_result, $actual_async_result, 'Assets with a modified load method should have the appropriate attibute added' );
	}

	public function test_print_asset(): void {
		// Inline load method with array provided for src attribute
		$inline_array           = [
			'handle'      => 'inline-array-asset',
			'src'         => [
				'myGlobalVar' => true,
			],
			'load_method' => 'inline',
		];
		$expected_script_output = '<script class="wp-asset-manager inline-array-asset" type="text/javascript">window.assetContext = window.assetContext || {}; window.assetContext["inline-array-asset"] = {"myGlobalVar":true}</script>';
		$actual_script_output   = capture( fn () => Scripts::instance()->print_asset( $inline_array ) );
		$this->assertEquals( $expected_script_output, $actual_script_output, 'Inline assets with an array provided in `src` should output a script containing a global variable' );

		// Inline load method with path provided for src attibute
		$inline_src             = [
			'handle'      => 'inline-src-asset',
			'src'         => 'tests/mocks/test-js.js',
			'load_method' => 'inline',
		];
		$expected_script_output = "<script class=\"wp-asset-manager inline-src-asset\" type=\"text/javascript\">export function testFunction() {
  var test = 'This is a test variable';
  console.log(test);
};
</script>";
		$actual_script_output   = capture( fn () => Scripts::instance()->print_asset( $inline_src ) );
		$this->assertEquals( $expected_script_output, $actual_script_output, 'Inline assets with filepath provided in `src` should get the contents of that file and output them in a script tag' );

		// Inline load method with missing file
		$inline_fail            = [
			'handle'      => 'inline-missing',
			'src'         => get_stylesheet_directory_uri() . '/client/js/file-does-not-exist.js',
			'load_method' => 'inline',
		];
		$expected_script_output = '<strong>ENQUEUE ERROR</strong>: <em>unsafe_inline</em>';
		$actual_script_output   = capture( fn () => Scripts::instance()->print_asset( $inline_fail ) );
		$this->assertStringContainsString( $expected_script_output, $actual_script_output, 'Should throw an error if file provided does not exist' );

		// Inline load method with external asset
		$inline_external        = [
			'handle'      => 'inline-external',
			'src'         => 'https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js',
			'load_method' => 'inline',
		];
		$expected_script_output = '<strong>ENQUEUE ERROR</strong>: <em>unsafe_inline</em>';
		$actual_script_output   = capture( fn () => Scripts::instance()->print_asset( $inline_external ) );
		$this->assertStringContainsString( $expected_script_output, $actual_script_output, 'Should throw an error if file provided is not hosted on the same domain' );
	}

	public function test_post_validate_asset(): void {
		$sync_script  = array_merge(
			$this->test_script,
			[
				'load_method' => 'sync',
				'deps'        => [
					'defer-script-test',
					'async-script-test',
				],
			]
		);
		$defer_script = array_merge(
			$this->test_script_two,
			[
				'handle'      => 'defer-script-test',
				'load_method' => 'defer',
			]
		);
		$async_script = array_merge(
			$this->test_script_two,
			[
				'handle'      => 'async-script-test',
				'load_method' => 'async',
			]
		);
		am_enqueue_script( $sync_script );

		// Defer script test
		$defer_script['dependents'] = Scripts::instance()->find_dependents( $defer_script );
		$output                     = capture( fn () => Scripts::instance()->post_validate_asset( $defer_script ) );
		$this->assertStringContainsString( '<strong>ENQUEUE ERROR</strong>: <em>unsafe_load_method</em>', $output, 'Should throw an error if a synchronously-loaded script depends on a script with a defer attribute' );

		// Async script test
		$async_script['dependents'] = Scripts::instance()->find_dependents( $async_script );
		$output                     = capture( fn () => Scripts::instance()->post_validate_asset( $async_script ) );
		$this->assertStringContainsString( '<strong>ENQUEUE ERROR</strong>: <em>unsafe_load_method</em>', $output, 'Should throw an error if a synchronously-loaded script depends on a script with a async attribute' );
	}

	/**
	 * Test that enqueueing with the `defer` load method renders the attribute.
	 */
	public function test_defer_attribute(): void {
		am_enqueue_script( $this->test_script['handle'], $this->test_script['src'], [], 'global', 'defer' );

		$this->assertScriptHasAttribute( 'defer', capture( fn () => wp_print_scripts( $this->test_script['handle'] ) ) );
	}

	/**
	 * Test that `am_modify_load_method()` defers a script that is already enqueued.
	 *
	 * WordPress 6.3+ carries the load method through core's `strategy` argument, which is
	 * only read at enqueue time — so changing the load method afterwards has to write the
	 * strategy back to the registered script.
	 *
	 * @link https://github.com/alleyinteractive/wp-asset-manager/issues/62
	 */
	public function test_modify_load_method_defers_an_enqueued_script(): void {
		am_enqueue_script( $this->test_script );

		$before = capture( fn () => wp_print_scripts( $this->test_script['handle'] ) );
		$this->assertStringNotContainsString( ' defer', $before, 'Script should load synchronously before the load method is modified.' );

		am_modify_load_method( $this->test_script['handle'], 'defer' );

		wp_scripts()->done = [];

		$this->assertScriptHasAttribute( 'defer', capture( fn () => wp_print_scripts( $this->test_script['handle'] ) ) );
	}

	/**
	 * Test that a script can never carry both `async` and `defer`.
	 *
	 * The `async-defer` load method was removed in 2.0.0 — `async` takes precedence over
	 * `defer` in the HTML spec, so the combination has no effect worth supporting.
	 *
	 * @link https://github.com/alleyinteractive/wp-asset-manager/issues/63
	 */
	public function test_async_and_defer_are_mutually_exclusive(): void {
		$this->assertNotContains( 'async-defer', Scripts::instance()->load_methods, '`async-defer` should no longer be a valid load method.' );

		$this->setExpectedIncorrectUsage( 'am_enqueue_script' );

		am_enqueue_script(
			[
				'handle'      => 'async-defer-asset',
				'src'         => 'http://www.example.org/wp-content/themes/example/static/js/both.js',
				'load_method' => 'async-defer',
			]
		);

		$this->assertSame(
			'sync',
			Scripts::instance()->assets_by_handle['async-defer-asset']['load_method'],
			'An unrecognised load method should fall back to `sync`.'
		);

		$output = capture( fn () => wp_print_scripts( 'async-defer-asset' ) );

		$this->assertFalse(
			str_contains( $output, ' async' ) && str_contains( $output, ' defer' ),
			'A script tag should never carry both `async` and `defer`.'
		);
	}

	/**
	 * Assert that a rendered script tag carries a boolean attribute.
	 *
	 * Matches the attribute name only. WordPress 6.3 renders script attributes with single
	 * quotes and later versions use double quotes, so assertions must not depend on either.
	 *
	 * @param string $attribute Attribute name, e.g. `defer`.
	 * @param string $tag       Rendered script tag.
	 */
	private function assertScriptHasAttribute( string $attribute, string $tag ): void {
		$this->assertMatchesRegularExpression(
			'/<script\b[^>]*\s' . preg_quote( $attribute, '/' ) . '(?=[\s>])/',
			$tag,
			"Script tag should carry the `{$attribute}` attribute. Rendered: {$tag}"
		);
	}
}
