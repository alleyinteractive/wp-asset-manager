<?php
/**
 * Asset Manager Tests: SVG Sprite.
 *
 * Tests symbol registration, sprite-sheet escaping, symbol
 * markup, and dimension calculation.
 *
 * @package Asset_Manager
 */

declare(strict_types=1);

namespace Alley\WP\Asset_Manager\Tests;

use Alley\WP\Asset_Manager\SVG_Sprite;

use PHPUnit\Framework\Attributes\CoversClass;

use function Mantle\Support\Helpers\capture;

/**
 * SpriteTest class.
 */
#[CoversClass( SVG_Sprite::class )]
class SpriteTest extends TestCase {

	public $empty_sprite_wrapper = '<svg xmlns="http://www.w3.org/2000/svg" focusable="false" height="0" role="none" style="left:-9999px;overflow:hidden;position:absolute" viewBox="0 0 0 0" width="0">%s</svg>';

	/**
	 * Note: These aren't stright copies of SVG contents; they have been modified
	 * to fit the expected test return values. For example, they include closing
	 * `<path>` tags and namespacing, even though the original file may not.
	 */
	public $clean_with_dimensions = '<symbol id="am-symbol-with-dimensions" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"></path><path d="M0 0h24v24H0z" fill="none"></path></symbol>';

	public $clean_no_dimensions = '<symbol id="am-symbol-no-dimensions" viewBox="0 0 24 24"><defs><title>hello</title></defs><g><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"></path><path d="M0 0h24v24H0z" fill="none"></path></g></symbol>';

	public $with_export_junk = '<symbol id="am-symbol-export-junk" viewBox="0 0 24 24"><title>Export Junk</title><desc>Created with Sketch.</desc><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"></path><path d="M0 0h24v24H0z" fill="none"></path></symbol>';

	public $without_embedded_script = '<symbol id="am-symbol-embedded-script" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"></path>window.alert("Gotcha!");<path d="M0 0h24v24H0z" fill="none"></path></symbol>';

	/**
	 * Test adjusting relative filepaths.
	 */
	public function test_get_the_normalized_filepath(): void {
		$this->assertEquals(
			$this->svg_directory . 'relative/path.svg',
			SVG_Sprite::instance()->get_the_normalized_filepath( 'relative/path.svg' ),
			'Should build the correct file path from a relative path.'
		);

		$this->assertEquals(
			'/absolute/path.svg',
			SVG_Sprite::instance()->get_the_normalized_filepath( '/absolute/path.svg' ),
			'Should build the correct file path from an absolute path.'
		);
	}

	/**
	 * Test adding multiple assets to the sprite sheet.
	 */
	public function test_add_assets(): void {
		$this->assertEquals(
			sprintf( $this->empty_sprite_wrapper, '' ),
			SVG_Sprite::instance()->sprite_document->C14N(),
			'Should create an empty sprite sheet.'
		);

		am_register_symbol(
			[
				'handle'    => 'no-dimensions',
				'src'       => 'no-dimensions.svg',
				'condition' => 'global',
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
			SVG_Sprite::instance()->sprite_document->C14N(),
			'Should add the symbols to the sprite sheet.'
		);

		$this->assertEquals(
			$this->expected_after_kses( SVG_Sprite::instance()->sprite_document->C14N() ),
			capture( [ SVG_Sprite::instance(), 'print_sprite_sheet' ] ),
			'Should properly escape the sprite sheet.'
		);
	}

	/**
	 * Test adding an asset without dimensions.
	 */
	public function test_add_asset_no_dimensions(): void {

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
			SVG_Sprite::instance()->sprite_document->C14N(),
			'Should add the symbol to the sprite sheet.'
		);

		$this->assertEquals(
			$this->expected_after_kses( SVG_Sprite::instance()->sprite_document->C14N() ),
			capture( [ SVG_Sprite::instance(), 'print_sprite_sheet' ] ),
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
	public function test_with_dimensions(): void {

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
			SVG_Sprite::instance()->sprite_document->C14N(),
			'Should add the symbol to the sprite sheet.'
		);

		$this->assertEquals(
			$this->expected_after_kses( SVG_Sprite::instance()->sprite_document->C14N() ),
			capture( [ SVG_Sprite::instance(), 'print_sprite_sheet' ] ),
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
			SVG_Sprite::instance()->kses_svg_allowed_tags['svg'],
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
					'data-test' => 'test',
				]
			),
			'Should get the svg + use markup, with calculated height, global attributes, and additional attributes.'
		);

