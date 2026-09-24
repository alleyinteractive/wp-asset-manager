<?php
/**
 * Asset Manager Tests: Preload.
 *
 * Tests `am_preload()` — asset types, `as`/`mime_type` patching,
 * and error handling.
 *
 * @package Asset_Manager
 */

declare(strict_types=1);

namespace Alley\WP\Asset_Manager\Tests;

use Alley\WP\Asset_Manager\Preload;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

use function Mantle\Support\Helpers\capture;

/**
 * PreloadTest class.
 */
#[CoversClass( Preload::class )]
#[Group( 'preload' )]
class PreloadTest extends TestCase {

	public function test_preload_asset(): void {
		// Basic CSS preload.
		// The `print_asset` function does no option parsing, so all expected values are required.
		$preload_basic         = [
			'handle'      => 'preload-basic',
			'src'         => 'client/css/test.css',
			'as'          => 'style',
			'media'       => '(min-width: 768px)',
			'mime_type'   => 'text/css',
			'crossorigin' => false,
			'version'     => '1.0.0',
		];
		$expected_style_output = '<link rel="preload" href="http://client/css/test.css?ver=1.0.0" class="wp-asset-manager preload-basic" as="style" media="(min-width: 768px)" type="text/css" />';
		$actual_style_output   = capture( fn () => Preload::instance()->print_asset( $preload_basic ) );
		$this->assertEquals(
			$expected_style_output,
			$actual_style_output,
			'Should print a preload link with all necessary attributes'
		);
	}

	/**
	 * Test that a responsive image preload carries `imagesrcset`, `imagesizes` and `fetchpriority`.
	 */
	public function test_preload_responsive_image(): void {
		$preload_image = [
			'handle'        => 'preload-hero',
			'src'           => 'client/images/hero.jpg',
			'as'            => 'image',
			'media'         => 'all',
			'version'       => '1.0.0',
			'imagesrcset'   => 'client/images/hero-480.jpg 480w, client/images/hero-800.jpg 800w',
			'imagesizes'    => '(max-width: 600px) 480px, 800px',
			'fetchpriority' => 'high',
		];

		$expected = '<link rel="preload" href="http://client/images/hero.jpg" class="wp-asset-manager preload-hero" as="image" media="all" imagesrcset="client/images/hero-480.jpg 480w, client/images/hero-800.jpg 800w" imagesizes="(max-width: 600px) 480px, 800px" fetchpriority="high" />';

		$this->assertEquals(
			$this->expected_after_kses( $expected ),
			capture( fn () => Preload::instance()->print_asset( $preload_image ) ),
			'Should print a preload link with the responsive image attributes'
		);
	}

	/**
	 * Test that a preload without the responsive image options is unchanged.
	 *
	 * The three attributes are printed through the same format string as the rest, so an
	 * empty value must not leave an attribute or extra whitespace behind.
	 */
	public function test_preload_without_responsive_image_options(): void {
		$preload_image = [
			'handle'  => 'preload-plain',
			'src'     => 'client/images/hero.jpg',
			'as'      => 'image',
			'media'   => 'all',
			'version' => '1.0.0',
		];

		$expected = '<link rel="preload" href="http://client/images/hero.jpg" class="wp-asset-manager preload-plain" as="image" media="all" />';

		$this->assertEquals(
			$this->expected_after_kses( $expected ),
			capture( fn () => Preload::instance()->print_asset( $preload_image ) ),
			'Should print the same tag as before when no responsive image options are supplied'
		);
	}

	/**
	 * Test the array form of `imagesrcset`.
	 */
	public function test_imagesrcset_accepts_an_array(): void {
		am_preload(
			[
				'handle'      => 'preload-array-srcset',
				'src'         => 'client/images/hero.jpg',
				'as'          => 'image',
				'imagesrcset' => [
					480  => 'client/images/hero-480.jpg',
					800  => 'client/images/hero-800.jpg',
					1600 => 'client/images/hero-1600.jpg',
				],
			]
		);

		$this->assertSame(
			'client/images/hero-480.jpg 480w, client/images/hero-800.jpg 800w, client/images/hero-1600.jpg 1600w',
			Preload::instance()->assets_by_handle['preload-array-srcset']['imagesrcset'],
			'The array form of `imagesrcset` should be flattened into a descriptor list.'
		);
	}

