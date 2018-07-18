<?php

namespace Arionum\Arionum\Helpers;

use Arionum\Arionum\Config;
use Arionum\Arionum\Traits\HasConfig;
use PHPUnit\Framework\TestCase;

/**
 * Class ConfigTest
 */
class ConfigTest extends TestCase
{
    use HasConfig;

    /**
     * Set up the requirements for the unit tests.
     */
    public function setUp()
    {
        $this->setConfig(new Config([
            'testing' => true,
        ]));
    }

    /**
     * @test
     * @throws \Exception
     */
    public function itCanGetAConfigParameter()
    {
        $this->assertEquals(true, $this->config->get('testing'));
    }

    /**
     * @test
     * @expectedException \Arionum\Arionum\Exceptions\ConfigPropertyNotFoundException
     * @throws \Exception
     */
    public function itThrowsAnExceptionWhenThePropertyIsNotSet()
    {
        $this->assertEquals(true, $this->config->get('does-not-exist'));
    }

    /**
     * @test
     */
    public function itCanSetAConfigParameter()
    {
        $this->config->set('testing', false);

        $this->assertEquals(false, $this->config->get('testing'));
    }
}
