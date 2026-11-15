<?php

use App\Services\WsToken;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class WsTokenTest extends CIUnitTestCase
{
    private const USER = '01936b2a-1c2d-7e3f-8a4b-5c6d7e8f9a0b';

    public function testAFreshTokenVerifiesToItsUser(): void
    {
        $token = new WsToken('rahasia-panjang-untuk-uji');

        $this->assertSame(self::USER, $token->verify($token->issue(self::USER)));
    }

    public function testAnExpiredTokenIsRejected(): void
    {
        $token  = new WsToken('rahasia-panjang-untuk-uji');
        $issued = $token->issue(self::USER, 60, 1_000_000);

        $this->assertSame(self::USER, $token->verify($issued, 1_000_059));
        $this->assertNull($token->verify($issued, 1_000_061));
    }

    public function testATamperedUserIdIsRejected(): void
    {
        $token = new WsToken('rahasia-panjang-untuk-uji');
        [, $expires, $signature] = explode('.', $token->issue(self::USER, 60, time()));
        $forged = rtrim(strtr(base64_encode('01936b2a-0000-7000-8000-000000000000'), '+/', '-_'), '=') . '.' . $expires . '.' . $signature;

        $this->assertNull($token->verify($forged));
    }

    public function testATokenSignedWithAnotherSecretIsRejected(): void
    {
        $issued = (new WsToken('rahasia-satu-panjang-sekali'))->issue(self::USER);

        $this->assertNull((new WsToken('rahasia-dua-panjang-sekali'))->verify($issued));
    }

    public function testMalformedTokensAreRejected(): void
    {
        $token = new WsToken('rahasia-panjang-untuk-uji');

        foreach (['', 'abc', 'a.b', 'a.b.c.d', '..', 'a.notanumber.sig'] as $bad) {
            $this->assertNull($token->verify($bad), "token '{$bad}' harus ditolak");
        }
    }

    public function testWithoutASecretNothingIsIssuedOrAccepted(): void
    {
        $token = new WsToken('');

        $this->assertNull($token->verify('a.1.b'));
        $this->expectException(RuntimeException::class);

        $token->issue(self::USER);
    }
}
