<?php

namespace YesWiki\Publication\Exception;

/** A failed print, carrying the markup the browser had loaded so an admin can see what went wrong. */
class ExceptionWithHtml extends \Exception
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, private string $html = '')
    {
        parent::__construct($message, $code, $previous);
    }

    public function getHtml(): string
    {
        return $this->html;
    }
}
