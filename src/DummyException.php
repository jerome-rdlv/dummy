<?php


namespace Rdlv\WordPress\Dummy;


use Exception;
use Throwable;

class DummyException extends Exception
{
    /**
     * DummyException constructor.
     * @param string|string[] $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct(is_array($message) ? implode("\n", $message) : $message, $code, $previous);
    }
}