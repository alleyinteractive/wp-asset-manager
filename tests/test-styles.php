<?php

namespace Assetmanager_Tests;

class Assetmanager_Styles_Tests extends Assetmanager_Test {

	/**
     * @group assets
     */
	function test_print_asset() {
		// Inline load method with array provided for src attribute
		$inline_src = [
			'handle' => 'inline-src-asset',
			'src' => 'tests/test-css.css',
			'load_method' => 'inline',
		];
		$expected_style_output = "<style class=\"wp-custom-asset inline-src-asset\" type=\"text/css\">body {
\tbackground: blue;
\tfont: Helvetica, times, serif;
}
</style>";
		$actual_style_output = get_echo( [ \Assetmanager_Styles::instance(), 'print_asset' ], [ $inline_src ] );
		$this->assertEquals( $expected_style_output, $actual_style_output, 'Inline load_method should print the contents of a CSS file in a <style> tag' );

		// Preload load method
		$preload_style = [
			'handle' => 'inline-preload-asset',
			'src' => 'client/css/test.css',
			'load_method' => 'preload',
		];
		$expected_style_output = '<link rel="preload" href="http://client/css/test.css" class="wp-custom-asset inline-preload-asset" media="" as="style" onload="this.rel=\'stylesheet\'"></link><noscript><link rel="stylesheet" href="http://client/css/test.css" class="wp-custom-asset inline-preload-asset" media=""></link></noscript>';
		$actual_style_output = get_echo( [ \Assetmanager_Styles::instance(), 'print_asset' ], [ $preload_style ] );
		$this->assertEquals( $expected_style_output, $actual_style_output, 'Should load CSS via a preload <link> tag that, on load, will switch to a stylesheet <link> tag' );

		// Async load method
		$async_style = [
			'handle' => 'inline-async-asset',
			'src' => 'client/css/test.css',
			'load_method' => 'async',
		];
		$expected_style_output = '<script class="wp-custom-asset inline-async-asset" type="text/javascript">loadCSS("http://client/css/test.css");</script><noscript><link rel="stylesheet" href="http://client/css/test.css" class="wp-custom-asset inline-async-asset" media=""></link></noscript>';
		$actual_style_output = get_echo( [ \Assetmanager_Styles::instance(), 'print_asset' ], [ $async_style ] );
		$this->assertEquals( $expected_style_output, $actual_style_output, 'Should load CSS via loadCSS() function' );

		// Defer load method
		$defer_style = [
			'handle' => 'inline-defer-asset',
			'src' => 'client/css/test.css',
			'load_method' => 'defer',
		];
		$expected_style_output = '<script class="wp-custom-asset inline-defer-asset" type="text/javascript">document.addEventListener("DOMContentLoaded",function(){loadCSS("http://client/css/test.css");});</script><noscript><link rel="stylesheet" href="http://client/css/test.css" class="wp-custom-asset inline-defer-asset" media=""></link></noscript>';
		$actual_style_output = get_echo( [ \Assetmanager_Styles::instance(), 'print_asset' ], [ $defer_style ] );
		$this->assertEquals( $expected_style_output, $actual_style_output, 'Should load CSS via loadCSS() function called on DOMContentLoaded' );

		// Inline load method with missing file
		$inline_fail = [
			'handle' => 'inline-missing',
			'src' => 'client/css/file-does-not-exist.css',
			'load_method' => 'inline',
		];
		$style_output = get_echo( [ \Assetmanager_Styles::instance(), 'print_asset' ], [ $inline_fail ] );
		$this->assertContains( '<strong>ENQUEUE ERROR</strong>: <em>unsafe_inline</em>', $style_output, 'Should throw an error if file provided does not exist' );

		// Inline load method with external asset
		$inline_external = [
			'handle' => 'inline-external',
			'src' => 'https://ajax.googleapis.com/ajax/libs/jquerymobile/1.4.5/jquery.mobile.min.css',
			'load_method' => 'inline',
		];
		$style_output = get_echo( [ \Assetmanager_Styles::instance(), 'print_asset' ], [ $inline_external ] );
		$this->assertContains( '<strong>ENQUEUE ERROR</strong>: <em>unsafe_inline</em>', $style_output, 'Should throw an error if file provided is not hosted on the same domain' );
	}

	/**
     * @group assets
     */
	function test_pre_add_asset() {
		$async_style = array_merge( $this->test_style, [
			'load_method' => 'async',
		] );
		$this->assertContains( 'loadCSS', \Assetmanager_Scripts::instance()->asset_handles );
		$this->assertContains( [
		    'handle' => 'loadCSS',
		    'src' => get_template_directory() . '/static/js/loadCSS.min.js',
		    'deps' => [],
		    'condition' => 'global',
		    'load_method' => 'inline',
		    'version' => '1.0.0',
		    'load_hook' => 'am_critical',
		    'type' => 'script',
		    'in_footer' => false,
		], \Assetmanager_Scripts::instance()->assets );
	}

	/**
     * @group assets
     */
	function test_post_validate_asset() {
		$sync_style = array_merge( $this->test_style, [
			'deps' => ['defer-style-test'],
		] );
		$defer_style = array_merge( $this->test_style_two, [
			'handle' => 'defer-style-test',
			'load_method' => 'defer',
		] );
		am_enqueue_style( $sync_style );

		// Defer style test
		$defer_style['dependents'] = \Assetmanager_Styles::instance()->find_dependents( $defer_style );
		$output = get_echo( [ \Assetmanager_Styles::instance(), 'post_validate_asset' ], [ $defer_style ] );
		$this->assertContains( '<strong>ENQUEUE ERROR</strong>: <em>unsafe_load_method</em>', $output, 'Should throw an error if a synchronously-loaded stylesheet depends on a stylesheet with a defer attribute' );
	}
}
