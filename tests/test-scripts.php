<?php

namespace Alley\WP\Asset_Manager\Tests;

use Alley\WP\Asset_Manager\Scripts;

class Asset_Manager_Scripts_Tests extends Test_Case {

	/**
	 * @group assets
	 */
	function test_add_attributes() {
		$async_asset  = [
			'handle'      => 'async-asset',
			'src'         => get_stylesheet_directory_uri() . '/static/js/async-test.js',
			'load_method' => 'async',
		];
		$original_tag = '<script type="text/javascript" src="http://example.com/wp-content/themes/twentytwelve/static/js/async-test.js"></script>';
		am_enqueue_script( $async_asset );
		Scripts::instance()->add_to_async( $async_asset );
		$expected_async_tag = '<script type="text/javascript" async src="http://example.com/wp-content/themes/twentytwelve/static/js/async-test.js"></script>';
		$actual_async_tag   = Scripts::instance()->add_attributes( $original_tag, 'async-asset' );
		$this->assertEquals( $expected_async_tag, $actual_async_tag, 'add_to_async should add the approprate attribute (async or defer) to a script' );
	}

	/**
	 * @group assets
	 */
	function test_modify_load_method() {
		$sync_asset            = [
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

	/**
	 * @group assets
	 */
	function test_print_asset() {
		// Inline load method with array provided for src attribute
		$inline_array           = [
			'handle'      => 'inline-array-asset',
			'src'         => [
				'myGlobalVar' => true,
			],
			'load_method' => 'inline',
		];
		$expected_script_output = '<script class="wp-asset-manager inline-array-asset" type="text/javascript">window.assetContext = window.assetContext || {}; window.assetContext["inline-array-asset"] = {"myGlobalVar":true}</script>';
		$actual_script_output   = get_echo( [ Scripts::instance(), 'print_asset' ], [ $inline_array ] );
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
		$actual_script_output   = get_echo( [ Scripts::instance(), 'print_asset' ], [ $inline_src ] );
		$this->assertEquals( $expected_script_output, $actual_script_output, 'Inline assets with filepath provided in `src` should get the contents of that file and output them in a script tag' );

		// Inline load method with missing file
		$inline_fail            = [
			'handle'      => 'inline-missing',
			'src'         => get_stylesheet_directory_uri() . '/client/js/file-does-not-exist.js',
			'load_method' => 'inline',
		];
		$expected_script_output = '<strong>ENQUEUE ERROR</strong>: <em>unsafe_inline</em>';
		$actual_script_output   = get_echo( [ Scripts::instance(), 'print_asset' ], [ $inline_fail ] );
		$this->assertStringContainsString( $expected_script_output, $actual_script_output, 'Should throw an error if file provided does not exist' );

		// Inline load method with external asset
		$inline_external        = [
			'handle'      => 'inline-external',
			'src'         => 'https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js',
			'load_method' => 'inline',
		];
		$expected_script_output = '<strong>ENQUEUE ERROR</strong>: <em>unsafe_inline</em>';
		$actual_script_output   = get_echo( [ Scripts::instance(), 'print_asset' ], [ $inline_external ] );
		$this->assertStringContainsString( $expected_script_output, $actual_script_output, 'Should throw an error if file provided is not hosted on the same domain' );
	}

	/**
	 * @group assets
	 */
	function test_post_validate_asset() {
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
		$output                     = get_echo( [ Scripts::instance(), 'post_validate_asset' ], [ $defer_script ] );
		$this->assertStringContainsString( '<strong>ENQUEUE ERROR</strong>: <em>unsafe_load_method</em>', $output, 'Should throw an error if a synchronously-loaded script depends on a script with a defer attribute' );

		// Async script test
		$async_script['dependents'] = Scripts::instance()->find_dependents( $async_script );
		$output                     = get_echo( [ Scripts::instance(), 'post_validate_asset' ], [ $async_script ] );
		$this->assertStringContainsString( '<strong>ENQUEUE ERROR</strong>: <em>unsafe_load_method</em>', $output, 'Should throw an error if a synchronously-loaded script depends on a script with a async attribute' );
	}

	/**
	 * @group assets
	 */
	function test_add_to_async() {
		$async_script = array_merge(
			$this->test_script_two,
			[
				'handle'      => 'async-script-test',
				'load_method' => 'defer',
			]
		);
		Scripts::instance()->add_to_async( $async_script );
		$this->assertContains( 'async-script-test', Scripts::instance()->async_scripts, 'If a script has an `async`, `defer`, or `async-defer` attribute it should be added to an internal $async_scripts property' );

		// Should not add the same script twice
		Scripts::instance()->add_to_async( $async_script );
		$this->assertContains( 'async-script-test', Scripts::instance()->async_scripts, 'A script should not be added to the $async_scripts property twice' );
	}
}
