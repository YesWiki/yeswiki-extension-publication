<?php

namespace YesWiki\Publication;

use YesWiki\Core\YesWikiHandler;

class PdfHandler extends YesWikiHandler
{
    public function run()
    {
        return $this->renderInSquelette('@publication/handler-pdf.twig', [
            'isAdmin' => $this->wiki->UserIsAdmin(),
            'pageTag' => $this->wiki->tag
        ]);
    }
}
