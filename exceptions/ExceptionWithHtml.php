<?php

namespace YesWiki\Publication\Service;

use Exception;
use Throwable;

class ExceptionWithHtml extends Exception implements Throwable
{
    protected $thml;

    public function __construct($message = '', $code = 0, Throwable $previous = null, string $html)
    {
        parent::construct($message, $code, $previous);
        $this->html = $html;
    }

    public function getHtml(): string
    {
        return $this->html;
    }
}
