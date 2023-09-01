<?php

namespace Asset_Manager_Tests;

use Asset_Manager_Scripts;
use Asset_Manager_Styles;
use Asset_Manager_Preload;
use Asset_Manager_SVG_Sprite;
use Mantle\Testing\Concerns\Refresh_Database;
use Mantle\Testkit\Test_Case;

abstract class Asset_Manager_Test extends Test_Case {
	use Refresh_Database;

	public $test_script = [
		'handle' => 'my-test-asset',
		'src'    => 'http://www.example.org/wp-content/themes/example/static/js/test.bundle.js',
	];

	public $test_script_two = [
		'handle' => 'test-asset-two',
		'src'    => 'http://www.example.org/wp-content/themes/example/static/js/test-two.bundle.js',
	];

	public $test_style = [
		'handle' => 'my-test-style',
		'src'    => 'http://www.example.org/wp-content/themes/example/static/css/test.css',
	];

	public $test_style_two = [
		'handle' => 'test-style-two',
		'src'    => 'http://www.example.org/wp-content/themes/example/static/css/test-two.css',
	];

	public $global_attributes;
	public $svg_directory;

	protected function setUp(): void {
		parent::setUp();

		// Add test conditions
		remove_all_filters( 'am_asset_conditions', 10 );
		add_filter(
			'am_asset_conditions',
			function() {
				return [
					'global'            => true,
					'article_post_type' => true,
					'single'            => true,
					'archive'           => false,
					'has_slideshow'     => false,
					'has_video'         => false,
				];
			}
		);
		add_filter(
			'am_inline_script_context',
			function() {
				return 'assetContext';
			}
		);

		$this->svg_directory = dirname( __FILE__ ) . '/mocks/';
		add_filter(
			'am_modify_svg_directory',
			function() {
				return $this->svg_directory;
			}
		);

		$this->global_attributes = [ 'focusable' => 'false', 'aria-hidden' => 'true' ];
		add_filter(
			'am_global_svg_attributes',
			function( $attrs ) {
				return array_merge( $attrs, $this->global_attributes );
			}
		);

		$this->reset_assets();
		$this->acting_as( 'administrator' );
	}

	public function reset_assets() {
		Asset_Manager_Scripts::instance()->assets           = [];
		Asset_Manager_Scripts::instance()->assets_by_handle = [];
		Asset_Manager_Scripts::instance()->asset_handles    = [];
		Asset_Manager_Styles::instance()->assets            = [];
		Asset_Manager_Styles::instance()->assets_by_handle  = [];
		Asset_Manager_Styles::instance()->asset_handles     = [];
		Asset_Manager_Styles::instance()->loadcss_added     = false;
		Asset_Manager_Preload::instance()->assets           = [];
		Asset_Manager_Preload::instance()->assets_by_handle = [];
		Asset_Manager_Preload::instance()->asset_handles    = [];

		Asset_Manager_SVG_Sprite::instance()->asset_handles       = [];
		Asset_Manager_SVG_Sprite::instance()->sprite_map          = [];
		Asset_Manager_SVG_Sprite::instance()->kses_svg_allowed_tags = [
			'svg' => [
				'focusable'   => true,
				'aria-hidden' => true,
			],
			'use' => [
				'href' => true,
			],
		];
		Asset_Manager_SVG_Sprite::$_global_attributes = null;
		Asset_Manager_SVG_Sprite::$_svg_directory     = null;
		Asset_Manager_SVG_Sprite::instance()->create_sprite_sheet();

		wp_deregister_script( 'my-test-asset' );
		wp_deregister_script( 'test-asset-two' );
	}
}
