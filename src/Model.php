<?php

namespace Arionum\Arionum;

use Arionum\Arionum\Helpers\Log;
use Arionum\Arionum\Traits\HasConfig;
use Arionum\Arionum\Traits\HasDatabase;
use Arionum\Arionum\Traits\HasLogging;

/**
 * Class Model
 */
class Model
{
    use HasConfig, HasDatabase, HasLogging;

    /**
     * Model constructor.
     * @param Config $config
     * @param DB     $database
     */
    public function __construct(Config $config, DB $database)
    {
        $this->setConfig($config);
        $this->setDatabase($database);

        $logger = new Log($this->config);
        $this->setLogger($logger);
    }
}
