<?php

namespace Assetmanager_Tests;

class Assetmanager_Core_Tests extends Assetmanager_Test {

	/**
     * @group assets
     */
	function test_add_asset() {
		global $wp_scripts;

		// Enqueue test script
		am_enqueue_script( $this->test_script );

		$this->assertContains( 'my-test-asset', $wp_scripts->queue, 'Script should be enqueued' );
		$this->assertArrayHasKey( 'my-test-asset', $wp_scripts->registered, 'Script should be registered' );
		$this->assertArrayHasKey( 'my-test-asset', \Assetmanager_Scripts::instance()->assets_by_handle, 'Script should be added to asset manifest, sorted by handle' );
		$this->assertContains( 'my-test-asset', \Assetmanager_Scripts::instance()->asset_handles, 'Script should be added to array of asset handles' );
		$this->assertContains( [
			'handle' => 'my-test-asset',
			'src' => get_stylesheet_directory_uri() . 'static/js/test.bundle.js',
			'deps' => [],
			'condition' => 'global',
			'load_method' => 'sync',
			'version' => '1.0.0',
			'load_hook' => 'wp_head',
			'type' => 'script',
			'in_footer' => false,
			'loaded' => 1,
		], \Assetmanager_Scripts::instance()->assets, 'Script data should exist in the primary asset manifest' );
	}

	/**
     * @group assets
     */
	function test_load_asset() {
		// Temporarily set current filter to 'wp_head' to trick current_filter()
		global $wp_current_filter;
		$old_filter = $wp_current_filter;
		$wp_current_filter = array( 'wp_head' );

		am_enqueue_script( [
			'handle' => 'test-inline-asset',
			'src' => [
				'myGlobalVar' => true,
			],
			'load_method' => 'inline',
			'load_hook' => 'wp_head',
		] );
		$actual_output = get_echo( [ \Assetmanager_Scripts::instance(), 'load_assets' ] );
		$expected_output = '<script class="wp-custom-asset test-inline-asset" type="text/javascript">window.nypScripts = window.nypScripts || {}; window.nypScripts["test-inline-asset"] = {"myGlobalVar":true}</script>';
		$this->assertEquals( $expected_output, $actual_output, 'Load assets should call the print_asset() function on each asset and echo the proper results' );

		// Reset current filter
		$wp_current_filter = $old_filter;
	}

	/**
     * @group assets
     */
	function test_asset_should_add() {
		// If no handle, should return false
		$no_handle = \Assetmanager_Scripts::instance()->asset_should_add( ['src' => get_stylesheet_directory_uri() . 'static/js/test-two.bundle.js'] );
		$this->assertFalse( $no_handle, 'If script does not have a handle, it should fail to be added' );

		// If already enqueued, should return false
		am_enqueue_script( $this->test_script );
		$already_added = \Assetmanager_Scripts::instance()->asset_should_add( $this->test_script );
		$this->assertFalse( $already_added, 'If script has already been added, it should not be added again' );

		// If no condition provided, should return true
		$no_condition = \Assetmanager_Scripts::instance()->asset_should_add( $this->test_script_two );
		$this->assertTrue( $no_condition, 'If script had no load condition, it should always load' );

		// If condition is provided as a string, should work as 'include'
		$condition_string_asset = array_merge( $this->test_script_two, ['condition' => 'article_post_type'] );
		$condition_string = \Assetmanager_Scripts::instance()->asset_should_add( $condition_string_asset );
		$this->assertTrue( $condition_string, 'If script has a string as the load condition, it should assume that string is an `include` condition' );

		// If condition is provided as an array, should work as 'include'
		$condition_array_asset = array_merge( $this->test_script_two, ['condition' => ['article_post_type'] ] );
		$condition_array = \Assetmanager_Scripts::instance()->asset_should_add( $condition_string_asset );
		$this->assertTrue( $condition_array, 'If script has an array as the load condition, it should assume that array contains `include` conditions' );

		// Test condition with 'include' property
		$condition_include_asset = array_merge( $this->test_script_two, ['condition' => ['include' => 'article_post_type'] ] );
		$condition_include = \Assetmanager_Scripts::instance()->asset_should_add( $condition_include_asset );
		$this->assertTrue( $condition_include, 'If script has a condition with an `include` key, it should check all `include` conditions are true' );

		// Test condition with 'exclude' property
		$condition_exclude_asset = array_merge( $this->test_script_two, ['condition' => ['exclude' => 'article_post_type'] ] );
		$condition_exclude = \Assetmanager_Scripts::instance()->asset_should_add( $condition_exclude_asset );
		$this->assertFalse( $condition_exclude, 'If script has a condition with an `exclude` key, it should check all `exclude` conditions are false' );

		// Test condition with both include and exclude properties
		$condition_include_exclude_asset = array_merge( $this->test_script_two, [
			'condition' => [
				'include' => [ 'article_post_type', 'single' ],
				'exclude' => [ 'has_slideshow', 'has_video', 'archive' ],
			],
		] );
		$condition_include_exclude = \Assetmanager_Scripts::instance()->asset_should_add( $condition_include_exclude_asset );
		$this->assertTrue( $condition_include_exclude, 'If script has a condition with both `include` and `exclude` keys, it should check all `include` conditions are true and all `eclude` conditions are false' );
	}

