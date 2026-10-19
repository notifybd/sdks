<?php
/**
 * Runs the shared webhook vectors against the plugin's verifier. No WordPress needed.
 *
 *     composer require --dev phpunit/phpunit && vendor/bin/phpunit tests
 */

define( 'NOTIFY_BD_TESTING', true );
require_once __DIR__ . '/../notify-bd/includes/class-notify-bd-signature.php';

use PHPUnit\Framework\TestCase;

final class SignatureTest extends TestCase {

	/** @return array<string,mixed> */
	private static function fixture(): array {
		$path = __DIR__ . '/../../../spec/fixtures/webhook-signature.json';

		return json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
	}

	/** @return iterable<string,array{0:array<string,mixed>}> */
	public static function vectors(): iterable {
		foreach ( self::fixture()['cases'] as $case ) {
			yield $case['name'] => array( $case );
		}
	}

	/** @dataProvider vectors */
	public function test_shared_vectors( array $case ): void {
		$fixture = self::fixture();
		$result  = Notify_BD_Signature::verify(
			$case['rawBody'] ?? $fixture['rawBody'],
			$case['header'],
			$fixture['secret'],
			$fixture['toleranceSeconds'],
			$case['now']
		);

		if ( $case['valid'] ) {
			$this->assertTrue( $result );
		} else {
			$this->assertSame( $case['reason'], $result );
		}
	}

	public function test_a_missing_header_is_malformed(): void {
		$this->assertSame( 'malformed_header', Notify_BD_Signature::verify( '{}', null, 'secret' ) );
	}
}
