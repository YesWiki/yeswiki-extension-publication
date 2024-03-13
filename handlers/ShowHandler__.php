<?php

namespace YesWiki\Publication;

use YesWiki\Core\Service\AclService;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Publication\Service\Publication;

class ShowHandler__ extends YesWikiHandler
{
    public function run()
    {
        if ($this->getService(AclService::class)->hasAccess('read') && (
            isset($this->wiki->page['metadatas']['publication-title']) ||
            isset($this->wiki->page['metadatas']['publication']['title'])
        )) {
            $metadata = $this->getService(Publication::class)->getOptions($this->wiki->page['metadatas']);

            $output = $this->render('@publication/show.twig', [
                'hasWriteAccess' => $this->wiki->HasAccess('write'),
                'hasDeleteAccess' => $this->wiki->UserIsAdmin() || $this->wiki->UserIsOwner(),
                'metadata' => $metadata,
                'page' => $this->wiki->page
              ]);

            $this->output = preg_replace(
                '#<div class="page".+<hr class="hr_clear" />#siU',
                '<div class="page" >'. $output .'<hr class="hr_clear" />',
                $this->output
            );
        }
    }
}
