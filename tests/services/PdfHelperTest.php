<?php

namespace YesWiki\Test\Publication\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Publication\Service\PdfHelper;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

/**
 * PdfHelper finds the entries and fiche templates a page publishes, on forms, entries and pages the test creates itself.
 */
class PdfHelperTest extends YesWikiTestCase
{
    private const TEMPLATE_FOLDER = 'custom/templates/bazar/';
    private const TEMPLATE_CONTENT = 'test';

    private static array $formIds = [];
    private static array $entryTags = [];
    private static array $pageTags = [];
    private static array $templateFiles = [];

    /**
     * Keeps every created form until the end, so no two cases share a form id the template loader already looked up.
     */
    public static function tearDownAfterClass(): void
    {
        $wiki = self::getWiki();
        $GLOBALS['wiki'] = $wiki;
        $aclService = $wiki->services->get(AclService::class);
        foreach (self::$entryTags as $tag) {
            $wiki->services->get(EntryManager::class)->delete($tag, true);
            $aclService->delete($tag);
        }
        foreach (self::$pageTags as $tag) {
            $wiki->services->get(PageManager::class)->deleteOrphaned($tag);
            $aclService->delete($tag);
        }
        foreach (self::$formIds as $formId) {
            $wiki->services->get(FormManager::class)->delete($formId);
        }
        foreach (self::$templateFiles as $file) {
            @unlink($file);
        }
        self::$formIds = self::$entryTags = self::$pageTags = self::$templateFiles = [];
    }

    /**
     * Creates a form holding one readable entry, with a fiche template when asked, and returns the form id.
     */
    private function createFormWithEntry(Wiki $wiki, bool $withTemplate): string
    {
        $GLOBALS['wiki'] = $wiki;
        $formId = $wiki->services->get(FormManager::class)->create([
            'bn_label_nature' => 'PdfHelperTest form',
            'bn_template' => 'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
            'bn_condition' => '',
        ]);
        self::$formIds[] = $formId;
        $entry = $wiki->services->get(EntryManager::class)->create($formId, ['bf_titre' => 'PdfHelperTest entry ' . $formId]);
        self::$entryTags[] = $entry['id_fiche'];
        $wiki->services->get(AclService::class)->save($entry['id_fiche'], 'read', '*');
        if ($withTemplate) {
            $this->createTemplate('fiche-' . $formId . '.tpl.html');
        }

        return $formId;
    }

    private function createTemplate(string $templateName): void
    {
        $file = self::TEMPLATE_FOLDER . $templateName;
        $this->assertFileDoesNotExist($file, 'the test would overwrite a template of this wiki');
        if (!is_dir(self::TEMPLATE_FOLDER)) {
            mkdir(self::TEMPLATE_FOLDER, 0777, true);
        }
        file_put_contents($file, self::TEMPLATE_CONTENT);
        self::$templateFiles[] = $file;
    }

    private function createPage(Wiki $wiki, string $body): string
    {
        $pageManager = $wiki->services->get(PageManager::class);
        do {
            $tag = 'PdfHelperTest' . bin2hex(random_bytes(4));
        } while (!empty($pageManager->getOne($tag)));
        $pageManager->save($tag, $body, '', true);
        $wiki->services->get(AclService::class)->save($tag, 'read', '*');
        self::$pageTags[] = $tag;

        return $tag;
    }