	/**
	 * Test that `imagesrcset` prints on its own, without `imagesizes` or `fetchpriority`.
	 *
	 * The three options are independent, and `imagesrcset` is the only one that does anything
	 * on its own — so this is the shape a responsive preload usually takes.
	 */
	public function test_imagesrcset_prints_without_the_other_options(): void {
		am_preload(
			[
				'handle'      => 'preload-srcset-only',
				'src'         => 'client/images/hero.jpg',
				'imagesrcset' => [
					480 => 'client/images/hero-480.jpg',
					800 => 'client/images/hero-800.jpg',
				],
			]
		);

		$asset = Preload::instance()->post_validate_asset( Preload::instance()->assets_by_handle['preload-srcset-only'] );

		$expected = '<link rel="preload" href="http://client/images/hero.jpg" class="wp-asset-manager preload-srcset-only" as="image" media="all" imagesrcset="client/images/hero-480.jpg 480w, client/images/hero-800.jpg 800w" />';

		$this->assertEquals(
			$this->expected_after_kses( $expected ),
			capture( fn () => Preload::instance()->print_asset( $asset ) ),
			'`imagesrcset` alone should print, with `as` inferred and no other attributes.'
		);
	}

	/**
	 * Test that a string key in the array form of `imagesrcset` is used as the descriptor.
	 */
	public function test_imagesrcset_array_accepts_density_descriptors(): void {
		$this->assertSame(
			'hero.jpg 1x, hero-2x.jpg 2x',
			Preload::instance()->build_imagesrcset(
				[
					'1x' => 'hero.jpg',
					'2x' => 'hero-2x.jpg',
				]
			),
			'A string key should be used as the descriptor verbatim.'
		);
	}

	/**
	 * Test that a plain list of URLs is rejected rather than read as widths.
	 */
	public function test_imagesrcset_array_rejects_a_plain_list(): void {
		$this->setExpectedIncorrectUsage( 'am_preload' );

		$this->assertSame(
			'',
			Preload::instance()->build_imagesrcset( [ 'hero-480.jpg', 'hero-800.jpg' ] ),
			'A list of URLs has no descriptors, so nothing should be built from it.'
		);
	}

	/**
	 * Test that `as` is inferred as `image` from `imagesrcset`.
	 */
	public function test_imagesrcset_infers_as_image(): void {
		$asset = [
			'handle'      => 'preload-inferred',
			'src'         => 'hero.jpg',
			'imagesrcset' => 'hero-480.jpg 480w, hero-800.jpg 800w',
		];

		$this->assertEquals(
			array_merge( $asset, [ 'as' => 'image' ] ),
			Preload::instance()->post_validate_asset( $asset ),
			"Should infer an 'as' of 'image' when `imagesrcset` is supplied without one"
		);
	}

	/**
	 * Test that `imagesizes` alone neither infers `as` nor prints.
	 *
	 * `imagesizes` picks from the candidates in `imagesrcset`. Without one there is nothing
	 * to pick from, so the attribute says nothing about the asset type and does nothing.
	 */
	public function test_imagesizes_without_imagesrcset_is_dropped(): void {
		$this->setExpectedIncorrectUsage( 'am_preload' );

		$asset = [
			'handle'     => 'preload-sizes-only',
			'src'        => 'hero.jpg',
			'imagesizes' => '(max-width: 600px) 480px, 800px',
		];

		$validated = Preload::instance()->post_validate_asset( $asset );

		$this->assertArrayNotHasKey( 'imagesizes', $validated, '`imagesizes` should be dropped without an `imagesrcset`.' );
		$this->assertArrayNotHasKey( 'as', $validated, "Should not infer an 'as' from `imagesizes` alone." );
	}

