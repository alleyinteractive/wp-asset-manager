<?php

namespace Asset_Manager_Tests;

use Asset_Manager_Scripts;
use Asset_Manager_Styles;
use Asset_Manager_Preload;
use Asset_Manager_SVG_Sprite;
use WP_UnitTestCase;

abstract class Asset_Manager_Test extends WP_UnitTestCase {

	public function setUp() {
		$this->test_script     = [
			'handle' => 'my-test-asset',
			'src'    => 'http://www.example.org/wp-content/themes/example/static/js/test.bundle.js',
		];
		$this->test_script_two = [
			'handle' => 'test-asset-two',
			'src'    => 'http://www.example.org/wp-content/themes/example/static/js/test-two.bundle.js',
		];
		$this->test_style      = [
			'handle' => 'my-test-style',
			'src'    => 'http://www.example.org/wp-content/themes/example/static/css/test.css',
		];
		$this->test_style_two  = [
			'handle' => 'test-style-two',
			'src'    => 'http://www.example.org/wp-content/themes/example/static/css/test-two.css',
		];

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
			'am_svg_attributes',
			function( $attrs ) {
				return array_merge( $attrs, $this->global_attributes );
			}
		);

		$this->reset_assets();
		$this->add_test_user();
	}

	public function tearDown() {
		$this->remove_test_user();
	}

	function add_test_user() {
		$this->user_id = $this->factory->user->create(
			[
				'user_login' => 'assets-test-user',
				'user_pass'  => 'password',
				'user_email' => 'assets-test-user@test.com',
				'role'       => 'administrator',
			]
		);
		wp_set_current_user( $this->user_id );
	}

	function remove_test_user() {
		$this->delete_user( $this->user_id );
		wp_set_current_user( 0 );
	}

	function reset_assets() {
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
		Asset_Manager_SVG_Sprite::instance()->symbol_allowed_html = [
			'svg' => [
				'height' => true,
				'width'  => true,
				'class'  => true,
			],
			'use' => [
				'href' => true,
			],
		];
		Asset_Manager_SVG_Sprite::instance()->global_attributes   = [];
		Asset_Manager_SVG_Sprite::$_svg_directory                 = null;
		Asset_Manager_SVG_Sprite::instance()->create_sprite_sheet();

		wp_deregister_script( 'my-test-asset' );
		wp_deregister_script( 'test-asset-two' );
	}
}
