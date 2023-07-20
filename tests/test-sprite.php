<?php

namespace Asset_Manager_Tests;

class Asset_Manager_Sprite_Tests extends Asset_Manager_Test {

	public $empty_sprite_wrapper = '<svg xmlns="http://www.w3.org/2000/svg" focusable="false" height="0" role="none" style="left:-9999px;overflow:hidden;position:absolute" viewBox="0 0 0 0" width="0">%s</svg>';

	/**
	 * Note: These aren't stright copies of SVG contents; they have been modified
	 * to fit the expected test return values. For example, they include closing
	 * `<path>` tags and namespacing, even though the original file may not.
	 */
	public $clean_with_dimensions = '<symbol id="am-symbol-with-dimensions" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"></path><path d="M0 0h24v24H0z" fill="none"></path></symbol>';

	public $clean_no_dimensions = '<symbol id="am-symbol-no-dimensions" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"></path><path d="M0 0h24v24H0z" fill="none"></path></symbol>';

	public $with_export_junk = '<symbol id="am-symbol-export-junk" viewBox="0 0 24 24"><title>Export Junk</title><desc>Created with Sketch.</desc><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"></path><path d="M0 0h24v24H0z" fill="none"></path></symbol>';

	public $without_embedded_script = '<symbol id="am-symbol-embedded-script" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"></path>window.alert("Gotcha!");<path d="M0 0h24v24H0z" fill="none"></path></symbol>';

	public $nested_dom = '<symbol xmlns:xlink="http://www.w3.org/1999/xlink" id="am-symbol-nested" viewBox="0 0 80 80"><defs><circle cx="40" cy="40" id="a" r="40"></circle><mask fill="#fff" height="80" id="b" width="80" x="0" y="0"><use xlink:href="#a"></use></mask></defs><g fill="none" fill-rule="evenodd"><g fill="#ff3600"><use mask="url(#b)" opacity=".3" stroke="#ff3600" stroke-width="4" xlink:href="#a"></use><circle cx="40" cy="40" r="36"></circle></g><path d="m13.0219048 15.2638095c2.5923729-2.3906688 6.1211271-3.4911238 9.61288-2.9978122s6.5775398 2.5282629 8.4061676 5.5435265l-4.2742857 3.9390476c-1.0743947-2.757774-3.9477158-4.372098-6.8619048-3.8552381-1.1435768.2045781-2.2049056.7316078-3.0590476 1.5190477l-8.20285716 7.5533333c-2.45850266 2.3044243-2.60001568 6.159791-.31707104 8.6382523 2.2829446 2.4784613 6.136934 2.653516 8.6351663.3922239l2.5247619-2.3257143c2.1605309.9580421 4.5104044 1.4129719 6.8723809 1.3304762l-5.5838095 5.1438095c-2.2971156 2.1185814-5.3421485 3.2371933-8.4644577 3.1094714-3.12230911-.1277219-6.06580493-1.4913019-8.18220895-3.7904238-4.41047619-4.787619-5.11238096-11.9323809.68095238-16.6466666zm13.0219047-11.99523807-5.5838095 5.11238095c2.3627528-.0788384 4.7125796.3796644 6.872381 1.34095238l2.5247619-2.32571428c2.4958926-2.29098681 6.3746278-2.13171507 8.6742857.35619047 2.2942857 2.49333335 2.8704762 4.99714285-.3561905 8.67428575l-8.1714286 7.9409523c-3.7504762 3.2895239-6.4219047 1.7390477-8.7057143-.7333333-.5351693-.5786746-.9610925-1.2495038-1.2571428-1.98l-4.2742857 3.9390476c.3948891.6647201.8583744 1.2862118 1.3828571 1.8542857 1.465213 1.594157 3.3436489 2.7509544 5.4266667 3.3419048 2.9253435.8752374 6.0681218.6669586 8.8523809-.5866667 1.150865-.7358603 2.2435936-1.5589091 3.2685715-2.4619047l7.3333333-7.1552381c6.0657143-5.9190476 5.1123809-11.84857144.6495238-16.63619049-4.4063754-4.78100742-11.8538178-5.08584606-16.6361905-.68095238z" fill="#fff" stroke="#fff" stroke-width="1.4" transform="translate(16 18)"></path></g></symbol>';

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

		am_register_symbol(
			[
				'handle'     => 'no-dimensions',
				'src'        => 'no-dimensions.svg',
				'condition'  => 'global',
			]
		);

