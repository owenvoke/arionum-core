<?php

namespace Arionum\Core\Helpers;

use Arionum\Core\Config;
use PHPUnit\Framework\TestCase;

/**
 * Class ConfigTest
 */
class ConfigTest extends TestCase
{
    /**
     * Set up the requirements for the unit tests.
     */
    public function setUp()
    {
        Config::setGlobal([
            'testing' => true,
        ]);
    }

    /**
     * @test
     * @throws \Exception
     */
    public function itCanGetAConfigParameter()
    {
        $this->assertEquals(true, Config::get('testing'));
    }

    /**
     * @test
     * @expectedException \Arionum\Core\Exceptions\ConfigPropertyNotFoundException
     * @throws \Exception
     */
    public function itThrowsAnExceptionWhenThePropertyIsNotSet()
    {
        $this->assertEquals(true, Config::get('does-not-exist'));
    }

    /**
     * @test
     * @throws \Arionum\Core\Exceptions\ConfigPropertyNotFoundException
     */
    public function itCanSetAConfigParameter()
    {
        Config::set('testing', false);

        $this->assertEquals(false, Config::get('testing'));
    }
}
