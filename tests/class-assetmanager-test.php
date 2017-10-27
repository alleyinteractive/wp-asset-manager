<?php

namespace Assetmanager_Tests;

abstract class Assetmanager_Test extends \WP_UnitTestCase {

	public function setUp() {
		$this->test_script = [
			'handle' => 'my-test-asset',
			'src' => get_stylesheet_directory_uri() . 'static/js/test.bundle.js'
		];
		$this->test_script_two = [
			'handle' => 'test-asset-two',
			'src' => get_stylesheet_directory_uri() . 'static/js/test-two.bundle.js'
		];
		$this->test_style = [
			'handle' => 'my-test-style',
			'src' => get_stylesheet_directory_uri() . 'static/css/test.css'
		];
		$this->test_style_two = [
			'handle' => 'test-style-two',
			'src' => get_stylesheet_directory_uri() . 'static/css/test-two.css'
		];

		// Add test conditions
		remove_all_filters( 'am_asset_conditions', 10 );
		add_filter( 'am_asset_conditions', function() {
			return [
				'global' => true,
				'article_post_type' => true,
				'single' => true,
				'archive' => false,
				'has_slideshow' => false,
				'has_video' => false,
			];
		} );

		$this->reset_assets();
		$this->add_test_user();
	}

	public function tearDown() {
		$this->remove_test_user();
	}

	function add_test_user() {
		$this->user_id = $this->factory->user->create( [
			'user_login' => 'assets-test-user',
			'user_pass' => 'password',
			'user_email' => 'assets-test-user@test.com',
			'role' => 'administrator',
		] );
		wp_set_current_user( $this->user_id );
	}

	function remove_test_user() {
		$this->delete_user( $this->user_id );
		wp_set_current_user( 0 );
	}

	function reset_assets() {
		\Assetmanager_Scripts::instance()->assets = array();
		\Assetmanager_Scripts::instance()->assets_by_handle = array();
		\Assetmanager_Scripts::instance()->asset_handles = array();
		\Assetmanager_Styles::instance()->assets = array();
		\Assetmanager_Styles::instance()->assets_by_handle = array();
		\Assetmanager_Styles::instance()->asset_handles = array();
		\Assetmanager_Styles::instance()->preload_engaged = false;

		wp_deregister_script( 'my-test-asset' );
		wp_deregister_script( 'test-asset-two' );
	}
}
