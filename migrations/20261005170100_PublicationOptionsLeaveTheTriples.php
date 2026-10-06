<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;

/** Doryphore kept an ebook's options in the metadata triple, which core's own migration does not carry: they join the page's Metadata. */
class PublicationOptionsLeaveTheTriples extends YesWikiMigration
{
    private const PROPERTY = 'http://outils-reseaux.org/_vocabulary/metadata';

    public function run()
    {
        $triples = trim($this->dbService->prefixTable('triples'));
        if (!in_array($triples, $this->dbService->schema()->getTables(), true)) {
            return;
        }
        $pages = $this->dbService->prefixTable('pages');
        $carried = [];
        foreach ($this->dbService->loadAll("SELECT resource, value FROM {$triples} WHERE property = ? ORDER BY id", [self::PROPERTY]) as $row) {
            $values = json_decode((string)$row['value'], true);
            $options = is_array($values) ? array_filter($values, static fn ($key): bool => str_starts_with((string)$key, 'publication'), ARRAY_FILTER_USE_KEY) : [];
            if ($options === []) {
                continue;
            }
            $tag = (string)$row['resource'];
            $latest = $this->dbService->loadSingle("SELECT id, metadata FROM {$pages} WHERE tag = ? AND latest = 'Y' LIMIT 1", [$tag]);
            if ($latest === null) {
                continue;
            }
            $existing = json_decode((string)($latest['metadata'] ?? ''), true);
            $existing = is_array($existing) ? $existing : [];
            $added = array_diff_key($options, $existing);
            if ($added === []) {
                continue;
            }
            $this->dbService->query(
                "UPDATE {$pages} SET metadata = ? WHERE id = ?",
                [(string)json_encode($existing + $added, PageBody::JSON_FLAGS), (string)$latest['id']]
            );
            $this->getService(PageManager::class)->forget($tag);
            $carried[] = $tag;
        }

        if ($carried !== []) {
            $this->say('The publication options Doryphore kept in the metadata triple were added to the Metadata of ' . count($carried) . ' page(s): ' . implode(', ', $carried));
        }
    }
}
