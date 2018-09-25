<?php

namespace Arionum\Core;

use Arionum\Core\Helpers\Log;
use Arionum\Core\Traits\HasConfig;
use Arionum\Core\Traits\HasDatabase;
use Arionum\Core\Traits\HasLogging;

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
