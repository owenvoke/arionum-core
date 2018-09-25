<?php

namespace Arionum\Core\Helpers;

use Arionum\Core\Config;
use Arionum\Core\Traits\HasConfig;
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
     * @expectedException \Arionum\Core\Exceptions\ConfigPropertyNotFoundException
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
