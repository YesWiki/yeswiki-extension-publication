<?php

namespace YesWiki\Publication;

use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\AssetsManager;
use YesWiki\Core\YesWikiHandler;

class __ShowHandler extends YesWikiHandler
{
    public function run()
    {
        if ($this->getService(AclService::class)->hasAccess('read') && isset($this->wiki->page['metadatas']['publication-title'])) {
            $this->getService(AssetsManager::class)->AddCSSFile('tools/publication/styles/publication.css');
        }
    }
}
