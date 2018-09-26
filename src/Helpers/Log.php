<?php

namespace Arionum\Core\Helpers;

use Arionum\Core\Config;

/**
 * Class Log
 */
class Log
{
    /**
     * Log function, this only shows in the CLI.
     * @param string $logData
     * @return void
     * @throws \Exception
     *
     * @todo Convert to Monolog
     * @link https://github.com/pxgamer/arionum/issues/3
     */
    public static function log(string $logData): void
    {
        $logInfo = self::getLogFormat($logData);

        self::logToConsole($logInfo);

        self::logToFile($logInfo);
    }

    /**
     * @param string $logData
     * @return string
     * @throws \Exception
     */
    private static function getLogFormat(string $logData): string
    {
        $date = date('[Y-m-d H:i:s]');
        $trace = debug_backtrace();
        $traceData = $trace[count($trace) - 1];
        $file = substr($traceData['file'], strrpos($traceData['file'], DIRECTORY_SEPARATOR) + 1);

        $logInfo = $date.' '.$file.':'.$traceData['line'];

        if (!empty($traceData['class'])) {
            $logInfo .= '---'.$traceData['class'];
        }

        if (!empty($traceData['function']) && $traceData['function'] != '_log') {
            $logInfo .= '->'.$traceData['function'].'()';
        }

        return $logInfo.' '.$logData.' '.PHP_EOL;
    }

    /**
     * @param string $logInfo
     * @return void
     * @throws \Exception
     */
    private static function logToConsole(string $logInfo): void
    {
        if (php_sapi_name() === 'cli') {
            echo $logInfo;
        }
    }

    /**
     * @param string $logInfo
     * @return void
     * @throws \Exception
     */
    private static function logToFile(string $logInfo): void
    {
        $logFile = Config::get('log_file');
        $logDirectory = dirname($logFile);

        if (Config::get('enable_logging')
            && is_dir($logDirectory)
            && is_writable($logDirectory)
        ) {
            file_put_contents($logFile, $logInfo, FILE_APPEND);
        }
    }
}
