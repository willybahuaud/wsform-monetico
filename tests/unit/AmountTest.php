<?php
/**
 * Amount tests.
 *
 * @package WSForm_Monetico\Tests
 */

use PHPUnit\Framework\TestCase;
use WSForm_Monetico\Amount;

/**
 * Covers the amount formats met in forms and in Monetico notifications.
 */
class AmountTest extends TestCase {

	/**
	 * Field values and the amount they mean.
	 *
	 * @return array<string,array{0:mixed,1:float}>
	 */
	public function field_values() {
		return array(
			'integer'               => array( '12', 12.0 ),
			'dot decimals'          => array( '12.5', 12.5 ),
			'comma decimals'        => array( '12,50', 12.5 ),
			'french thousands'      => array( '1 234,56', 1234.56 ),
			'german thousands'      => array( '1.234,56', 1234.56 ),
			'english thousands'     => array( '1,234.56', 1234.56 ),
			'thousands only'        => array( '1.234', 1234.0 ),
			'currency sign'         => array( '12,50 €', 12.5 ),
			'array from a select'   => array( array( '30' ), 30.0 ),
			'empty'                 => array( '', 0.0 ),
			'zero'                  => array( '0,00', 0.0 ),
			'text'                  => array( 'gratuit', 0.0 ),
			'minus sign is ignored' => array( '-5', 5.0 ),
		);
	}

	/**
	 * Field values are parsed.
	 *
	 * @dataProvider field_values
	 *
	 * @param mixed $raw_value Field value.
	 * @param float $expected  Expected amount.
	 *
	 * @return void
	 */
	public function test_parse( $raw_value, $expected ) {
		$this->assertSame( $expected, Amount::parse( $raw_value ) );
	}

	/**
	 * Amounts are sent to Monetico with two decimals and a supported currency.
	 *
	 * @return void
	 */
	public function test_to_monetico() {
		$this->assertSame( '12.50EUR', Amount::to_monetico( 12.5, 'eur' ) );
		$this->assertSame( '1234.00CHF', Amount::to_monetico( 1234, 'CHF' ) );
		$this->assertSame( '5.00EUR', Amount::to_monetico( 5, 'XXX' ) );
	}

	/**
	 * Monetico may drop trailing zeros.
	 *
	 * @return void
	 */
	public function test_normalize_monetico() {
		$this->assertSame( '5.00EUR', Amount::normalize_monetico( '5EUR' ) );
		$this->assertSame( '5.50EUR', Amount::normalize_monetico( '5.5eur' ) );
		$this->assertSame( '12.50EUR', Amount::normalize_monetico( '12.50EUR' ) );
		$this->assertSame( '', Amount::normalize_monetico( '12,50EUR' ) );
		$this->assertSame( '', Amount::normalize_monetico( '12.50' ) );
		$this->assertSame( '', Amount::normalize_monetico( '' ) );
	}
}
