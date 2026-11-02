<?php declare(strict_types=1);

namespace CartographyTest\View\Helper;

use Cartography\View\Helper\Cartography;
use PHPUnit\Framework\TestCase;
use SpamGuard\Stdlib\FormToken;

class CartographyTest extends TestCase
{
    public function testNoChallengeWithoutSpamGuard(): void
    {
        $this->assertNull($this->challenge(new Cartography()));
    }

    public function testChallengeIsReadableBySpamGuard(): void
    {
        if (!class_exists(FormToken::class)) {
            $this->markTestSkipped('Module SpamGuard not available.');
        }
        $formToken = new FormToken('secret');
        $challenge = $this->challenge(new Cartography($formToken, 3));

        $this->assertSame(3, $challenge['difficulty']);
        $this->assertMatchesRegularExpression('~^[0-9a-f]{32}$~', $challenge['salt']);

        $ip = (string) (new \Laminas\Http\PhpEnvironment\RemoteAddress())->getIpAddress();
        $token = $formToken->read($challenge['token'], $ip);
        $this->assertNotNull($token);
        $this->assertSame($challenge['salt'], $token['salt']);
        $this->assertNull($formToken->read($challenge['token'], '192.0.2.1'));
    }

    protected function challenge(Cartography $helper): ?array
    {
        $method = new \ReflectionMethod($helper, 'spamGuardChallenge');
        $method->setAccessible(true);
        return $method->invoke($helper);
    }
}