		am_register_symbol(
			[
				'handle'    => 'with-dimensions',
				'src'       => 'with-dimensions.svg',
				'condition' => 'global',
			]
		);

		am_register_symbol(
			[
				'handle'    => 'export-junk',
				'src'       => 'export-junk.svg',
				'condition' => 'global',
			]
		);

		$this->assertTrue( am_symbol_is_registered( 'no-dimensions' ) );
		$this->assertTrue( am_symbol_is_registered( 'with-dimensions' ) );
		$this->assertTrue( am_symbol_is_registered( 'export-junk' ) );

		$add_assets_expected = sprintf(
			$this->empty_sprite_wrapper,
			$this->clean_no_dimensions . $this->clean_with_dimensions . $this->with_export_junk
		);

		$this->assertEquals(
			$add_assets_expected,
			\Asset_Manager_SVG_Sprite::instance()->sprite_document->C14N(),
			'Should add the symbols to the sprite sheet.'
		);

		$this->assertEquals(
			\Asset_Manager_SVG_Sprite::instance()->sprite_document->C14N(),
			get_echo( [ \Asset_Manager_SVG_Sprite::instance(), 'print_sprite_sheet' ] ),
			'Should properly escape the sprite sheet.'
		);
	}

	/**
	 * Test adding an asset without dimensions.
	 */
	function test_add_asset_no_dimensions() {

		am_register_symbol(
			[
				'handle'     => 'no-dimensions',
				'src'        => 'no-dimensions.svg',
				'condition'  => 'global',
				'attributes' => [
					'id'        => 'define-symbol',
					'data-test' => 'no-dimensions',
					// Override global attribute
					'focusable' => null,
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

		$this->assertEquals(
			\Asset_Manager_SVG_Sprite::instance()->sprite_document->C14N(),
			get_echo( [ \Asset_Manager_SVG_Sprite::instance(), 'print_sprite_sheet' ] ),
			'Should properly escape the sprite sheet.'
		);

		// Global `aria-hidden` preserved for now.
		$basic_markup_expected = '<svg aria-hidden="true" id="define-symbol" data-test="no-dimensions" width="24" height="24"><use href="#am-symbol-no-dimensions"></use></svg>';

		$this->assertEquals(
			$basic_markup_expected,
			am_get_symbol( 'no-dimensions' ),
			'Should get the svg + use markup.'
		);

		$with_attributes_markup_expected = '<svg data-test="test" width="48" height="48"><use href="#am-symbol-no-dimensions"></use></svg>';

		$with_attributes_markup_actual = am_get_symbol(
			'no-dimensions',
			[
				'height'      => 48,
				// Overrides attributes passed to `am_register_symbol()`.
				'id'          => null,
				'data-test'   => 'test',
				// Override global attribute
				'aria-hidden' => false,
			]
		);

		$this->assertEquals(
			$with_attributes_markup_expected,
			$with_attributes_markup_actual,
			'Should get the svg + use markup, with calculated height, global attributes, and additional attributes.'
		);

		$this->assertEquals(
			$with_attributes_markup_expected,
			wp_kses_post( $with_attributes_markup_actual ),
			'Should properly escape the svg + use markup and attributes.'
		);
	}

	/**
	 * Test adding and retrieving an asset with dimensions.
	 */
	function test_with_dimensions() {

		am_register_symbol(
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

		$this->assertEquals(
			\Asset_Manager_SVG_Sprite::instance()->sprite_document->C14N(),
			get_echo( [ \Asset_Manager_SVG_Sprite::instance(), 'print_sprite_sheet' ] ),
			'Should properly escape the sprite sheet.'
		);

		$basic_markup_expected = '<svg focusable="false" aria-hidden="true" width="24" height="24"><use href="#am-symbol-with-dimensions"></use></svg>';

		$this->assertEquals(
			$basic_markup_expected,
			am_get_symbol( 'with-dimensions' ),
			'Should get the svg + use markup.'
		);

		$this->assertArrayHasKey(
			'focusable',
			\Asset_Manager_SVG_Sprite::instance()->kses_svg_allowed_tags['svg'],
			'Should add global attributes to $kses_svg_allowed_tags.'
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
			\Asset_Manager_SVG_Sprite::instance()->kses_svg_allowed_tags['svg'],
			'Should add attributes to $kses_svg_allowed_tags.'
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

		am_register_symbol(
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

		$this->assertEquals(
			\Asset_Manager_SVG_Sprite::instance()->sprite_document->C14N(),
			get_echo( [ \Asset_Manager_SVG_Sprite::instance(), 'print_sprite_sheet' ] ),
			'Should properly escape the sprite sheet.'
		);
	}

	/**
	 * Test adding an asset with an embedded script tag and `onClick` event attribute.
	 */
	function test_asset_with_embedded_script() {

		am_register_symbol(
			[
				'handle'    => 'embedded-script',
				'src'       => 'danger.svg',
				'condition' => 'global',
			]
		);

		$without_embedded_script_expected = sprintf(
			$this->empty_sprite_wrapper,
			$this->without_embedded_script
		);

		$this->assertEquals(
			$without_embedded_script_expected,
			get_echo( [ \Asset_Manager_SVG_Sprite::instance(), 'print_sprite_sheet' ] ),
			'Should properly escape the script tag and disallowed attribute.'
		);
	}

	/**
	 * Test defining dimensions when adding an asset without height/width attributes.
	 */
	function test_asset_with_defined_dimensions() {

		am_register_symbol(
			[
				'handle'     => 'no-dimensions',
				'src'        => 'no-dimensions.svg',
				'condition'  => 'global',
				'attributes' => [
					'width'  => 48,
					'height' => 48,
				],
			]
		);

		$defined_dimensions_expected = '<svg focusable="false" aria-hidden="true" width="48" height="48"><use href="#am-symbol-no-dimensions"></use></svg>';

		$this->assertEquals(
			$defined_dimensions_expected,
			am_get_symbol( 'no-dimensions' ),
			'Should get the svg + use markup with the defined dimensions.'
		);

		$with_height_expected = '<svg focusable="false" aria-hidden="true" width="12" height="12"><use href="#am-symbol-no-dimensions"></use></svg>';
		$with_height_actual   = am_get_symbol( 'no-dimensions', [ 'height' => '12' ] );

		$this->assertEquals(
			$with_height_expected,
			$with_height_actual,
			'Should get the svg + use markup with the dimensions calculated from defined dimensions.'
		);

		$this->assertEquals(
			$with_height_expected,
			wp_kses_post( $with_height_actual ),
			'Should properly escape the svg + use markup.'
		);
	}

	/**
	 * Test adding an asset with nested elements.
	 */
	function test_asset_with_nested_dom() {
		am_register_symbol(
			[
				'handle'    => 'nested',
				'src'       => 'nested.svg',
				'condition' => 'global',
			]
		);

		$with_embedded_script_expected = sprintf(
			$this->empty_sprite_wrapper,
			$this->nested_dom
		);

		$this->assertEquals(
			$with_embedded_script_expected,
			\Asset_Manager_SVG_Sprite::instance()->sprite_document->C14N(),
			'Should copy nested SVG DOM nodes to the sprite sheet.'
		);

		$this->assertEquals(
			\Asset_Manager_SVG_Sprite::instance()->sprite_document->C14N(),
			get_echo( [ \Asset_Manager_SVG_Sprite::instance(), 'print_sprite_sheet' ] ),
			'Should properly escape the sprite sheet.'
		);
	}

	/**
	 * Test escaping non-standard attributes.
	 */
	function test_escape_non_standard_attributes() {

		am_register_symbol(
			[
				'handle'    => 'without-non-standard-attribute',
				'src'       => 'non-standard-attribute.svg',
				'condition' => 'global',
			]
		);

		$without_non_standard_attribute = '<symbol id="am-symbol-without-non-standard-attribute" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"></path><path d="M0 0h24v24H0z" fill="none"></path></symbol>';

		$without_non_standard_attribute_expected = sprintf(
			$this->empty_sprite_wrapper,
			$without_non_standard_attribute
		);

		$this->assertEquals(
			$without_non_standard_attribute_expected,
			get_echo( [ \Asset_Manager_SVG_Sprite::instance(), 'print_sprite_sheet' ] ),
			'Should properly escape the sprite sheet.'
		);
	}

	/**
	 * Test allowing non-standard attributes with `am_sprite_allowed_tags`.
	 */
	function test_allow_non_standard_attribute() {

		am_register_symbol(
			[
				'handle'    => 'with-non-standard-attribute',
				'src'       => 'non-standard-attribute.svg',
				'condition' => 'global',
			]
		);

		add_filter(
			'am_sprite_allowed_tags',
			function( $allowed_tags ) {
				$allowed_tags['path']['asset-manager'] = true;

				return $allowed_tags;
			}
		);

		$with_non_standard_attribute = '<symbol id="am-symbol-with-non-standard-attribute" viewBox="0 0 24 24"><path asset-manager="sprite-test" d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"></path><path d="M0 0h24v24H0z" fill="none"></path></symbol>';

		$with_non_standard_attribute_expected = sprintf(
			$this->empty_sprite_wrapper,
			$with_non_standard_attribute
		);

		$this->assertEquals(
			$with_non_standard_attribute_expected,
			get_echo( [ \Asset_Manager_SVG_Sprite::instance(), 'print_sprite_sheet' ] ),
			'Should properly escape the sprite sheet with the extra allowed attribute.'
		);
	}

	/**
	 * Verifies `$am_svg_allowed_tags` is formatted correctly.
	 */
	function test_escape_camelcase_tags_and_attributes() {

		am_register_symbol(
			[
				'handle'    => 'without-camelcase-tags-and-attrs',
				'src'       => 'camelcase.svg',
				'condition' => 'global',
			]
		);

		// Features a mix of camelCase tags and attributes.
		$camelcase_tags_attrs = '<symbol id="am-symbol-without-camelcase-tags-and-attrs" viewBox="0 0 220 150"><rect height="100" width="100" x="0" y="0"><animate attributeName="y" attributeType="XML" dur="1s" from="0" repeatCount="5" to="50"></animate></rect><rect height="100" width="100" x="120" y="0"><animate attributeName="y" attributeType="XML" dur="1s" from="0" repeatCount="indefinite" to="50"></animate></rect><path d="M20,50 C20,-50 180,150 180,50 C180-50 20,150 20,50 z" fill="none" stroke="lightgrey"></path><circle fill="red" r="5"><animateMotion dur="10s" path="M20,50 C20,-50 180,150 180,50 C180-50 20,150 20,50 z" repeatCount="indefinite"></animateMotion></circle><clipPath id="myClip"><circle cx="40" cy="35" r="35"></circle></clipPath><use clip-path="url(#myClip)" fill="red" href="#heart"></use><defs><linearGradient gradientTransform="rotate(90)" id="myGradient"><stop offset="5%" stop-color="gold"></stop><stop offset="95%" stop-color="red"></stop></linearGradient></defs><circle cx="5" cy="5" fill="url(#myGradient)" r="4"></circle></symbol>';

		$camelcase_tags_attrs_expected = sprintf(
			$this->empty_sprite_wrapper,
			$camelcase_tags_attrs
		);

		$this->assertEquals(
			$camelcase_tags_attrs_expected,
			get_echo( [ \Asset_Manager_SVG_Sprite::instance(), 'print_sprite_sheet' ] ),
			'Should properly escape the SVG tags and attributes.'
		);
	}

	/**
	 * Test replacing a symbol.
	 */
	function test_replace_symbol() {
		$clean_with_dimensions = '<symbol id="am-symbol-deregister-test" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"></path><path d="M0 0h24v24H0z" fill="none"></path></symbol>';

		$with_export_junk = '<symbol id="am-symbol-deregister-test" viewBox="0 0 24 24"><title>Export Junk</title><desc>Created with Sketch.</desc><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"></path><path d="M0 0h24v24H0z" fill="none"></path></symbol>';

		am_register_symbol(
			[
				'handle'    => 'deregister-test',
				'src'       => 'with-dimensions.svg',
				'condition' => 'global',
			]
		);

		$with_defined_symbol = sprintf(
			$this->empty_sprite_wrapper,
			$clean_with_dimensions
		);

		$this->assertEquals(
			$with_defined_symbol,
			\Asset_Manager_SVG_Sprite::instance()->sprite_document->C14N(),
			'Should add the symbol to the sprite sheet.'
		);

		// Remove.
		$symbol_was_removed = am_deregister_symbol( 'deregister-test' );

		$with_removed_symbol = sprintf(
			$this->empty_sprite_wrapper,
			''
		);

		$this->assertEquals(
			$with_removed_symbol,
			\Asset_Manager_SVG_Sprite::instance()->sprite_document->C14N(),
			'Should remove the symbol from the sprite sheet.'
		);

		$this->assertTrue( $symbol_was_removed );
		$this->assertFalse( am_symbol_is_registered( 'deregister-test' ) );

		// Returns true if the symbol hasn't been registered.
		$symbol_not_exist = am_deregister_symbol( 'nonexistent' );
		$this->assertTrue( $symbol_not_exist );
	}
}
