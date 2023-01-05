<?php

namespace YesWiki\Test\Publication\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Publication\Service\PdfHelper;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

// TODO update tests with new pdfHelper

class PdfHelperTest extends YesWikiTestCase
{
    /**
     * @covers PdfHelper::__construct
     * @return Wiki
     */
    public function testPdfHelperExisting(): Wiki
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(PdfHelper::class));
        return $wiki;
    }

    /**
     * @depends testPdfHelperExisting
     * @covers PdfHelper::getPageEntriesContent
     * @dataProvider dataProvider
     * @param string $pageTagMode
     * @param string|null $via
     * @param array $bazarlisteIds
     * @param bool $withTemplate
     * @param mixed $expected
     * @param bool $clean
     * @param Wiki $wiki
     */
    public function testGetPageEntriesContent(string $pageTagMode, ?string $via, array $bazarlisteIds, bool $withTemplate, bool $clean, $expected, Wiki $wiki)
    {
        if ($pageTagMode === 'entry') {
            $pageTag = $this->getEntryPageName($withTemplate);
            if ($withTemplate) {
                if (!empty($pageTag)) {
                    list($templateName, $templateContent) = $this->getCustomTemplate($pageTag, false);
                }
                // do not use temporary template because it will not been registered by TemplateEngine
                // if (empty($pageTag) || ($templateContent === '{{template not found}}')) {
                //     // create template for next tests
                //     $pageTag = $this->getEntryPageName(false);
                //     list($templateName, $templateContent) = $this->getCustomTemplate($pageTag, true);
                // }
                if ($templateContent === 'test') {
                    $templatesNameToDelete[] = $templateName;
                }
                if ($templateContent == '{{template not found}}' && isset($expected["template content"])) {
                    unset($expected["template content"]);
                } else {
                    $expected["template content"] = $templateContent;
                }
            }
        } elseif ($pageTagMode === 'page') {
            if ($via === 'bazarliste') {
                // $pageTag = $this->getPageTagWithBazar2Publication($bazarlisteIds);
                // if (empty($pageTage)) {
                $pageTag = $this->createPageTagWithBazar2Publication($bazarlisteIds);
                $pageToDelete = $pageTag;
                $bazarlisteIdsCopy = $bazarlisteIds;
                $bazarlisteIds = [];
                foreach ($bazarlisteIdsCopy as $bazarlisteId) {
                    if (isset($expected['template fiche-'.$bazarlisteId])) {
                        $templateName = 'fiche-'.$bazarlisteId.'.tpl.html';
                        $templateContent = $this->createCustomTemplate($templateName);
                        $expected['template fiche-'.$bazarlisteId] = $templateContent;
                        $bazarlisteIds[] = $bazarlisteId;
                        $templatesNameToDelete[] = $templateName;
                    }
                }
            // }
            } else {
                $pageTag = $this->getPageTagWithoutBazar2Publication();
            }
        } else {
            // not existing page
            $pageTag = '\/aa';
        }
        $pdfHelper = $wiki->services->get(PdfHelper::class);
        try {
            $results = $pdfHelper->getPageEntriesContent($pageTag, $via);
        } finally {
            if (!empty($pageToDelete)) {
                $this->deletePage($pageToDelete);
            }
            if ($clean && !empty($templatesNameToDelete)) {
                foreach ($templatesNameToDelete as $templateNameToDelete) {
                    $this->deleteCustomEmptyTemplate($templateNameToDelete);
                }
            }
        }
        if (!empty($expected['entries last-date'])) {
            $this->assertArrayHasKey('entries last-date', $results);
            $this->assertTrue(!empty($results['entries last-date']));
            $this->assertIsString($results['entries last-date']);
            foreach ($bazarlisteIds as $bazarlisteId) {
                $this->assertArrayHasKey('template fiche-'.$bazarlisteId, $results);
                $this->assertSame($expected['template fiche-'.$bazarlisteId], $results['template fiche-'.$bazarlisteId]);
            }
        } else {
            $this->assertSame($expected, $results);
        }
    }

    public function dataProvider()
    {
        // pageTagMode ,via, bazarlisteIds, withTemplate, clean,expected
        return [
            'page not entry' => [
                'mode' => 'page',
                'via' => null,
                'bazarlisteIds' => [],
                'withTemplate' => false,
                'clean' => false,
                'expected' => []
            ],
            'page not entry with via without template' => [
                'mode' => 'page',
                'via' => 'bazarliste',
                'bazarlisteIds' => ['3'],
                'withTemplate' => false,
                'clean' => false,
                'expected' => ['entries last-date' => '{{date}}']
            ],
            'page not entry with via with template' => [
                'mode' => 'page',
                'via' => 'bazarliste',
                'bazarlisteIds' => ['1'],
                'withTemplate' => true,
                'clean' => true,
                'expected' => ['entries last-date' => '{{date}}','template fiche-1' => '{{content}}']
            ],
            'page not entry with via 2 ids with template' => [
                'mode' => 'page',
                'via' => 'bazarliste',
                'bazarlisteIds' => ['1','3'],
                'withTemplate' => true,
                'clean' => true,
                'expected' => ['entries last-date' => '{{date}}','template fiche-1' => '{{content}}']
            ],
            'page not entry with via 2 ids with templates' => [
                'mode' => 'page',
                'via' => 'bazarliste',
                'bazarlisteIds' => ['1','4'],
                'withTemplate' => true,
                'clean' => true,
                'expected' => ['entries last-date' => '{{date}}','template fiche-1' => '{{content}}','template fiche-4' => '{{content}}']
            ],
            'not existing page' => [
                'mode' => 'no page',
                'via' => null,
                'bazarlisteIds' => [],
                'withTemplate' => false,
                'clean' => false,
                'expected' => []
            ],
            'not existing page with via' => [
                'mode' => 'no page',
                'via' => 'bazarliste',
                'bazarlisteIds' => [],
                'withTemplate' => false,
                'clean' => false,
                'expected' => []
            ],
            'entry without template' => [
                'mode' => 'entry',
                'via' => null,
                'bazarlisteIds' => [],
                'withTemplate' => false,
                'clean' => false,
                'expected' => []
            ],
            'entry with via without template' => [
                'mode' => 'entry',
                'via' => 'bazarliste',
                'bazarlisteIds' => [],
                'withTemplate' => false,
                'clean' => false,
                'expected' => []
            ],
            'entry with template' => [
                'mode' => 'entry',
                'via' => null,
                'bazarlisteIds' => [],
                'withTemplate' => true,
                'clean' => true,
                'expected' => ["template content"=>"{{content}}"]
            ],
            'entry with via with template' => [
                'mode' => 'entry',
                'via' => 'bazarliste',
                'bazarlisteIds' => [],
                'withTemplate' => true,
                'clean' => true,
                'expected' => ["template content"=>"{{content}}"]
            ]
        ];
    }

    /**
     * @param bool $withTemplate
     * @return string
     */
    protected function getEntryPageName(bool $withTemplate): string
    {
        $wiki = $this->getWiki();
        $entryManager = $wiki->services->get(EntryManager::class);
        $templateEngine = $wiki->services->get(TemplateEngine::class);
        $GLOBALS['wiki'] = $wiki; // for bazar.fonct.php:82
        $entries = $entryManager->search([]);
        foreach ($entries as $tag => $entry) {
            $formId = $entry['id_typeannonce'];
            if (strval($formId) == strval(intval($formId))) {
                $templateName = '@bazar/fiche-'.trim($formId).'.tpl.html';
                $templateName2 = '@bazar/fiche-'.trim($formId).'.twig';
                if ($withTemplate == ($templateEngine->hasTemplate($templateName) || $templateEngine->hasTemplate($templateName2))) {
                    return $tag;
                }
            }
        }
        return '';
    }
    /**
     * @eturn null|string
     */
    private function getPageTagWithBazar2Publication(): ?string
    {
        $wiki = $this->getWiki();
        $dbService = $wiki->services->get(DbService::class);
        $sqlRequest = 'SELECT tag FROM ' . $dbService->prefixTable('pages') . ' '.
            'WHERE latest = \'Y\' AND comment_on=\'\' AND '.
            'body LIKE \'%{{bazarliste%\' AND '.
            'body LIKE \'%{{bazar2publication}}%\' AND '.
            'tag NOT IN (SELECT DISTINCT resource FROM ' . $dbService->prefixTable('triples') . ' '.'
                WHERE value = "fiche_bazar" AND '.
                'property = "http://outils-reseaux.org/_vocabulary/type"'.
            ') LIMIT 1';
        $pages = $dbService->loadAll($sqlRequest);
        return empty($pages) ? null : $pages[array_key_first($pages)]['tag'] ;
    }

    /**
     * @eturn null|string
     */
    private function getPageTagWithoutBazar2Publication(): ?string
    {
        $wiki = $this->getWiki();
        $dbService = $wiki->services->get(DbService::class);
        $sqlRequest = 'SELECT tag FROM ' . $dbService->prefixTable('pages') . ' '.
            'WHERE latest = \'Y\' AND comment_on=\'\' AND '.
            'body NOT LIKE \'%{{bazarliste%\' AND '.
            'body NOT LIKE \'%{{bazar2publication}}%\' AND '.
            'tag NOT IN (SELECT DISTINCT resource FROM ' . $dbService->prefixTable('triples') . ' '.'
                WHERE value = "fiche_bazar" AND '.
                'property = "http://outils-reseaux.org/_vocabulary/type"'.
            ') LIMIT 1';
        $pages = $dbService->loadAll($sqlRequest);
        return empty($pages) ? null : $pages[array_key_first($pages)]['tag'] ;
    }

    /**
     * @param array $bazarlisteIds
     * @return string $pageTag
     */
    private function createPageTagWithBazar2Publication(array $bazarlisteIds): string
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $ids = implode(',', $bazarlisteIds);
        $pageContent = "{{bazarliste id=\"".$ids."\"}}\n{{bazar2publication}}";
        $pageContent = _convert($pageContent, YW_CHARSET, true);
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersUpperCase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        do {
            $pageTag = '';

            $index = rand(0, strlen($charactersUpperCase) - 1);
            $pageTag .= $charactersUpperCase[$index];

            for ($i = 0; $i < 8; $i++) {
                $index = rand(0, strlen($characters) - 1);
                $pageTag .= $characters[$index];
            }

            $index = rand(0, strlen($charactersUpperCase) - 1);
            $pageTag .= $charactersUpperCase[$index];
        } while (!empty($pageManager->getOne($pageTag)));
        $pageManager->save($pageTag, $pageContent, "", true);
        return $pageTag;
    }
    /**
     * @param string $pageTag
     */
    private function deletePage(string $pageTag)
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $pageManager->deleteOrphaned($pageTag);
    }

    /**
     * @param string $pageTag
     * @param bool $createTemplate
     * @return array [string $templateName, string $templateContent]
     */
    protected function getCustomTemplate(string $pageTag, bool $createTemplate = false): array
    {
        $wiki = $this->getWiki();
        $entryManager = $wiki->services->get(EntryManager::class);
        $entry = $entryManager->getOne($pageTag);
        $formId = trim($entry['id_typeannonce']);
        $templateName = 'fiche-'.$formId.'.tpl.html';
        if ($createTemplate) {
            $templateContent = $this->createCustomTemplate($templateName);
        } else {
            $paths = [
                    'custom/templates/bazar/',
                    'custom/templates/bazar/templates/',
                    'themes/tools/bazar/templates/',
                    'themes/tools/bazar/presentation/templates/',
                    'tools/bazar/templates/',
                    'tools/bazar/presentation/templates/',
                ];
            $templateContent = '{{template not found}}' ; // default if template not found
            foreach ($paths as $path) {
                if (file_exists($path.$templateName)) {
                    $templateContent = file_get_contents($path.$templateName);
                    return [$templateName,$templateContent];
                }
            }
        }
        return [$templateName,$templateContent];
    }
    private function createCustomTemplate(string $templateName): string
    {
        if (!file_exists('custom/templates/bazar')) {
            mkdir('custom/templates/bazar', 0777, true);
        }
        $templateContent = 'test';
        file_put_contents('custom/templates/bazar/'.$templateName, $templateContent);
        return $templateContent;
    }

    /**
     * @param string $templateName
     */
    protected function deleteCustomEmptyTemplate(string $templateName)
    {
        if (file_exists('custom/templates/bazar/'.$templateName)) {
            unlink('custom/templates/bazar/'.$templateName);
        }
    }

    /**
     * @depends testPdfHelperExisting
     * @covers PdfHelper::getFullFileName
     * @dataProvider dataProviderGetFullFileName
     * @param array $get
     * @param array $server
     * @param array $expected
     * @param Wiki $wiki
     */
    public function testGetFullFileName(array $get, array $server, array $expected, Wiki $wiki)
    {
        $previousPage = $this->setRootPage($wiki);
        $server['QUERY_STRING'] = str_replace(
            '{{rootPageTag}}',
            $wiki->tag,
            $server['QUERY_STRING']
        );
        $results = $this->getService($wiki, PdfHelper::class)->getFullFileName($get, $server);
        $hash = $results['hash'] ?? 'unset-hash';
        $expected = array_map(function ($value) use ($wiki, $hash) {
            return str_replace(
                ['{{rootPageTag}}','{{hash}}'],
                [$wiki->tag,$hash],
                $value
            );
        }, $expected);
        $this->setPage($wiki, $previousPage);
        foreach ($expected as $key => $value) {
            $this->assertArrayHasKey($key, $results);
            if ($value == 'not empty') {
                $this->assertNotEmpty($results[$key]);
            } elseif (substr($value, 0, 7) == 'regexp:') {
                $this->assertMatchesRegularExpression(substr($value, 7), $results[$key], "not waited value in 'results' for key $key, $value");
            } else {
                $this->assertSame($value, $results[$key], "not same value in 'results' for key $key, expected $value");
            }
        }
    }

    public function dataProviderGetFullFileName()
    {
        return [
            'first test' => [
                'get' => [],
                'server' => [
                    'QUERY_STRING' => '{{rootPageTag}}'
                ],
                'expected' => [
                    'pageTag' => '{{rootPageTag}}',
                    'dlFilename' => 'regexp:/^{{rootPageTag}}-{{hash}}\.pdf$/',
                    'fullFilename' => 'regexp:/.+\/yeswiki-[A-Za-z0-9\-]+\/{{rootPageTag}}-publication-{{hash}}\.pdf$/',
                    'hash' => 'regexp:/^[A-Fa-f0-9]{10,}$/',
                    'sourceUrl' => 'regexp:/^https?:\/\/.+\/\??{{rootPageTag}}\/preview.*$/'
                ],
            ],
            'test with url' => [
                'get' => [
                    'url' => 'http://localhost/?TesT/preview'
                ],
                'server' => [
                    'QUERY_STRING' => '{{rootPageTag}}&url=http%3A%2F%2Flocalhost%2F%3FTesT%2Fpreview'
                ],
                'expected' => [
                    'pageTag' => 'publication',
                    'dlFilename' => 'regexp:/^publication-{{hash}}\.pdf$/',
                    'fullFilename' => 'regexp:/.+\/yeswiki-[A-Za-z0-9\-]+\/publication-publication-{{hash}}\.pdf$/',
                    'hash' => 'regexp:/^[A-Fa-f0-9]{10,}$/',
                    'sourceUrl' => 'regexp:/^http:\/\/localhost\/\?TesT\/preview$/'
                ],
            ],
            'test with url and urlPageTag' => [
                'get' => [
                    'url' => 'http://localhost/?TesT/preview',
                    'urlPageTag' => 'TesT'
                ],
                'server' => [
                    'QUERY_STRING' => '{{rootPageTag}}&url=http%3A%2F%2Flocalhost%2F%3FTesT%2Fpreview&urlPageTag=TesT'
                ],
                'expected' => [
                    'pageTag' => 'TesT',
                    'dlFilename' => 'regexp:/^TesT-{{hash}}\.pdf$/',
                    'fullFilename' => 'regexp:/.+\/yeswiki-[A-Za-z0-9\-]+\/TesT-publication-{{hash}}\.pdf$/',
                    'hash' => 'regexp:/^[A-Fa-f0-9]{10,}$/',
                    'sourceUrl' => 'regexp:/^http:\/\/localhost\/\?TesT\/preview$/'
                ],
            ],
        ];
    }

    protected function setRootPage(Wiki $wiki): array
    {
        $previousPageTag = $wiki->tag;
        $previousPageContent = $wiki->page;
        $rootPageTag = $this->getParam($wiki, 'root_page');
        $this->setPage($wiki, [
            'tag'=>$rootPageTag,
            'content' => $this->getService($wiki, PageManager::class)->getOne($rootPageTag)
        ]);
        return [
            'tag'=>$previousPageTag,
            'content'=>$previousPageContent
        ];
    }

    protected function setPage(Wiki $wiki, array $pageInfo)
    {
        $wiki->tag = $pageInfo['tag'];
        $wiki->page = $pageInfo['content'];
    }

    protected function getService(Wiki $wiki, string $className)
    {
        return $wiki->services->get($className);
    }

    protected function getParam(Wiki $wiki, string $name): ?string
    {
        $params = $this->getService($wiki, ParameterBagInterface::class);
        return $params->get($name);
    }
}
