<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/** `{{bazar2publication}}` is written `{{entries2publication}}` in the current revision of every page; the old name keeps working as an alias. */
class Bazar2publicationBecomesEntries2publication extends YesWikiMigration
{
    private const CALL = '/\{\{(\s*)bazar2publication\b/i';

    public function run()
    {
        $db = $this->getService(DbService::class);
        $pages = $db->prefixTable('pages');
        $rows = $db->loadAll(
            "SELECT id, tag, body FROM {$pages} WHERE latest = 'Y' AND " . $db->jsonAsText('body') . ' LIKE ?' . SqlParameters::LIKE_CLAUSE_SUFFIX,
            [SqlParameters::likeContains('bazar2publication')]
        );

        $rewritten = [];
        foreach ($rows as $row) {
            $body = PageBody::decode((string)$row['body']);
            $content = $body[PageBody::CONTENT] ?? null;
            if (!is_string($content)) {
                continue;
            }
            $renamed = (string)preg_replace(self::CALL, '{{$1entries2publication', $content);
            if ($renamed === $content) {
                continue;
            }
            $body[PageBody::CONTENT] = $renamed;
            $db->query("UPDATE {$pages} SET body = ? WHERE id = ?", [PageBody::encode($body), (string)$row['id']]);
            $this->getService(PageManager::class)->forget((string)$row['tag']);
            $rewritten[] = (string)$row['tag'];
        }

        if ($rewritten !== []) {
            $this->getService(SearchIndexer::class)->enqueue($rewritten);
            $this->say('{{bazar2publication}} is now written {{entries2publication}} in ' . count($rewritten) . ' page(s): ' . implode(', ', $rewritten));
        }
    }
}
