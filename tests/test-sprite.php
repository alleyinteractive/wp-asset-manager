<?php

namespace Asset_Manager_Tests;

class Asset_Manager_Sprite_Tests extends Asset_Manager_Test {

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
}
