<?php

namespace Asset_Manager_Tests;

class Asset_Manager_Preload_Tests extends Asset_Manager_Test {

	/**
	 * @group preload
	 */
	function test_preload_asset() {
		// Basic CSS preload.
		$preload_basic         = [
			'handle'      => 'preload-basic',
			'src'         => 'client/css/test.css',
			'as'          => 'style',
			'media'       => '(min-width: 768px)',
			'mime_type'   => 'text/css',
			'crossorigin' => false,
		];
		$expected_style_output = '<link rel="preload" href="http://client/css/test.css" class="wp-asset-manager preload-basic" as="style" media="(min-width: 768px)" type="text/css" />';
		$actual_style_output   = get_echo( [ \Asset_Manager_Preload::instance(), 'print_asset' ], [ $preload_basic ] );
		$this->assertEquals(
			$expected_style_output,
			$actual_style_output,
			'Should print a preload link with all necessary attributes'
		);
	}

	/**
	 * @group preload
	 */
	function test_post_validate_asset() {
		// Adds the expected attributes for preloading a font.
		$font_asset     = [
			'handle' => 'preload-as-font',
			'src'    => 'my-font.woff2',
		];
		$expected_font  = array_merge(
			$font_asset,
			[
				'as'          => 'font',
				'crossorigin' => true,
				'mime_type'   => 'font/woff2',
			]
		);

		$actual_font_output = \Asset_Manager_Preload::instance()->post_validate_asset( $font_asset );

		$this->assertEquals(
			$expected_font,
			$actual_font_output,
			"Should add the 'as' and 'crossorigin' arguments for a preloaded font"
		);

		// Throws an error for an unknown file type.
		$unknown_asset = [
			'handle' => 'preload-as-audio',
			'src'    => 'my-song.mp3',
		];

		$error = get_echo( [ \Asset_Manager_Preload::instance(), 'post_validate_asset' ], [ $unknown_asset ] );
		$this->assertContains( '<strong>ENQUEUE ERROR</strong>: <em>missing_preload_attribute</em>', $error, "Should throw missing_preload_attribute error if the 'as' attribute is missing" );
	}

	/**
	 * @group preload
	 */
	function test_set_asset_types() {
		// Adds the expected attributes for preloading a CSS file.
		$expected_style  = array_merge(
			$this->test_style,
			[
				'as'        => 'style',
				'mime_type' => 'text/css',
			]
		);

		$actual_output = \Asset_Manager_Preload::instance()->set_asset_types( $this->test_style );

		$this->assertEquals(
			$expected_style,
			$actual_output,
			"Should add the 'as' and 'mime_type' arguments for a preloaded CSS file"
		);

		// Adds the expected attributes for preloading a font.
		$font_asset     = [
			'handle' => 'preload-type-font',
			'src'    => 'my-font.woff2',
		];
		$expected_font  = array_merge(
			$font_asset,
			[
				'as'        => 'font',
				'mime_type' => 'font/woff2',
			]
		);

		$actual_font_output = \Asset_Manager_Preload::instance()->set_asset_types( $font_asset );

		$this->assertEquals(
			$expected_font,
			$actual_font_output,
			"Should add the 'as', 'mime_type' and 'crossorigin' arguments for a preloaded font"
		);

		// Adds the expected attributes for preloading a JS file.
		$script_asset = [
			'handle' => 'preload-type-script',
			'src'    => 'my-script.js',
		];
		$expected_script  = array_merge(
			$script_asset,
			[
				'as'        => 'script',
				'mime_type' => 'text/javascript',
			]
		);

		$actual_script_output = \Asset_Manager_Preload::instance()->set_asset_types( $script_asset );

		$this->assertEquals(
			$expected_script,
			$actual_script_output,
			"Should add the 'as' and 'mime_type' arguments for a preloaded JS file"
		);
	}
}