	/**
	 * Test that an unsupported `fetchpriority` value is reported and dropped.
	 */
	public function test_invalid_fetchpriority_is_dropped(): void {
		$this->setExpectedIncorrectUsage( 'am_preload' );

		$asset = [
			'handle'        => 'preload-bad-priority',
			'src'           => 'hero.jpg',
			'as'            => 'image',
			'fetchpriority' => 'urgent',
		];

		$this->assertArrayNotHasKey(
			'fetchpriority',
			Preload::instance()->post_validate_asset( $asset ),
			'An unsupported `fetchpriority` value should be dropped.'
		);
	}

	public function test_post_validate_asset(): void {
		// Adds the expected attributes for preloading a font.
		$font_asset    = [
			'handle' => 'preload-as-font',
			'src'    => 'my-font.woff2',
		];
		$expected_font = array_merge(
			$font_asset,
			[
				'as'          => 'font',
				'crossorigin' => true,
				'mime_type'   => 'font/woff2',
			]
		);

		$actual_font_output = Preload::instance()->post_validate_asset( $font_asset );

		$this->assertEquals(
			$expected_font,
			$actual_font_output,
			"Should add the 'as' and 'crossorigin' arguments for a preloaded font"
		);

		// Shouldn't alter the option values for an "unknown" file type.
		$unknown_asset = [
			'handle' => 'preload-as-audio',
			'src'    => 'my-song.mp3',
		];

		$actual_script_output = Preload::instance()->set_asset_types( $unknown_asset );

		$this->assertEquals(
			$unknown_asset,
			$actual_script_output,
			"Should not add the 'as' and 'mime_type' arguments for an unknown file type"
		);
	}

	public function test_print_asset(): void {
		// Throws an error for missing `as` value.
		$unknown_asset = [
			'handle' => 'preload-as-audio',
			'src'    => 'my-song.mp3',
		];

		$error = capture( fn () => Preload::instance()->print_asset( $unknown_asset ) );
		$this->assertStringContainsString( '<strong>ENQUEUE ERROR</strong>: <em>invalid_preload_as_attribute</em>', $error, "Should throw invalid_preload_attribute error if the 'as' attribute is missing" );
	}

	public function test_set_asset_types(): void {
		$actual_output = Preload::instance()->set_asset_types( [] );

		$this->assertEquals(
			$actual_output,
			[],
			'Should return an empty array if no arguments are passed'
		);

		// Adds the expected attributes for preloading a CSS file.
		$expected_style = array_merge(
			$this->test_style,
			[
				'as'        => 'style',
				'mime_type' => 'text/css',
			]
		);

		$actual_output = Preload::instance()->set_asset_types( $this->test_style );

		$this->assertEquals(
			$expected_style,
			$actual_output,
			"Should add the 'as' and 'mime_type' arguments for a preloaded CSS file"
		);

		// Adds the expected attributes for preloading a font.
		$font_asset    = [
			'handle' => 'preload-type-font',
			'src'    => 'my-font.woff2',
		];
		$expected_font = array_merge(
			$font_asset,
			[
				'as'        => 'font',
				'mime_type' => 'font/woff2',
			]
		);

		$actual_font_output = Preload::instance()->set_asset_types( $font_asset );

		$this->assertEquals(
			$expected_font,
			$actual_font_output,
			"Should add the 'as', 'mime_type' and 'crossorigin' arguments for a preloaded font"
		);

		// Adds the expected attributes for preloading a JS file.
		$script_asset    = [
			'handle' => 'preload-type-script',
			'src'    => 'my-script.js',
		];
		$expected_script = array_merge(
			$script_asset,
			[
				'as'        => 'script',
				'mime_type' => 'text/javascript',
			]
		);

		$actual_script_output = Preload::instance()->set_asset_types( $script_asset );

		$this->assertEquals(
			$expected_script,
			$actual_script_output,
			"Should add the 'as' and 'mime_type' arguments for a preloaded JS file"
		);
	}
}
