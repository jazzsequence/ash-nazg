<?php
/**
 * Basic sanity test.
 *
 * @package Pantheon\AshNazg
 */

use PHPUnit\Framework\TestCase;

/**
 * Test basic functionality.
 */
class Test_Basic extends TestCase {

	/**
	 * Test that true is true.
	 */
	public function test_true_is_true() {
		$this->assertTrue( true );
	}

	/**
	 * Test basic math.
	 */
	public function test_math() {
		$this->assertEquals( 4, 2 + 2 );
	}
}
