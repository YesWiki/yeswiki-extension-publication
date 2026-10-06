<?php

namespace YesWiki\Test\Publication\Actions;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Performable\ActionRegistry;
use YesWiki\Kernel\Service\ExtensionRegistry;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Publication\Service\Publication;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The publication actions, run the way a page runs them, over content the test makes and removes. */
class PublicationActionsTest extends YesWikiTestCase
{
    private const PAGE = 'TestPublicationChapterOne';
    private const EBOOK = 'TestPublicationEbookRabelais';

    private string $formId = '';

    /** @var list<string> */
    private array $tags = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (!isset($this->getWiki()->services->get(ExtensionRegistry::class)->all()['publication'])) {
            $this->markTestSkipped('publication is not switched on in this wiki');
        }
        $this->getWiki()->services->get(AuthenticationService::class)->connectFirstAdmin();
    }

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        foreach ($this->tags as $tag) {
            $services->get(PageManager::class)->deleteOrphaned($tag);
        }
        if ($this->formId !== '') {
            $services->get(FormManager::class)->delete($this->formId);
        }
        $services->get(AuthenticationService::class)->logout();
        parent::tearDown();
    }

    public function testTheOldNameStillRunsTheRenamedAction(): void
    {
        $registry = $this->getWiki()->services->get(ActionRegistry::class);
        $this->assertTrue($registry->has('action', 'entries2publication'));
        $this->assertSame('entries2publication', $registry->resolve('action', 'bazar2publication')[0]);

        $html = $this->getWiki()->services->get(MarkdownFormatterService::class)->format('{{bazar2publication title="Imprimer ces livres" templatepage="NoSuchTemplatePageAnywhere"}}');

        $this->assertStringContainsString('entries2publication-action', $html);
        $this->assertStringContainsString('Imprimer ces livres', $html);
        $this->assertStringContainsString('/preview', $html);
        $this->assertStringContainsString('via=entrylist', $html);
        $this->assertStringContainsString('yw-alert--warning', $html, 'a template page that does not exist is said to be missing');
    }

    public function testTheGeneratorOffersPagesAndTheEntriesOfAForm(): void
    {
        $services = $this->getWiki()->services;
        $this->makePage(self::PAGE, "# Chapitre\n\nDu texte.");
        $this->makeForm();
        $entry = $services->get(EntryManager::class)->create($this->formId, ['bf_titre' => 'Gargantua de test', 'antispam' => 1], false);
        $this->tags[] = (string)$entry['tag'];

        $html = $services->get(ActionRunner::class)->action('publicationgenerator', [
            'groupselector' => "pages, {$this->formId}",
            'titles' => 'Les pages, Les livres',
        ]);

        $this->assertStringContainsString('Les pages', $html);
        $this->assertStringContainsString('Les livres', $html);
        $this->assertStringContainsString('value="' . self::PAGE . '"', $html);
        $this->assertStringContainsString('value="' . $entry['tag'] . '"', $html, 'an entry is picked by its tag');
        $this->assertStringContainsString('Gargantua de test', $html, 'and shown by its computed title');
        $this->assertStringContainsString('export-table-form', $html);
    }

    public function testANewsletterWithoutItsFormSaysSo(): void
    {
        $html = $this->getWiki()->services->get(ActionRunner::class)->action('publicationgenerator', ['outputformat' => 'newsletter']);

        $this->assertStringContainsString('yw-alert--danger', $html);
    }

    public function testTheListShowsThePagesHoldingAPublication(): void
    {
        $services = $this->getWiki()->services;
        $this->makePage(self::PAGE, 'Pas une publication.');
        $this->makePage(self::EBOOK, '{{include page="' . self::PAGE . '"}}');
        $services->get(PageManager::class)->setMetadata(self::EBOOK, $services->get(Publication::class)->storable([
            'publication' => ['title' => 'Rabelais choisi', 'description' => 'Une sélection', 'authors' => 'François Rabelais'],
            'publication-mode' => 'fanzine',
            'publication-fanzine' => ['layout' => 'recto-folio'],
            'page' => ['ignored'],
        ]));

        $html = $services->get(ActionRunner::class)->action('publicationlist', ['pagenameprefix' => 'TestPublication']);

        $this->assertStringContainsString('Rabelais choisi', $html);
        $this->assertStringContainsString('recto-folio.svg', $html);
        $this->assertStringContainsString(self::EBOOK . '/pdf', $html);
        $this->assertStringNotContainsString(self::PAGE . '/pdf', $html, 'a page holding no publication is not listed');
        $this->assertArrayNotHasKey('page', $services->get(PageManager::class)->getMetadata(self::EBOOK) ?? [], 'only publication options are kept in Metadata');
    }

    public function testThePrintOnlyActionsWriteWhatTheLayoutLooksFor(): void
    {
        $runner = $this->getWiki()->services->get(ActionRunner::class);

        $this->assertStringContainsString('class="blank-page"', $runner->action('blankpage'));
        $this->assertStringContainsString('class="pagebreak"', $runner->action('pagebreak'));
        $this->assertStringContainsString('publication-template-placeholder', $runner->action('publication-template'));
    }

    public function testListContribNamesTheContributorsOfTheIncludedEntries(): void
    {
        $services = $this->getWiki()->services;
        $this->makeForm();
        $tags = [];
        foreach (['jeanne DUPONT', 'Albert martin'] as $name) {
            $entry = $services->get(EntryManager::class)->create($this->formId, ['bf_titre' => $name, 'antispam' => 1], false);
            $this->tags[] = $tags[] = (string)$entry['tag'];
        }
        $this->makePage(self::EBOOK, implode("\n", array_map(static fn (string $tag): string => '{{include page="' . $tag . '"}}', $tags)));
        $previous = $services->get(PageContext::class)->getRequestedTag();
        $services->get(PageContext::class)->setRequestedTag(self::EBOOK);
        try {
            $html = $services->get(ActionRunner::class)->action('listcontrib', ['field' => 'bf_titre']);
        } finally {
            $services->get(PageContext::class)->setRequestedTag($previous);
        }

        $this->assertSame('<ol><li>Albert Martin</li><li>Jeanne Dupont</li></ol>', $html);
    }

    private function makePage(string $tag, string $content): void
    {
        $this->getWiki()->services->get(PageManager::class)->save($tag, [PageBody::CONTENT => $content], '', true);
        $this->tags[] = $tag;
    }

    private function makeForm(): void
    {
        $forms = $this->getWiki()->services->get(FormManager::class);
        $this->formId = (string)$forms->findNewId();
        $forms->create([
            'id' => $this->formId,
            'label' => 'Publication test form',
            'template' => [['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre']],
            'entry_title_template' => '{{bf_titre}}',
        ]);
    }
}
