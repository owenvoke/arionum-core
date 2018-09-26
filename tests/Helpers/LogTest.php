<?php

namespace Arionum\Core\Helpers;

use Arionum\Core\Config;
use Arionum\Core\Helpers\Log;
use PHPUnit\Framework\TestCase;

/**
 * Class LogTest
 */
class LogTest extends TestCase
{
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

        Config::setGlobal([
            'enable_logging' => false,
            'log_file'       => self::LOG_FILE_LOCATION,
        ]);
    }

    /**
     * @test
     * @throws \Exception
     */
    public function itLogsToTheConsole()
    {
        $this->expectOutputRegex('/'.self::TEST_DATA.'/');

        Log::log(self::TEST_DATA);
    }

    /**
     * @test
     * @throws \Exception
     */
    public function itLogsToAFile()
    {
        $this->expectOutputRegex('/'.self::TEST_DATA.'/');

        Config::set('enable_logging', true);
        Log::log(self::TEST_DATA);

        $this->assertFileExists(self::LOG_FILE_LOCATION);
    }
}
