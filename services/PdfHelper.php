<?php

namespace YesWiki\Publication\Service;

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TemplateEngine;

class PdfHelper
{
    private const PATH_LIST = [
        'custom/templates/bazar/',
        'custom/templates/bazar/templates/',
        'themes/tools/bazar/presentation/templates/',
        'themes/tools/bazar/templates/',
        'tools/bazar/presentation/templates/',
    ];

    protected $dbService;
    protected $entryManager;
    protected $templateEngine;
    protected $pageManager;

    public function __construct(
        DbService $dbService,
        EntryManager $entryManager,
        TemplateEngine $templateEngine,
        PageManager $pageManager
    ) {
        $this->dbService = $dbService;
        $this->entryManager = $entryManager;
        $this->templateEngine = $templateEngine;
        $this->pageManager = $pageManager;
    }

    /**
     * return content to add to sha1 formatted as array
     * @param string $pageTag
     * @param null|string $get
     * @return array
     */
    public function getDataToCheck(string $pageTag, ?string $via = null): array
    {
        $return = [];
        if ($this->entryManager->isEntry($pageTag)) {
            $entry = $this->entryManager->getOne($pageTag);
            $formId = $entry['id_typeannonce'];
            $templatePath = $this->getTemplatePathFormFormId($formId);
            if (!empty($templatePath)) {
                $return['template content'] = file_get_contents($templatePath);
                echo 'pageTag : '.$pageTag.';template : '.$templatePath."\n";
            }
        } elseif ($via === 'bazarliste') {
            $page = $this->pageManager->getOne($pageTag);
            if ($page && preg_match('/({{(bazarliste|bazarcarto|calendrier|map|gogomap)\s[^}]*}})/i', $page['body'], $matches)) {
                if (preg_match_all('/([a-zA-Z0-9_]*)=\"(.*)\"/U', $matches[1], $matchesLevel2)) {
                    $params = [];
                    $matches = [];
                    foreach ($matchesLevel2[0] as $id => $match) {
                        $params[$matchesLevel2[1][$id]] = $matchesLevel2[2][$id];
                    }
                    $ids = explode(',', $params['id'] ?? null);
                    if (!empty($ids)) {
                        $ids = array_map(function ($id) {
                            return trim($id);
                        }, $ids);
                        $ids = array_filter($ids, function ($id) {
                            return ((substr($id, 0, 4) != 'http') && (strval(intval($id)) == strval($id)));
                        });
                    }
                    if (!empty($ids)) {
                        $return['entries last-date'] = $this->getLastDateOfEntries($ids);
                        foreach ($ids as $id) {
                            $templatePath = $this->getTemplatePathFormFormId($id);
                            if (!empty($templatePath)) {
                                $return['template fiche-'.$id] = file_get_contents($templatePath);
                            }
                        }
                    }
                }
            }
        }
        return $return;
    }

    /**
     * rerieve fiche-X.tpl.html path and filename form formId
     * @param string $formId
     * @return string|null $path
     */
    private function getTemplatePathFormFormId(string $formId): ?string
    {
        $templateFileName = 'fiche-'.trim($formId);
        if ($this->templateEngine->hasTemplate('@bazar/'.$templateFileName.'.tpl.html')) {
            $templateFileName .= '.tpl.html';
        } elseif ($this->templateEngine->hasTemplate('@bazar/'.$templateFileName.'.twig')) {
            $templateFileName .= '.twig';
        } else {
            $templateFileName = '';
        }
        
        if (!empty($templateFileName)) {
            foreach (self::PATH_LIST as $path) {
                if (file_exists($path.$templateFileName)) {
                    return $path.$templateFileName;
                }
            }
        }
        return null;
    }

    /**
     * find date of last entry of the forms
     * @param array $formsIds
     * @return string $date
     */
    private function getLastDateOfEntries(array $formsIds): string
    {
        $EntriesRequest =
            'SELECT DISTINCT resource FROM ' . $this->dbService->prefixTable('triples') .
            'WHERE value = "fiche_bazar" AND property = "http://outils-reseaux.org/_vocabulary/type" ' .
            'ORDER BY resource ASC';
        $FormIdRequest = join(
            ' OR ',
            array_map(function ($id) {
                return 'body LIKE \'%"id_typeannonce":"'.trim($id).'"%\'';
            }, $formsIds)
        );

        if (empty($FormIdRequest)) {
            throw new \Exception("No form id request ! \$formsIds = ".json_encode($formsIds));
        }

        $SQLRequest =
        'SELECT DISTINCT time FROM ' . $this->dbService->prefixTable('pages') . ' '.
        'WHERE latest="Y" AND comment_on = \'\' ' .
        'AND ('.$FormIdRequest.') ' .
        'AND tag IN (' . $EntriesRequest . ') '.
        'ORDER BY time DESC '.
        'LIMIT 1';

        $results = $this->dbService->loadAll($SQLRequest);

        return !empty($results) ? $results[array_key_first($results)]['time'] : '';
    }
}
