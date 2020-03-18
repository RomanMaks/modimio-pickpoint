<?php

namespace app\exceptions\PickPoint;

use Throwable;

/**
 * Базовое исключение для PickPoint API
 *
 * Class PickPointApiException
 * @package app\exceptions\PickPoint
 */
class PickPointApiException extends \Exception
{
    public function __construct($message = '', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}