<?php

namespace Arionum\Arionum\Helpers;

use Arionum\Arionum\Config;
use Arionum\Arionum\Traits\HasConfig;

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
     * @param string $data
     * @return void
     *
     * @todo Convert to Monolog
     * @link https://github.com/pxgamer/arionum/issues/3
     * @throws \Exception
     */
    public function log(string $data): void
    {
        $date = date("[Y-m-d H:i:s]");
        $trace = debug_backtrace();
        $loc = count($trace) - 1;
        $file = substr($trace[$loc]['file'], strrpos($trace[$loc]['file'], "/") + 1);

        $res = "$date ".$file.":".$trace[$loc]['line'];

        if (!empty($trace[$loc]['class'])) {
            $res .= "---".$trace[$loc]['class'];
        }

        if (!empty($trace[$loc]['function']) && $trace[$loc]['function'] != '_log') {
            $res .= '->'.$trace[$loc]['function'].'()';
        }

        $res .= " $data \n";
        if (php_sapi_name() === 'cli') {
            echo $res;
        }

        if ($this->config->get('enable_logging') == true) {
            @file_put_contents($this->config->get('log_file'), $res, FILE_APPEND);
        }
    }
}
