<?php

namespace Asset_Manager_Tests;

class Asset_Manager_Sprite_Tests extends Asset_Manager_Test {

	public $empty_sprite_wrapper = '<svg xmlns="http://www.w3.org/2000/svg" style="display:none;">%s</svg>';

	/**
	 * Note: These aren't stright copies of SVG contents; they have been modified
	 * to fit the expected test return values. For example, they include closing
	 * tags, even though the original file may not.
	 */
	public $clean_with_dimensions = '<symbol id="am-symbol-with-dimensions" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"></path><path d="M0 0h24v24H0z" fill="none"></path></symbol>';

	public $clean_no_dimensions = '<symbol id="am-symbol-no-dimensions" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"></path><path d="M0 0h24v24H0z" fill="none"></path></symbol>';

	function test_get_the_normalized_filepath() {
		$this->assertEquals(
			$this->svg_directory . 'relative/path.svg',
			\Asset_Manager_SVG_Sprite::instance()->get_the_normalized_filepath( 'relative/path.svg' ),
			'Should build the correct file path from a relative path.'
		);

		$this->assertEquals(
			'/absolute/path.svg',
			\Asset_Manager_SVG_Sprite::instance()->get_the_normalized_filepath( '/absolute/path.svg' ),
			'Should build the correct file path from an absolute path.'
		);
	}

	function test_add_asset() {
		$this->assertEquals(
			sprintf( $this->empty_sprite_wrapper, '' ),
			\Asset_Manager_SVG_Sprite::instance()->sprite_document->C14N(),
			'Should create sprite sheet.'
		);

		am_define_symbol(
			[
				'handle'    => 'no-dimensions',
				'src'       => 'no-dimensions.svg',
				'condition' => 'global',
			]
		);

		$add_asset_test_one = sprintf(
			$this->empty_sprite_wrapper,
			$this->clean_no_dimensions
		);

		$this->assertEquals(
			$add_asset_test_one,
			\Asset_Manager_SVG_Sprite::instance()->sprite_document->C14N(),
			'Should add the symbol to the sprite sheet.'
		);

		am_define_symbol(
			[
				'handle'    => 'with-dimensions',
				'src'       => 'with-dimensions.svg',
				'condition' => 'global',
			]
		);

		$add_asset_test_two = sprintf(
			$this->empty_sprite_wrapper,
			$this->clean_no_dimensions . $this->clean_with_dimensions
		);

		$this->assertEquals(
			$add_asset_test_two,
			\Asset_Manager_SVG_Sprite::instance()->sprite_document->C14N(),
			'Should add the symbol to the sprite sheet.'
		);
	}
}
