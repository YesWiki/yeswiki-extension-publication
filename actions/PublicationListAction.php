<?php

namespace YesWiki\Publication;

use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Publication\Service\Publication;

class PublicationListAction extends YesWikiAction
{
    protected $dbService;
    protected $pageManager;
    protected $publicationService;

    public function formatArguments($args)
    {
        return [
            'pagenameprefix' => (empty($args['pagenameprefix']) || !is_string($args['pagenameprefix'])) ? 'Ebook' : $args['pagenameprefix'],
        ];
    }

    public function run()
    {
        // get Service
        $this->dbService = $this->getService(DbService::class);
        $this->pageManager = $this->getService(PageManager::class);
        $this->publicationService = $this->getService(Publication::class);

        $textInJson = 'publication\\":{\\"title\\":';
        $sql = <<<SQL
        SELECT DISTINCT resource FROM {$this->dbService->prefixTable('triples')}
          WHERE property="http://outils-reseaux.org/_vocabulary/metadata"
            AND (
                value LIKE "%publication-title%" OR value LIKE "%$textInJson%"
                )
            AND resource LIKE "{$this->arguments['pagenameprefix']}%"
          ORDER BY resource ASC
        SQL;
        $results = $this->dbService->LoadAll($sql);

        if (!empty($results)) {
            $pages = array_map(function ($page) {
                $metas = $this->pageManager->getMetadata($page['resource']);
                $page['_metas'] = $this->publicationService->getOptions($metas);
                return $page;
            }, $results);
        } else {
            $pages = [];
        }

        return $this->render('@publication/publicationlist.twig', [
            'hasWriteAccess' => $this->wiki->HasAccess('write'),
            'hasDeleteAccess' => $this->wiki->UserIsAdmin() || $this->wiki->UserIsOwner(),
            'pages' => $pages,
        ]);
    }
}
