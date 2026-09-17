<?php
use PHPUnit\Framework\TestCase;

final class SecurityPrimitivesTest extends TestCase
{
    public function testPasswordPolicyRules(): void
    {
        $p = new Password_policy(['min_length' => 10]);
        $this->assertNotEmpty($p->validate('short'));
        $this->assertNotEmpty($p->validate('alllowercase1!'));
        $this->assertSame([], $p->validate('Strong#Pass2026'));
        $this->assertTrue($p->isExpired(date('Y-m-d H:i:s', strtotime('-100 days'))));
        $this->assertFalse($p->isExpired(date('Y-m-d H:i:s')));
    }

    public function testPasswordHashIsNotPlaintextAndVerifies(): void
    {
        $h = Password_policy::hash('Strong#Pass2026');
        $this->assertStringNotContainsString('Strong#Pass2026', $h);
        $this->assertTrue(Password_policy::verify('Strong#Pass2026', $h));
        $this->assertFalse(Password_policy::verify('wrong', $h));
    }

    public function testJwtRoundTripAndTamperDetection(): void
    {
        $jwt = new Jwt(str_repeat('s', 40));
        $t = $jwt->encode(['sub' => 7, 'typ' => 'access'], 60);
        $this->assertSame(7, $jwt->decode($t)['sub']);
        $this->expectException(Authentication_exception::class);
        $jwt->decode(substr($t, 0, -2) . 'xx');
    }

    public function testJwtExpired(): void
    {
        $jwt = new Jwt(str_repeat('s', 40));
        $t = $jwt->encode(['sub' => 1], -10);
        $this->expectException(Authentication_exception::class);
        $jwt->decode($t);
    }

    public function testJwtRejectsShortSecret(): void
    {
        $this->expectException(RuntimeException::class);
        new Jwt('short');
    }

    public function testNumberingFormat(): void
    {
        $ts = mktime(0, 0, 0, 6, 15, 2026);
        $this->assertSame('ADJ/HO/202606/00042', Numbering_service::format('ADJ/{BRANCH}/{YYYY}{MM}/{SEQ:5}', 42, ['BRANCH' => 'HO'], $ts));
        $this->assertSame('B2606000007', Numbering_service::format('B{YY}{MM}{SEQ:6}', 7, [], $ts));
    }

    public function testPaginatorBounds(): void
    {
        [$page, $per, $offset] = Paginator::fromInput(['page' => '-3', 'per_page' => '99999']);
        $this->assertSame([1, 200, 0], [$page, $per, $offset]);
        $p = new Paginator([], 101, 3, 25);
        $this->assertSame(5, $p->pages());
    }
}
