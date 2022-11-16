<?php

namespace YesWiki\Publication;

use YesWiki\Core\YesWikiAction;

class PageBreakAction extends YesWikiAction
{
    public function run()
    {
        return "\n<hr class=\"pagebreak\" aria-hidden>\n";
    }
}
