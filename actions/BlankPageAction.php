<?php

namespace YesWiki\Publication;

use YesWiki\Core\YesWikiAction;

class BlankPageAction extends YesWikiAction
{
    public function run()
    {
        return "\n<div class=\"blank-page\" aria-label=\"". _t("PUBLICATION_BLANK_PAGE") ."\"></div>\n";
    }
}
