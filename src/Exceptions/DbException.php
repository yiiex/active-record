<?php

namespace Yii1x\ActiveRecord\Exceptions;

use Exception;
use Throwable;

class DbException extends Exception
{

    /**
     * Constructor.
     * @param string $message PDO error message
     * @param integer $code PDO error code
     * @param mixed $errorInfo PDO error info
     * @param Throwable|null $previous the previous throwable used for the exception chaining
     */
    public function __construct(string $message, int $code = 0, public mixed $errorInfo = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}