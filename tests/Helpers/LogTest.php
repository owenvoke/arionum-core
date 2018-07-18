<?php

namespace Arionum\Arionum\Helpers;

use Arionum\Arionum\Config;
use Arionum\Arionum\Traits\HasConfig;
use Arionum\Arionum\Traits\HasLogging;
use PHPUnit\Framework\TestCase;

/**
 * Class LogTest
 */
class LogTest extends TestCase
{
    use HasConfig, HasLogging;

    const TEST_DATA = 'testing123';
    const BUILD_DIRECTORY = __DIR__.'/../../build';
    const LOG_FILE_LOCATION = self::BUILD_DIRECTORY.'/aro.log';

    /**
     * Set up the requirements for the unit tests.
     */
    public function setUp()
    {
        if (!is_dir(self::BUILD_DIRECTORY)) {
            mkdir(self::BUILD_DIRECTORY);
        }

        if (file_exists(self::LOG_FILE_LOCATION)) {
            unlink(self::LOG_FILE_LOCATION);
        }

        $this->setConfig(new Config([
            'enable_logging' => false,
            'log_file'       => self::LOG_FILE_LOCATION,
        ]));

        $this->setLogger(new Log($this->config));
    }

    /**
     * @test
     * @throws \Exception
     */
    public function itLogsToTheConsole()
    {
        $this->expectOutputRegex('/'.self::TEST_DATA.'/');

        $this->log->log(self::TEST_DATA);
    }

    /**
     * @test
     * @throws \Exception
     */
    public function itLogsToAFile()
    {
        $this->expectOutputRegex('/'.self::TEST_DATA.'/');

        $this->config->set('enable_logging', true);
        $this->log->log(self::TEST_DATA);

        $this->assertFileExists(self::LOG_FILE_LOCATION);
    }
}
