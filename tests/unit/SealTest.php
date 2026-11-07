<?php
/**
 * Seal tests.
 *
 * @package WSForm_Monetico\Tests
 */

use PHPUnit\Framework\TestCase;
use WSForm_Monetico\Monetico\Seal;

/**
 * The reference seals below were computed by the Monetico PHP kit
 * (HmacComputer) that this class replaces, with made-up keys.
 */
class SealTest extends TestCase {

	/**
	 * Seals produced by the Monetico kit.
	 *
	 * @return array<string,array{0:string,1:array<string,string>,2:string}>
	 */
	public function kit_vectors() {
		return array(
			'payment request'            => array(
				'0123456789ABCDEF0123456789ABCDEF01234567',
				array(
					'TPE'               => '1234567',
					'societe'           => 'demo',
					'lgue'              => 'FR',
					'version'           => '3.0',
					'reference'         => 'WSF-1-2-abc',
					'date'              => '07/11/2026:14:52:22',
					'montant'           => '12.50EUR',
					'contexte_commande' => 'eyJiaWxsaW5nIjp7fX0=',
					'mail'              => 'client@example.test',
				),
				'3669c3f9563e8de766f9e16bec3dabd12ef60606',
			),
			'IPN with lowercase key'     => array(
				'fedcba9876543210fedcba9876543210fedcba98',
				array(
					'TPE'         => '7654321',
					'date'        => '07/11/2026_a_14:52:46',
					'montant'     => '1.00EUR',
					'reference'   => 'REF42',
					'texte-libre' => '',
					'code-retour' => 'Annulation',
					'motifrefus'  => 'Refus',
					'usage'       => 'credit',
				),
				'507d37177ba63782545300d36391978e2edea998',
			),
			'old key ending with M'      => array(
				'0123456789ABCDEF0123456789ABCDEF0123459M',
				array(
					'TPE'       => '1111111',
					'reference' => 'X2',
					'montant'   => '5EUR',
				),
				'9201c7aea25b1997b4658864e467bc8c071346cf',
			),
			'UTF-8 and mixed case names' => array(
				'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
				array(
					'TPE'         => '2222222',
					'reference'   => 'R',
					'texte-libre' => 'Devis n°12 été',
					'a'           => '1',
					'B'           => '2',
					'9'           => 'x',
				),
				'ad059736695b9163296d4f14c81cce4547362f8d',
			),
		);
	}

	/**
	 * The seal matches the Monetico kit.
	 *
	 * @dataProvider kit_vectors
	 *
	 * @param string               $key           Made-up key.
	 * @param array<string,string> $fields        Fields.
	 * @param string               $expected_seal Seal computed by the kit.
	 *
	 * @return void
	 */
	public function test_compute_matches_kit( $key, array $fields, $expected_seal ) {
		$this->assertSame( $expected_seal, Seal::compute( $fields, $key ) );
	}

	/**
	 * Verification accepts the kit seal, in any case, and ignores the MAC field.
	 *
	 * @dataProvider kit_vectors
	 *
	 * @param string               $key           Made-up key.
	 * @param array<string,string> $fields        Fields.
	 * @param string               $expected_seal Seal computed by the kit.
	 *
	 * @return void
	 */
	public function test_verify_accepts_kit_seal( $key, array $fields, $expected_seal ) {
		$fields['MAC'] = strtoupper( $expected_seal );
		$this->assertTrue( Seal::verify( $fields, $key, $fields['MAC'] ) );
	}

	/**
	 * A changed field or a wrong key breaks the seal.
	 *
	 * @return void
	 */
	public function test_verify_rejects_tampering() {
		list( $key, $fields, $seal ) = $this->kit_vectors()['payment request'];

		$tampered            = $fields;
		$tampered['montant'] = '1.00EUR';
		$this->assertFalse( Seal::verify( $tampered, $key, $seal ) );
		$this->assertFalse( Seal::verify( $fields, str_repeat( 'B', 40 ), $seal ) );
		$this->assertFalse( Seal::verify( $fields, $key, '' ) );
	}

	/**
	 * Old keys ending with two letters follow the documented conversion.
	 *
	 * The kit gets this case wrong (hex2bin warning), so the expected value
	 * is the HMAC computed with the converted key ...34590.
	 *
	 * @return void
	 */
	public function test_old_key_with_letter_suffix() {
		$fields = array(
			'TPE'       => '1111111',
			'reference' => 'X1',
			'montant'   => '5EUR',
		);

		$this->assertSame( 'f355ad50f85a618f86cb3a4163ad12703b0acbcb', Seal::compute( $fields, '0123456789ABCDEF0123456789ABCDEF012345PM' ) );
	}

	/**
	 * Malformed keys never produce a seal.
	 *
	 * @return void
	 */
	public function test_invalid_keys() {
		$this->assertFalse( Seal::is_valid_key( '' ) );
		$this->assertFalse( Seal::is_valid_key( 'ABC' ) );
		$this->assertFalse( Seal::is_valid_key( str_repeat( 'Z', 40 ) ) );
		$this->assertTrue( Seal::is_valid_key( "0123456789ABCDEF 0123456789ABCDEF01234567\n" ) );
		$this->assertSame( '', Seal::compute( array( 'a' => 'b' ), 'nope' ) );
	}
}
