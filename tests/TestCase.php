<?php
/**
 * Asset Manager Tests: Base test case.
 *
 * Shared fixtures, filters, and state reset for the suite.
 *
 * @package Asset_Manager
 */

declare(strict_types=1);

namespace Alley\WP\Asset_Manager\Tests;

use Alley\WP\Asset_Manager\Scripts;
use Alley\WP\Asset_Manager\Styles;
use Alley\WP\Asset_Manager\Preload;
use Alley\WP\Asset_Manager\SVG_Sprite;
use Mantle\Testing\Concerns\Refresh_Database;

/**
 * TestCase class.
 */
abstract class TestCase extends \Mantle\Testkit\Test_Case {
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

	public array $global_attributes = [
		'focusable'   => 'false',
		'aria-hidden' => 'true',
	];

	public string $svg_directory;

	protected function setUp(): void {
		parent::setUp();

		// Add test conditions
		remove_all_filters( 'am_asset_conditions', 10 );
		add_filter(
			'am_asset_conditions',
			fn () => [
				'global'            => true,
				'article_post_type' => true,
				'single'            => true,
				'archive'           => false,
				'has_slideshow'     => false,
				'has_video'         => false,
			]
		);

		add_filter( 'am_inline_script_context', fn () => 'assetContext' );

		$this->svg_directory = __DIR__ . '/mocks/';

		add_filter( 'am_modify_svg_directory', fn () => $this->svg_directory );

		$this->global_attributes = [
			'focusable'   => 'false',
			'aria-hidden' => 'true',
		];

		add_filter( 'am_global_svg_attributes', fn ( $attrs ) => array_merge( $attrs, $this->global_attributes ) );

		$this->reset_assets();
		$this->acting_as( 'administrator' );
	}

	/**
	 * Adapt an expected markup string to the `wp_kses()` behavior of the running WordPress version.
	 *
	 * WordPress 7.0 rebuilt `wp_kses_hair()` on top of `WP_HTML_Tag_Processor`, which changed
	 * two things about escaped output (see https://core.trac.wordpress.org/ticket/63724):
	 *
	 * 1. Attribute *names* are lowercased. Element names and attribute *values* are not, so
	 *    `<animateMotion attributeType="XML">` becomes `<animateMotion attributetype="XML">`.
	 * 2. Single quotes are encoded as `&apos;`.
	 *
	 * Both are cosmetic. HTML parsers restore the canonical casing of SVG attributes via the
	 * spec's attribute adjustment table, and `&apos;` is decoded before an inline handler is
	 * compiled, so the rendered result is identical on either version.
	 *
	 * The affected attributes are listed explicitly rather than lowercasing every attribute
	 * name, so that an escaping regression outside this known set still fails the test.
	 *
	 * @param string $html Markup expected under WordPress 6.9 and earlier.
	 * @return string Markup expected under the running WordPress version.
	 */
	protected function expected_after_kses( string $html ): string {
		if ( version_compare( $GLOBALS['wp_version'], '7.0', '<' ) ) {
			return $html;
		}

		$html = (string) preg_replace_callback(
			'/\b(viewBox|attributeName|attributeType|repeatCount|gradientTransform)=/',
			static fn ( array $matches ): string => strtolower( $matches[1] ) . '=',
			$html
		);

		return str_replace( "'", '&apos;', $html );
	}

	public function reset_assets(): void {
		Scripts::instance()->assets           = [];
		Scripts::instance()->assets_by_handle = [];
		Scripts::instance()->asset_handles    = [];
		Styles::instance()->assets            = [];
		Styles::instance()->assets_by_handle  = [];
		Styles::instance()->asset_handles     = [];
		Preload::instance()->assets           = [];
		Preload::instance()->assets_by_handle = [];
		Preload::instance()->asset_handles    = [];

		SVG_Sprite::instance()->asset_handles         = [];
		SVG_Sprite::instance()->sprite_map            = [];
		SVG_Sprite::instance()->kses_svg_allowed_tags = [
			'svg' => [],
			'use' => [
				'href' => true,
			],
		];
		SVG_Sprite::$_global_attributes               = [];
		SVG_Sprite::$_svg_directory                   = null;
		SVG_Sprite::instance()->create_sprite_sheet();

		wp_deregister_script( 'my-test-asset' );
		wp_deregister_script( 'test-asset-two' );
	}
}
