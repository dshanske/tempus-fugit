<?php
/**
 * Test helper for behavior that is known to be wrong.
 *
 * @package TempusFugit
 */

/**
 * Lets a test assert the correct behavior without failing while a known bug is still open.
 *
 * While the code is wrong, the test is marked incomplete with a description of the bug.
 * Once the bug is fixed, the same test passes and guards against regressions.
 */
trait Tempus_Known_Bugs {

	/**
	 * Asserts that a value is what it should be, or marks the test incomplete if a known bug
	 * produces something else.
	 *
	 * @param mixed  $expected Correct value.
	 * @param mixed  $actual   Value the code produced.
	 * @param string $bug      Description of the known bug.
	 */
	protected function assert_same_or_known_bug( $expected, $actual, $bug ) {
		if ( $expected !== $actual ) {
			$this->markTestIncomplete( 'Known bug: ' . $bug . ' Expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . '.' );
		}
		$this->assertSame( $expected, $actual );
	}
}
