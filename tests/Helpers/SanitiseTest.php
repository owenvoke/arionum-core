<?php

namespace Arionum\Core\Helpers;

use PHPUnit\Framework\TestCase;

/**
 * Class SanitiseTest
 */
class SanitiseTest extends TestCase
{
    private const INVALID_INPUT = '~~~';
    private const VALID_INPUT_ALPHANUMERIC = 'ABC';
    private const VALID_INPUT_HOST = 'https://test.com';
    private const VALID_INPUT_IP = '127.0.0.1';

    /**
     * @test
     */
    public function itDoesNotSanitiseAStringThatIsAlphanumeric()
    {
        $this->assertEquals(self::VALID_INPUT_ALPHANUMERIC, Sanitise::alphanumeric(self::VALID_INPUT_ALPHANUMERIC));
    }

    /**
     * @test
     */
    public function itSanitisesAStringThatIsNotAlphanumeric()
    {
        $this->assertEquals('', Sanitise::alphanumeric(self::INVALID_INPUT));
    }

    /**
     * @test
     */
    public function itDoesNotSanitiseAStringThatIsAHost()
    {
        $this->assertEquals(self::VALID_INPUT_HOST, Sanitise::host(self::VALID_INPUT_HOST));
    }

    /**
     * @test
     */
    public function itSanitisesAStringThatIsNotAHost()
    {
        $this->assertEquals('', Sanitise::host(self::INVALID_INPUT));
    }

    /**
     * @test
     */
    public function itDoesNotSanitiseAStringThatIsAnIp()
    {
        $this->assertEquals(self::VALID_INPUT_IP, Sanitise::ip(self::VALID_INPUT_IP));
    }

    /**
     * @test
     */
    public function itSanitisesAStringThatIsNotAnIp()
    {
        $this->assertEquals('', Sanitise::ip(self::INVALID_INPUT));
    }
}