    /**
     * @covers PdfHelper::__construct
     */
    public function testPdfHelperExisting(): Wiki
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(PdfHelper::class));

        return $wiki;
    }

    #[Depends('testPdfHelperExisting')]
    #[DataProvider('dataProvider')]
    public function testGetPageEntriesContent(string $pageTagMode, ?string $via, array $forms, bool $withTemplate, array $expected, Wiki $wiki)
    {
        if ($pageTagMode === 'entry') {
            $formId = $this->createFormWithEntry($wiki, $withTemplate);
            $pageTag = end(self::$entryTags);
        } elseif ($pageTagMode === 'page' && $via === 'bazarliste') {
            $ids = [];
            foreach ($forms as $slot => $hasTemplate) {
                $ids[$slot] = $this->createFormWithEntry($wiki, $hasTemplate);
            }
            $pageTag = $this->createPage($wiki, "{{bazarliste id=\"" . implode(',', $ids) . "\"}}\n{{bazar2publication}}");
            $expected = array_combine(
                array_map(fn ($key) => preg_replace_callback('/\{(\w)\}/', fn ($m) => $ids[$m[1]], $key), array_keys($expected)),
                $expected
            );
        } elseif ($pageTagMode === 'page') {
            $pageTag = $this->createPage($wiki, 'A page without any entry list');
        } else {
            $pageTag = '\/aa';
        }

        $results = $wiki->services->get(PdfHelper::class)->getPageEntriesContent($pageTag, $via);

        if (isset($expected['entries last-date'])) {
            $this->assertIsString($results['entries last-date'] ?? null);
            $this->assertNotEmpty($results['entries last-date']);
            unset($expected['entries last-date'], $results['entries last-date']);
        }
        $this->assertSame($expected, $results);
    }

    public static function dataProvider()
    {
        $content = self::TEMPLATE_CONTENT;

        return array_map('array_values', [
            'page not entry' => [
                'mode' => 'page',
                'via' => null,
                'forms' => [],
                'withTemplate' => false,
                'expected' => [],
            ],
            'page not entry with via without template' => [
                'mode' => 'page',
                'via' => 'bazarliste',
                'forms' => ['a' => false],
                'withTemplate' => false,
                'expected' => ['entries last-date' => ''],
            ],
            'page not entry with via with template' => [
                'mode' => 'page',
                'via' => 'bazarliste',
                'forms' => ['a' => true],
                'withTemplate' => true,
                'expected' => ['entries last-date' => '', 'template fiche-{a}' => $content],
            ],
            'page not entry with via 2 ids with template' => [
                'mode' => 'page',
                'via' => 'bazarliste',
                'forms' => ['a' => true, 'b' => false],
                'withTemplate' => true,
                'expected' => ['entries last-date' => '', 'template fiche-{a}' => $content],
            ],
            'page not entry with via 2 ids with templates' => [
                'mode' => 'page',
                'via' => 'bazarliste',
                'forms' => ['a' => true, 'b' => true],
                'withTemplate' => true,
                'expected' => ['entries last-date' => '', 'template fiche-{a}' => $content, 'template fiche-{b}' => $content],
            ],
            'not existing page' => [
                'mode' => 'no page',
                'via' => null,
                'forms' => [],
                'withTemplate' => false,
                'expected' => [],
            ],
            'not existing page with via' => [
                'mode' => 'no page',
                'via' => 'bazarliste',
                'forms' => [],
                'withTemplate' => false,
                'expected' => [],
            ],
            'entry without template' => [
                'mode' => 'entry',
                'via' => null,
                'forms' => [],
                'withTemplate' => false,
                'expected' => [],
            ],
            'entry with via without template' => [
                'mode' => 'entry',
                'via' => 'bazarliste',
                'forms' => [],
                'withTemplate' => false,
                'expected' => [],
            ],
            'entry with template' => [
                'mode' => 'entry',
                'via' => null,
                'forms' => [],
                'withTemplate' => true,
                'expected' => ['template content' => $content],
            ],
            'entry with via with template' => [
                'mode' => 'entry',
                'via' => 'bazarliste',
                'forms' => [],
                'withTemplate' => true,
                'expected' => ['template content' => $content],
            ],
        ]);
    }

    #[Depends('testPdfHelperExisting')]
    #[DataProvider('dataProviderGetFullFileName')]
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

    public static function dataProviderGetFullFileName()
    {
        return array_map('array_values', [
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
        ]);
    }

    protected function setRootPage(Wiki $wiki): array
    {
        $previousPageTag = $wiki->tag;
        $previousPageContent = $wiki->page;
        $rootPageTag = $this->getParam($wiki, 'root_page');
        $this->setPage($wiki, [
            'tag' => $rootPageTag,
            'content' => $this->getService($wiki, PageManager::class)->getOne($rootPageTag)
        ]);
        return [
            'tag' => $previousPageTag,
            'content' => $previousPageContent
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
