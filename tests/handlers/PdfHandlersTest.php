<?php

namespace YesWiki\Test\Publication\Handlers;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Service\ExtensionRegistry;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RequestScope;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Publication\Service\PdfHelper;
use YesWiki\Publication\Service\Publication;
use YesWiki\Publication\Service\SessionManager;
use YesWiki\Render\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The preview a PDF is printed from, the PDF page, and the headless browser printing it. */
class PdfHandlersTest extends YesWikiTestCase
{
    private const CHAPTER = 'TestPublicationPrintChapter';
    private const EBOOK = 'TestPublicationPrintEbook';
    private const CHROMIUM = '/run/current-system/sw/bin/chromium';

    protected function setUp(): void
    {
        parent::setUp();
        $services = $this->getWiki()->services;
        if (!isset($services->get(ExtensionRegistry::class)->all()['publication'])) {
            $this->markTestSkipped('publication is not switched on in this wiki');
        }
        $services->get(AuthenticationService::class)->connectFirstAdmin();
        $pages = $services->get(PageManager::class);
        $pages->save(self::CHAPTER, [PageBody::CONTENT => "# Chapitre imprimé\n\nUn paragraphe."], '', true);
        $pages->save(self::EBOOK, [PageBody::CONTENT => '{{include page="' . self::CHAPTER . '"}}' . "\n{{blankpage}}\n"], '', true);
        $pages->setMetadata(self::EBOOK, $services->get(Publication::class)->storable([
            'publication' => ['title' => 'Livre imprimé', 'description' => '', 'authors' => 'Moi'],
            'publication-cover-page' => '1',
            'publication-book' => ['page-format' => 'A5', 'page-orientation' => 'landscape'],
        ]));
        $services->get(AuthenticationService::class)->logout();
    }

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        foreach ([self::EBOOK, self::CHAPTER] as $tag) {
            $services->get(PageManager::class)->deleteOrphaned($tag);
        }
        $storage = $services->get(Storage::class);
        foreach ($storage->glob(PdfHelper::CACHE_DIR . 'TestPublication*') as $path) {
            $storage->delete($path);
        }
        parent::tearDown();
    }

    public function testThePreviewLaysTheEbookOutWithItsOptions(): void
    {
        $html = $this->runHandler('preview', self::EBOOK);

        $this->assertStringContainsString('<!doctype html>', strtolower($html));
        $this->assertMatchesRegularExpression('/<body class="[^"]*publication--book[^"]*page-format--A5[^"]*page-orientation--landscape/', $html);
        $this->assertStringContainsString('data-publication="awaiting-layout"', $html);
        $this->assertStringContainsString('Livre imprimé', $html, 'the cover is drawn');
        $this->assertStringContainsString('Chapitre imprimé', $html, 'the included page is there');
        $this->assertStringContainsString('class="blank-page"', $html);
        $this->assertStringContainsString('extensions/publication/javascripts/browser/print.js', $html);
        $this->assertStringContainsString('extensions/publication/javascripts/browser/is-pdf-ready.js', $html);
        $this->assertStringContainsString('size: A5 landscape', $html);
    }

    public function testAPageThatIsNoPublicationIsPrintedOnItsOwn(): void
    {
        $html = $this->runHandler('preview', self::CHAPTER);

        $this->assertStringContainsString('publication--page', $html);
        $this->assertStringNotContainsString('publication-cover', $html);
    }

    public function testThePdfPageAsksTheLocalServiceForThePreview(): void
    {
        $pdfHelper = $this->getWiki()->services->get(PdfHelper::class);
        if (!$pdfHelper->canExecChromium()) {
            $this->markTestSkipped('no Chromium here: the PDF page would send the reader to the browser\'s printing');
        }

        $html = $this->runHandler('pdf', self::CHAPTER);

        $this->assertStringContainsString('pdf-handler-container', $html);
        $this->assertStringContainsString('api\/pdf\/getPdf', $html);
        $this->assertStringContainsString(self::CHAPTER . '/preview', $html);
    }

    public function testThePdfIsNamedAfterThePageAndCachedUnderCache(): void
    {
        $this->setPage(self::EBOOK);
        $file = $this->getWiki()->services->get(PdfHelper::class)->getFullFileName([], []);

        $this->assertSame(self::EBOOK, $file['pageTag']);
        $this->assertStringContainsString(self::EBOOK . '/preview', $file['sourceUrl']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{10}$/', $file['hash']);
        $this->assertSame(PdfHelper::CACHE_DIR . self::EBOOK . '-publication-' . $file['hash'] . '.pdf', $file['cachePath']);
        $this->assertSame('public', $this->getWiki()->services->get(Storage::class)->tierOf($file['cachePath']));
    }

    public function testChromiumPrintsThePreviewAsAPdf(): void
    {
        if (!is_executable(self::CHROMIUM)) {
            $this->markTestSkipped('no Chromium at ' . self::CHROMIUM);
        }
        $services = $this->getWiki()->services;
        $sourceUrl = $services->get(UrlFormatter::class)->href('preview', self::EBOOK, null, false);
        $headers = @get_headers($sourceUrl, false, stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false], 'http' => ['proxy' => '']]));
        if ($headers === false) {
            $this->markTestSkipped("the wiki is not served at {$sourceUrl}, so no browser can load it");
        }
        $params = $services->get(ParameterBagInterface::class);
        $printing = new PdfHelper(
            $services->get(EntryManager::class),
            $services->get(PageManager::class),
            $services->get(PageContext::class),
            $services->get(UrlFormatter::class),
            $services->get(AuthenticationService::class),
            new ParameterBag([
                'base_url' => $params->get('base_url'),
                'htmltopdf_path' => self::CHROMIUM,
                'page_load_timeout' => 30000,
                'htmltopdf_options' => [
                    'noSandbox' => true,
                    'headless' => true,
                    'startupTimeout' => 30,
                    'customFlags' => ['--ignore-certificate-errors', '--no-proxy-server'],
                ],
            ]),
            $services->get(SessionManager::class),
            $services->get(Storage::class)
        );

        $pdf = $printing->printToPdf($sourceUrl);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThanOrEqual(3, preg_match_all('#/Type\s*/Page[^s]#', $pdf), 'a cover, the blank page after it and the chapter');
    }

    private function setPage(string $tag): void
    {
        $services = $this->getWiki()->services;
        $page = $services->get(PageManager::class)->getOne($tag);
        $services->get(PageContext::class)->setTag($tag);
        $services->get(PageContext::class)->assignPage($page);
        $services->get(PageContext::class)->setMetadata(is_array($page['metadatas'] ?? null) ? $page['metadatas'] : []);
    }

    private function runHandler(string $handler, string $tag): string
    {
        $services = $this->getWiki()->services;
        $services->get(RequestScope::class)->startNewRequest();
        $this->setPage($tag);

        return (string)$services->get(Performer::class)->run($handler, 'handler', []);
    }
}
