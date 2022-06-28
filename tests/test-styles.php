<?php

namespace Asset_Manager_Tests;

class Asset_Manager_Styles_Tests extends Asset_Manager_Test {

	/**
	 * @group assets
	 */
	function test_print_asset() {
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
		$actual_style_output   = get_echo( [ \Asset_Manager_Styles::instance(), 'print_asset' ], [ $inline_src ] );
		$this->assertEquals( $expected_style_output, $actual_style_output, 'Inline load_method should print the contents of a CSS file in a <style> tag' );

		// Async load method
		$async_style           = [
			'handle'      => 'inline-async-asset',
			'src'         => 'client/css/test.css',
			'load_method' => 'async',
		];
		$expected_style_output = '<link rel="stylesheet" class="wp-asset-manager inline-async-asset" href="http://client/css/test.css" media="print" onload="this.onload=null;this.media=\'all\'" /><noscript><link rel="stylesheet" href="http://client/css/test.css" class="wp-asset-manager inline-async-asset" /></noscript>';
		$actual_style_output   = get_echo( [ \Asset_Manager_Styles::instance(), 'print_asset' ], [ $async_style ] );
		$this->assertEquals( $expected_style_output, $actual_style_output, 'Should load CSS via <link> tag that, on load, will switch to the media attribute from `print` to `all`' );

		// Async load with media method
		$async_media_style     = [
			'handle'      => 'inline-async-asset',
			'src'         => 'client/css/test.css',
			'load_method' => 'async',
			'media'       => 'screen and (min-width: 1200px)',
		];
		$expected_style_output = '<link rel="stylesheet" class="wp-asset-manager inline-async-asset" href="http://client/css/test.css" media="print" onload="this.onload=null;this.media=\'screen and (min-width: 1200px)\'" /><noscript><link rel="stylesheet" href="http://client/css/test.css" media="screen and (min-width: 1200px)" class="wp-asset-manager inline-async-asset" /></noscript>';
		$actual_style_output   = get_echo( [ \Asset_Manager_Styles::instance(), 'print_asset' ], [ $async_media_style ] );
		$this->assertEquals( $expected_style_output, $actual_style_output, 'Should load CSS via <link> tag that, on load, will switch to the media attribute from `print` to the media attribute value specified in the config' );

		// Defer load method
		$defer_style           = [
			'handle'      => 'inline-defer-asset',
			'src'         => 'client/css/test.css',
			'load_method' => 'defer',
		];
		$expected_style_output = '<script class="wp-asset-manager inline-defer-asset" type="text/javascript">document.addEventListener("DOMContentLoaded",function(){loadCSS("http://client/css/test.css");});</script><noscript><link rel="stylesheet" href="http://client/css/test.css" class="wp-asset-manager inline-defer-asset" /></noscript>';
		$actual_style_output   = get_echo( [ \Asset_Manager_Styles::instance(), 'print_asset' ], [ $defer_style ] );
		$this->assertEquals( $expected_style_output, $actual_style_output, 'Should load CSS via loadCSS() function called on DOMContentLoaded' );

		// Inline load method with missing file
		$inline_fail  = [
			'handle'      => 'inline-missing',
			'src'         => 'client/css/file-does-not-exist.css',
			'load_method' => 'inline',
		];
		$style_output = get_echo( [ \Asset_Manager_Styles::instance(), 'print_asset' ], [ $inline_fail ] );
		$this->assertStringContainsString( '<strong>ENQUEUE ERROR</strong>: <em>unsafe_inline</em>', $style_output, 'Should throw an error if file provided does not exist' );

		// Inline load method with external asset
		$inline_external = [
			'handle'      => 'inline-external',
			'src'         => 'https://ajax.googleapis.com/ajax/libs/jquerymobile/1.4.5/jquery.mobile.min.css',
			'load_method' => 'inline',
		];
		$style_output    = get_echo( [ \Asset_Manager_Styles::instance(), 'print_asset' ], [ $inline_external ] );
		$this->assertStringContainsString( '<strong>ENQUEUE ERROR</strong>: <em>unsafe_inline</em>', $style_output, 'Should throw an error if file provided is not hosted on the same domain' );
	}

	/**
	 * @group assets
	 */
	function test_pre_add_asset() {
		$async_style = array_merge(
			$this->test_style,
			[
				'load_method' => 'async',
			]
		);
		am_enqueue_style( $async_style );
		$this->assertNotContains( 'loadCSS', \Asset_Manager_Scripts::instance()->asset_handles );

		$defer_style = array_merge(
			$this->test_style_two,
			[
				'load_method' => 'defer',
			]
		);
		am_enqueue_style( $defer_style );
		$this->assertContains( 'loadCSS', \Asset_Manager_Scripts::instance()->asset_handles );
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
			\Asset_Manager_Scripts::instance()->assets
		);

		// am_enqueue_style > load_method => preload is depricated.
		$preload_style = [
			'handle' => 'style-preload-patch',
			'src'    => 'http://www.example.org/wp-content/themes/example/static/css/test-patch.css',
			'load_method' => 'preload',
		];
		am_enqueue_style( $preload_style );
		$this->assertContains( 'style-preload-patch', \Asset_Manager_Styles::instance()->asset_handles );
		$this->assertContains(
			[
				'handle'      => 'style-preload-patch',
				'src'         => 'http://www.example.org/wp-content/themes/example/static/css/test-patch.css',
				'deps'        => [],
				'condition'   => 'global',
				'load_method' => 'sync',
				'version'     => '1.0.0',
				'load_hook'   => 'wp_head',
				'media'       => 'all',
				'type'        => 'style',
				'loaded'      => true,
			],
			\Asset_Manager_Styles::instance()->assets,
			"Styles preloaded via `am_enqueue_style` should be switched to the 'sync' `load_method`"
		);
		$this->assertContains( 'style-preload-patch', \Asset_Manager_Preload::instance()->asset_handles );
		$this->assertContains(
			[
				'handle'      => 'style-preload-patch',
				'src'         => 'http://www.example.org/wp-content/themes/example/static/css/test-patch.css',
				'deps'        => [],
				'condition'   => 'global',
				'load_method' => 'preload',
				'version'     => '1.0.0',
				'load_hook'   => 'wp_head',
				'media'       => 'all',
				'type'        => 'preload',
			],
			\Asset_Manager_Preload::instance()->assets,
			'Styles preloaded via `am_enqueue_style` should be sent through `am_preload`'
		);
	}

	/**
	 * @group assets
	 */
	function test_post_validate_asset() {
		$sync_style  = array_merge(
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
		$defer_style['dependents'] = \Asset_Manager_Styles::instance()->find_dependents( $defer_style );
		$output                    = get_echo( [ \Asset_Manager_Styles::instance(), 'post_validate_asset' ], [ $defer_style ] );
		$this->assertStringContainsString( '<strong>ENQUEUE ERROR</strong>: <em>unsafe_load_method</em>', $output, 'Should throw an error if a synchronously-loaded stylesheet depends on a stylesheet with a defer attribute' );
	}
}
