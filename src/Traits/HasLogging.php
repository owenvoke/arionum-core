<?php

namespace Arionum\Core\Traits;

use Arionum\Core\Helpers\Log;

/**
 * Trait HasLogging
 */
trait HasLogging
{
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