	/**
     * @group assets
     */
	function test_asset_should_load() {
		// Temporarily set current filter to 'wp_head' to trick current_filter()
		global $wp_current_filter;
		$old_filter = $wp_current_filter;
		$wp_current_filter = array( 'wp_head' );

		$no_src = \Assetmanager_Scripts::instance()->asset_should_load( [
			'handle' => 'my-test-asset',
			'load_hook' => 'wp_head',
			'loaded' => false,
		] );
		$this->assertFalse( $no_src, 'If script does not have a src, it should fail to be added' );

		$bad_location = \Assetmanager_Scripts::instance()->asset_should_load( [
			'handle' => 'my-test-asset',
			'src' => get_stylesheet_directory_uri() . 'static/js/test-bundle.bundle.js',
			'load_hook' => 'wp_footer',
			'loaded' => false,
		] );
		$this->assertFalse( $bad_location, 'If current hook comes after configured load_hook, do not load script' );

		$bad_location = \Assetmanager_Scripts::instance()->asset_should_load( [
			'handle' => 'my-test-asset',
			'src' => get_stylesheet_directory_uri() . 'static/js/test-bundle.bundle.js',
			'load_hook' => 'am_critical',
			'loaded' => false,
		] );
		$this->assertTrue( $bad_location, 'If current hook corresponds to configured load_hook, or if load_hook comes before current hook, load the asset' );

		$already_loaded = \Assetmanager_Scripts::instance()->asset_should_load( [
			'handle' => 'my-test-asset',
			'src' => get_stylesheet_directory_uri() . 'static/js/test-bundle.bundle.js',
			'load_hook' => 'wp_head',
			'loaded' => '1',
		] );
		$this->assertFalse( $already_loaded, 'If script is already loaded (indicated by loaded property), do not load script' );

		// Reset current filter
		$wp_current_filter = $old_filter;
	}

	/**
     * @group assets
     */
	function test_find_dependents() {
		$asset_with_deps = array_merge( $this->test_script_two, [
			'deps' => [ 'jquery', 'my-test-asset' ],
		] );
		$another_asset_with_deps = [
			'handle' => 'asset-with-dependencies',
			'src' => get_stylesheet_directory_uri() . 'static/js/test-three.bundle.js',
			'deps' => [ 'my-test-asset' ],
		];

		am_enqueue_script( $this->test_script );
		am_enqueue_script( $asset_with_deps );
		am_enqueue_script( $another_asset_with_deps );

		$actual_dependents = \Assetmanager_Scripts::instance()->find_dependents( $this->test_script );
		$expected_dependents = [ 'test-asset-two', 'asset-with-dependencies' ];
		$this->assertEquals( $expected_dependents, $actual_dependents, 'Should return an array of assets that depend on this one' );
	}

	/**
     * @group assets
     */
	function test_invalid_load_hook() {
		// Invalid load hook
		$invalid_load_hook = [
			'handle' => 'my-test-asset',
			'src' => get_stylesheet_directory_uri() . 'static/js/test-bundle.bundle.js',
			'load_hook' => 'hook_does_not_exist',
		];
		am_enqueue_script( $invalid_load_hook );
		$error = get_echo( array( \Assetmanager_Scripts::instance(), 'validate_assets' ), $invalid_load_hook );
		$this->assertContains( '<strong>ENQUEUE ERROR</strong>: <em>invalid_load_hook</em>', $error, 'Should throw invalid_load_hook error if load_hook provided does not exist' );
	}

