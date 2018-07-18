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
        if ($this->config->get('enable_logging')
            && is_dir(dirname($this->config->get('log_file')))
            && is_writable(dirname($this->config->get('log_file')))
        ) {
            file_put_contents($this->config->get('log_file'), $logInfo, FILE_APPEND);
        }
    }
}
