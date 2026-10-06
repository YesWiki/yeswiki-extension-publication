<?php

namespace YesWiki\Test\Publication\Migrations;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\ExtensionRegistry;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The rename of `{{bazar2publication}}`, and the carrying of Doryphore's publication options into Metadata. */
class Bazar2publicationBecomesEntries2publicationTest extends YesWikiTestCase
{
    private const TAG = 'TestPublicationRenameMigration';

    protected function setUp(): void
    {
        parent::setUp();
        $registry = $this->getWiki()->services->get(ExtensionRegistry::class);
        if (!isset($registry->all()['publication'])) {
            $this->markTestSkipped('publication is not switched on in this wiki');
        }
        require_once $registry->all()['publication'] . 'migrations/20261005170000_Bazar2publicationBecomesEntries2publication.php';
        require_once $registry->all()['publication'] . 'migrations/20261005170100_PublicationOptionsLeaveTheTriples.php';
    }

    protected function tearDown(): void
    {
        $db = $this->getWiki()->services->get(DbService::class);
        $db->query("DELETE FROM {$db->prefixTable('pages')} WHERE tag = ?", [self::TAG]);
        $db->query("DELETE FROM {$db->prefixTable('triples')} WHERE resource = ?", [self::TAG]);
        parent::tearDown();
    }

    public function testTheLatestRevisionIsRewrittenOnceAndOlderOnesAreLeftAlone(): void
    {
        $db = $this->getWiki()->services->get(DbService::class);
        $old = '{{bazar2publication title="old"}}';
        $latest = "Avant\n{{bazar2publication title=\"Imprimer\" templatepage=\"Modele\"}}\n{{ bazar2publication}}\n{{entrylist id=\"1\"}}";
        $this->insert($db, $old, 'N');
        $this->insert($db, $latest, 'Y');

        $this->runMigration(new \Bazar2publicationBecomesEntries2publication());
        $contents = $this->contents($db);

        $this->assertSame($old, $contents['N'], 'only the current revision is rewritten');
        $this->assertSame(
            "Avant\n{{entries2publication title=\"Imprimer\" templatepage=\"Modele\"}}\n{{ entries2publication}}\n{{entrylist id=\"1\"}}",
            $contents['Y']
        );

        $before = $this->contents($db);
        $this->runMigration(new \Bazar2publicationBecomesEntries2publication());
        $this->assertSame($before, $this->contents($db), 'a second run changes nothing');
    }

    public function testPublicationOptionsKeptInTheMetadataTripleJoinTheMetadata(): void
    {
        $db = $this->getWiki()->services->get(DbService::class);
        $this->insert($db, '{{include page="Un"}}', 'Y', '{"acls":{"read":"*"}}');
        $db->query(
            "INSERT INTO {$db->prefixTable('triples')} (resource, property, value) VALUES (?, ?, ?)",
            [self::TAG, 'http://outils-reseaux.org/_vocabulary/metadata', '{"publication-title":"Vieux livre","publication-mode":"book","theme":"margot"}']
        );

        $this->runMigration(new \PublicationOptionsLeaveTheTriples());
        $row = $db->loadSingle("SELECT metadata FROM {$db->prefixTable('pages')} WHERE tag = ? AND latest = 'Y'", [self::TAG]);
        $metadata = json_decode((string)($row['metadata'] ?? ''), true);

        $this->assertSame(['read' => '*'], $metadata['acls']);
        $this->assertSame('Vieux livre', $metadata['publication-title']);
        $this->assertSame('book', $metadata['publication-mode']);
        $this->assertArrayNotHasKey('theme', $metadata, 'what is not a publication option is core\'s migration\'s business');
    }

    private function runMigration(\YesWiki\Core\YesWikiMigration $migration): void
    {
        $services = $this->getWiki()->services;
        $migration->setServices($services);
        $migration->setDbService($services->get(DbService::class));
        $migration->setParams($services->get(ParameterBagInterface::class));
        $migration->run();
    }

    private function insert(DbService $db, string $content, string $latest, ?string $metadata = null): void
    {
        $db->query(
            "INSERT INTO {$db->prefixTable('pages')} (tag, {$db->quoteIdentifier('time')}, body, owner, {$db->quoteIdentifier('user')}, latest, type, parent, metadata)"
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [self::TAG, $latest === 'Y' ? '2026-01-02 00:00:00' : '2026-01-01 00:00:00', PageBody::encode([PageBody::CONTENT => $content]), '', '', $latest, 'page', '', $metadata]
        );
    }

    /**
     * @return array<string, string> latest flag => content
     */
    private function contents(DbService $db): array
    {
        $contents = [];
        foreach ($db->loadAll("SELECT latest, body FROM {$db->prefixTable('pages')} WHERE tag = ?", [self::TAG]) as $row) {
            $contents[(string)$row['latest']] = PageBody::content(PageBody::decode((string)$row['body']));
        }

        return $contents;
    }
}
