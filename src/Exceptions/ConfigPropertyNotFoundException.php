<?php

namespace Arionum\Core\Exceptions;

/**
 * Class ConfigPropertyNotFoundException
 */
class ConfigPropertyNotFoundException extends \Exception
{
    /**
     * @var string
     */
    protected $message = 'The requested configuration property was not found.';
}
