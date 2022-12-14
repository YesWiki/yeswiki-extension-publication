<?php

namespace YesWiki\Publication;

use YesWiki\Core\YesWikiHandler;
use YesWiki\Publication\Controller\PdfController;

class PdfHandler extends YesWikiHandler
{
    public function run()
    {
        return $this->getService(PdfController::class)->run();
    }
}
