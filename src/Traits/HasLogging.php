<?php

namespace Arionum\Arionum\Traits;

use Arionum\Arionum\Helpers\Log;

/**
 * Trait HasLogging
 */
trait HasLogging
{
    use HasConfig;

    /**
     * @var Log
     */
    protected $log;

    /**
     * @param Log|null $log
     * @return $this
     */
    protected function setLogger(Log $log = null)
    {
        $this->log = $log;

        return $this;
    }
}
