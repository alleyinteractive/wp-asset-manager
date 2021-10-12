<?php

namespace Asset_Manager_Tests;

class Asset_Manager_Sprite_Tests extends Asset_Manager_Test {

	public $empty_sprite_wrapper = '<svg xmlns="http://www.w3.org/2000/svg" style="display:none;">%s</svg>';

	/**
	 * Note: These aren't stright copies of SVG contents; they have been modified
	 * to fit the expected test return values. For example, they include closing
	 * `<path>` tags, even though the original file may not.
	 */
	public $clean_with_dimensions = '<symbol id="am-symbol-with-dimensions" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"></path><path d="M0 0h24v24H0z" fill="none"></path></symbol>';

	public $clean_no_dimensions = '<symbol id="am-symbol-no-dimensions" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"></path><path d="M0 0h24v24H0z" fill="none"></path></symbol>';

	public $with_export_junk = '<symbol id="am-symbol-export-junk" viewBox="0 0 24 24"><title>Export Junk</title><desc>Created with Sketch.</desc><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"></path><path d="M0 0h24v24H0z" fill="none"></path></symbol>';

	public $with_embedded_script = '<symbol id="am-symbol-embedded-script" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"></path><path d="M0 0h24v24H0z" fill="none"></path></symbol>';

	/**
	 * Test adjusting relative filepaths.
	 */
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

	/**
	 * Test adding multiple assets to the sprite sheet.
	 */
	function test_add_assets() {
		$this->assertEquals(
			sprintf( $this->empty_sprite_wrapper, '' ),
			\Asset_Manager_SVG_Sprite::instance()->sprite_document->C14N(),
			'Should create an empty sprite sheet.'
		);

		am_define_symbol(
			[
				'handle'     => 'no-dimensions',
				'src'        => 'no-dimensions.svg',
				'condition'  => 'global',
			]
		);

		am_define_symbol(
			[
				'handle'    => 'with-dimensions',
				'src'       => 'with-dimensions.svg',
				'condition' => 'global',
			]
		);

		am_define_symbol(
			[
				'handle'    => 'export-junk',
				'src'       => 'export-junk.svg',
				'condition' => 'global',
			]
		);

		$add_assets_expected = sprintf(
			$this->empty_sprite_wrapper,
			$this->clean_no_dimensions . $this->clean_with_dimensions . $this->with_export_junk
		);

		$this->assertEquals(
			$add_assets_expected,
			\Asset_Manager_SVG_Sprite::instance()->sprite_document->C14N(),
			'Should add the symbols to the sprite sheet.'
		);
	}

	/**
	 * Test adding an asset without dimensions.
	 */
	function test_add_asset_no_dimensions() {

		am_define_symbol(
			[
				'handle'     => 'no-dimensions',
				'src'        => 'no-dimensions.svg',
				'condition'  => 'global',
				'attributes' => [
					'id'        => 'define-symbol',
					'data-test' => 'no-dimensions',
				],
			]
		);

		$no_dimensions_expected = sprintf(
			$this->empty_sprite_wrapper,
			$this->clean_no_dimensions
		);

		$this->assertEquals(
			$no_dimensions_expected,
			\Asset_Manager_SVG_Sprite::instance()->sprite_document->C14N(),
			'Should add the symbol to the sprite sheet.'
		);

		$basic_markup_expected = '<svg focusable="false" aria-hidden="true" id="define-symbol" data-test="no-dimensions" width="24" height="24"><use href="#am-symbol-no-dimensions"></use></svg>';

		$this->assertEquals(
			$basic_markup_expected,
			am_get_symbol( 'no-dimensions' ),
			'Should get the svg + use markup.'
		);

		$with_attributes_markup_expected = '<svg focusable="false" data-test="test" width="48" height="48"><use href="#am-symbol-no-dimensions"></use></svg>';

		$this->assertEquals(
			$with_attributes_markup_expected,
			am_get_symbol(
				'no-dimensions',
				[
					'height'      => 48,
					// Overrides attributes passed to `am_define_symbol()`.
					'id'          => null,
					'data-test'   => 'test',
					// Override global attribute
					'aria-hidden' => false,
				]
			),
			'Should get the svg + use markup, with calculated height, global attributes, and additional attributes.'
		);
	}

	/**
	 * Test adding and retrieving an asset with dimensions.
	 */
	function test_with_dimensions() {

		am_define_symbol(
			[
				'handle'    => 'with-dimensions',
				'src'       => 'with-dimensions.svg',
				'condition' => 'global',
			]
		);

		$with_dimensions_expected = sprintf(
			$this->empty_sprite_wrapper,
			$this->clean_with_dimensions
		);

		$this->assertEquals(
			$with_dimensions_expected,
			\Asset_Manager_SVG_Sprite::instance()->sprite_document->C14N(),
			'Should add the symbol to the sprite sheet.'
		);

		$basic_markup_expected = '<svg focusable="false" aria-hidden="true" width="24" height="24"><use href="#am-symbol-with-dimensions"></use></svg>';

		$this->assertEquals(
			$basic_markup_expected,
			am_get_symbol( 'with-dimensions' ),
			'Should get the svg + use markup.'
		);

		$this->assertArrayHasKey(
			'focusable',
			\Asset_Manager_SVG_Sprite::instance()->symbol_allowed_html['svg'],
			'Should add global attributes to $symbol_allowed_html.'
		);

		$with_attributes_markup_expected = '<svg focusable="false" aria-hidden="true" width="48" height="48" class="am-test" data-test="test"><use href="#am-symbol-with-dimensions"></use></svg>';

		$this->assertEquals(
			$with_attributes_markup_expected,
			am_get_symbol(
				'with-dimensions',
				[
					'width'     => 48,
					'class'     => 'am-test',
					'data-test' => 'test'
				]
			),
			'Should get the svg + use markup, with calculated height, global attributes, and additional attributes.'
		);

		$this->assertArrayHasKey(
			'data-test',
			\Asset_Manager_SVG_Sprite::instance()->symbol_allowed_html['svg'],
			'Should add attributes to $symbol_allowed_html.'
		);

		$this->assertEquals(
			$with_attributes_markup_expected,
			get_echo(
				'am_use_symbol',
				[
					'with-dimensions',
					[
						'width'     => 48,
						'class'     => 'am-test',
						'data-test' => 'test'
					]
				]
			),
			'Should echo the svg + use markup, with calculated height, global attributes, and additional attributes.'
		);
	}

	/**
	 * Test adding an asset with dimensions.
	 */
	function test_asset_with_export_junk() {

		am_define_symbol(
			[
				'handle'    => 'export-junk',
				'src'       => 'export-junk.svg',
				'condition' => 'global',
			]
		);

		$with_export_junk_expected = sprintf(
			$this->empty_sprite_wrapper,
			$this->with_export_junk
		);

		$this->assertEquals(
			$with_export_junk_expected,
			\Asset_Manager_SVG_Sprite::instance()->sprite_document->C14N(),
			'Should add the symbol to the sprite sheet.'
		);
	}

	/**
	 * Test adding an asset with an embedded script tag.
	 */
	function test_asset_with_embedded_script() {

		am_define_symbol(
			[
				'handle'    => 'embedded-script',
				'src'       => 'danger.svg',
				'condition' => 'global',
			]
		);

		$with_embedded_script_expected = sprintf(
			$this->empty_sprite_wrapper,
			$this->with_embedded_script
		);

		$this->assertEquals(
			$with_embedded_script_expected,
			\Asset_Manager_SVG_Sprite::instance()->sprite_document->C14N(),
			'Should strip script tags before adding the symbol to the sprite sheet.'
		);
	}
}