	/**
     * @group assets
     */
	function test_missing_dependency() {
		// Missing dependency
		$dep_missing = [
			'handle' => 'my-test-asset',
			'src' => get_stylesheet_directory_uri() . 'static/js/test-bundle.bundle.js',
			'deps' => [ 'dep-does-not-exist' ],
		];
		am_enqueue_script( $dep_missing );
		$error = get_echo( array( \Assetmanager_Scripts::instance(), 'validate_assets' ), $dep_missing );
		$this->assertContains( '<strong>ENQUEUE ERROR</strong>: <em>missing</em>', $error, 'Should throw missing error if a dependency does not exist' );
	}

	/**
     * @group assets
     */
	function test_unsafe_load_hook() {
		// Unsafe load hook
		$unsafe_load_hook_dep = [
			'handle' => 'my-test-asset',
			'src' => get_stylesheet_directory_uri() . 'static/js/test-bundle.bundle.js',
			'load_hook' => 'wp_footer',
		];
		$unsafe_load_hook = [
			'handle' => 'test-asset-two',
			'src' => get_stylesheet_directory_uri() . 'static/js/test-two.bundle.js',
			'load_hook' => 'wp_head',
			'deps' => [ 'my-test-asset' ],
		];
		am_enqueue_script( $unsafe_load_hook_dep );
		am_enqueue_script( $unsafe_load_hook );
		$error = get_echo( array( \Assetmanager_Scripts::instance(), 'validate_assets' ), $unsafe_load_hook );
		$this->assertContains( '<strong>ENQUEUE ERROR</strong>: <em>unsafe_load_hook</em>', $error, 'Should throw unsafe_load_hook error if a dependency is configured to load on a load_hook after this script' );
	}

	/**
     * @group assets
     */
	function test_circular_dependency() {
		// Unsafe load hook
		$circular_dep = [
			'handle' => 'my-test-asset',
			'src' => get_stylesheet_directory_uri() . 'static/js/test-bundle.bundle.js',
			'deps' => [ 'test-asset-two' ],
		];
		$circular_dep_two = [
			'handle' => 'test-asset-two',
			'src' => get_stylesheet_directory_uri() . 'static/js/test-two.bundle.js',
			'deps' => [ 'my-test-asset' ],
		];
		am_enqueue_script( $circular_dep );
		am_enqueue_script( $circular_dep_two );
		$error = get_echo( array( \Assetmanager_Scripts::instance(), 'validate_assets' ), $circular_dep );
		$this->assertContains( '<strong>ENQUEUE ERROR</strong>: <em>circular_dependency</em>', $error, 'Should throw circular_dependency error if two scripts have each other as dependencies' );
	}

	/**
     * @group assets
     */
	function test_add_core_dependencies() {
		$asset_with_deps = array_merge( $this->test_script_two, [
			'deps' => [ 'jquery' ],
		] );
		am_enqueue_script( $asset_with_deps );

		\Assetmanager_Scripts::instance()->set_defaults();
		\Assetmanager_Scripts::instance()->add_core_dependencies( $asset_with_deps );

		$expected_assets = [
			[
				'handle' => 'test-asset-two',
				'src' => 'http://example.org/wp-content/themes/twentytwelve/js/test-two.bundle.js',
				'deps' => [ 'jquery' ],
				'condition' => 'global',
				'load_method' => 'sync',
				'version' => '1.0.0',
				'load_hook' => 'wp_head',
				'in_footer' => false,
				'type' => 'script',
				'loaded' => '1',
			],
			[
				'handle' => 'jquery',
				'src' => false,
				'condition' => 'global',
				'deps' => [
					'jquery-core',
					'jquery-migrate',
				],
				'in_footer' => false,
				'load_hook' => 'wp_head',
				'loaded' => true,
				'load_method' => 'sync',
				'type' => 'script',
				'version' => '1.12.4'
			],
		];
		$actual_assets = \Assetmanager_Scripts::instance()->assets;
		$this->assertEquals( $expected_assets, $actual_assets, 'Core dependencies should also be added to the internal asset manifest' );
	}
}
