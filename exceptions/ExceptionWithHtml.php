<?php

namespace YesWiki\Publication\Exception;

use Exception;
use Throwable;

class ExceptionWithHtml extends Exception implements Throwable
{
    protected $html;

    public function __construct($message = '', $code = 0, Throwable $previous = null, string $html)
    {
        parent::__construct($message, $code, $previous);
        $this->html = $html;
    }

    public function getHtml(): string
    {
        return $this->html;
    }
}
