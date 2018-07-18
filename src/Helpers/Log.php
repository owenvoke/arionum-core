<?php

namespace Arionum\Arionum\Helpers;

use Arionum\Arionum\Config;
use Arionum\Arionum\Traits\HasConfig;

/**
 * Class Log
 */
class Log
{
    use HasConfig;

    /**
     * Log constructor.
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->setConfig($config);
    }

    /**
     * Log function, this only shows in the CLI.
     * @param string $logData
     * @return void
     * @throws \Exception
     *
     * @todo Convert to Monolog
     * @link https://github.com/pxgamer/arionum/issues/3
     */
    public function log(string $logData): void
    {
        $logInfo = $this->getLogFormat($logData);

        $this->logToConsole($logInfo);

        $this->logToFile($logInfo);
    }

    /**
     * @param string $logData
     * @return string
     * @throws \Exception
     */
    private function getLogFormat(string $logData): string
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
    private function logToConsole(string $logInfo): void
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
    private function logToFile(string $logInfo): void
    {
        $logFile = $this->config->get('log_file');
        $logDirectory = dirname($logFile);

        if ($this->config->get('enable_logging')
            && is_dir($logDirectory)
            && is_writable($logDirectory)
        ) {
            file_put_contents($logFile, $logInfo, FILE_APPEND);
        }
    }
}
