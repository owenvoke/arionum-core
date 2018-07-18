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
     *
     * @todo Convert to Monolog
     * @link https://github.com/pxgamer/arionum/issues/3
     * @throws \Exception
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
        $lineNumber = count($trace) - 1;
        $file = substr($trace[$lineNumber]['file'], strrpos($trace[$lineNumber]['file'], '/') + 1);

        $logInfo = $date.' '.$file.':'.$trace[$lineNumber]['line'];

        if (!empty($trace[$lineNumber]['class'])) {
            $logInfo .= '---'.$trace[$lineNumber]['class'];
        }

        if (!empty($trace[$lineNumber]['function']) && $trace[$lineNumber]['function'] != '_log') {
            $logInfo .= '->'.$trace[$lineNumber]['function'].'()';
        }

        return $logInfo.' '.$logData.' '.PHP_EOL;
    }

    /**
     * @param string $logInfo
     * @throws \Exception
     */
    private function logToConsole(string $logInfo)
    {
        if (php_sapi_name() === 'cli') {
            echo $logInfo;
        }
    }

    /**
     * @param string $logInfo
     * @throws \Exception
     */
    private function logToFile(string $logInfo)
    {
        if ($this->config->get('enable_logging') && is_writable($this->config->get('log_file'))) {
            file_put_contents($this->config->get('log_file'), $logInfo, FILE_APPEND);
        }
    }
}