		$this->assertArrayHasKey(
			'data-test',
			SVG_Sprite::instance()->kses_svg_allowed_tags['svg'],
			'Should add attributes to $kses_svg_allowed_tags.'
		);

		$this->assertEquals(
			$with_attributes_markup_expected,
			capture(
				fn () => am_use_symbol(
					'with-dimensions',
					[
						'width'     => 48,
						'class'     => 'am-test',
						'data-test' => 'test',
					]
				)
			),
			'Should echo the svg + use markup, with calculated height, global attributes, and additional attributes.'
		);
	}

	/**
	 * Test adding an asset with dimensions.
	 */
	public function test_asset_with_export_junk(): void {

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
			SVG_Sprite::instance()->sprite_document->C14N(),
			'Should add the symbol to the sprite sheet.'
		);

		$this->assertEquals(
			$this->expected_after_kses( SVG_Sprite::instance()->sprite_document->C14N() ),
			capture( [ SVG_Sprite::instance(), 'print_sprite_sheet' ] ),
			'Should properly escape the sprite sheet.'
		);
	}

	/**
	 * Test adding an asset with an embedded script tag and `onClick` event attribute.
	 */
	public function test_asset_with_embedded_script(): void {

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
			$this->expected_after_kses( $without_embedded_script_expected ),
			capture( [ SVG_Sprite::instance(), 'print_sprite_sheet' ] ),
			'Should properly escape the script tag and disallowed attribute.'
		);
	}

	/**
	 * Test defining dimensions when adding an asset without height/width attributes.
	 */
	public function test_asset_with_defined_dimensions(): void {

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
	 * Test escaping non-standard attributes.
	 */
	public function test_escape_non_standard_attributes(): void {

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
			$this->expected_after_kses( $without_non_standard_attribute_expected ),
			capture( [ SVG_Sprite::instance(), 'print_sprite_sheet' ] ),
			'Should properly escape the sprite sheet.'
		);
	}

	/**
	 * Test allowing non-standard attributes with `am_sprite_allowed_tags`.
	 */
	public function test_allow_non_standard_attribute(): void {

		am_register_symbol(
			[
				'handle'    => 'with-non-standard-attribute',
				'src'       => 'non-standard-attribute.svg',
				'condition' => 'global',
			]
		);

		add_filter(
			'am_sprite_allowed_tags',
			function ( $allowed_tags ) {
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
			$this->expected_after_kses( $with_non_standard_attribute_expected ),
			capture( [ SVG_Sprite::instance(), 'print_sprite_sheet' ] ),
			'Should properly escape the sprite sheet with the extra allowed attribute.'
		);
	}

	/**
	 * Verifies `$am_svg_allowed_tags` is formatted correctly.
	 */
	public function test_escape_camelcase_tags_and_attributes(): void {

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

		/*
		 * camelCase element names survive escaping on every supported version. camelCase
		 * attribute names only survive through WordPress 6.9 — from 7.0 on, `wp_kses_hair()`
		 * lowercases them, which `expected_after_kses()` accounts for. Browsers restore the
		 * canonical casing when parsing SVG in HTML, so the rendered sprite is unchanged.
		 */
		$this->assertEquals(
			$this->expected_after_kses( $camelcase_tags_attrs_expected ),
			capture( [ SVG_Sprite::instance(), 'print_sprite_sheet' ] ),
			'Should properly escape the SVG tags and attributes.'
		);
	}

	/**
	 * Test replacing a symbol.
	 */
	public function test_replace_symbol(): void {
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
			SVG_Sprite::instance()->sprite_document->C14N(),
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
			SVG_Sprite::instance()->sprite_document->C14N(),
			'Should remove the symbol from the sprite sheet.'
		);

		$this->assertTrue( $symbol_was_removed );
		$this->assertFalse( am_symbol_is_registered( 'deregister-test' ) );

		// Returns true if the symbol hasn't been registered.
		$symbol_not_exist = am_deregister_symbol( 'nonexistent' );
		$this->assertTrue( $symbol_not_exist );
	}
}
