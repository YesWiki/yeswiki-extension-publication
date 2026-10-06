<?php

namespace YesWiki\Publication\Action;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\InclusionStack;
use YesWiki\Kernel\Service\PageContext;

/** `{{listcontrib field="bf_nom"}}`: the names held by that field in every page a publication includes, in alphabetical order. */
class ListContribAction extends YesWikiAction implements RegisteredAction
{
    private const INCLUSION = '/\{\{include\s*page="(.+)".*\}\}/mU';

    public static function performableName(): string
    {
        return 'listcontrib';
    }

    public function formatArguments($arg): array
    {
        return [
            'field' => (empty($arg['field']) || !is_string($arg['field'])) ? 'bf_nom' : $arg['field'],
        ];
    }

    public function run(): string
    {
        $pageManager = $this->getService(PageManager::class);
        $mainTag = $this->getService(PageContext::class)->getRequestedTag();
        if ($mainTag === '') {
            $inclusions = $this->getService(InclusionStack::class)->getAll();
            $mainTag = (string)end($inclusions);
        }
        $mainPage = $mainTag === '' ? null : $pageManager->getOne($mainTag);
        if ($mainPage === null || !preg_match_all(self::INCLUSION, PageBody::content($mainPage['body'] ?? []), $matches)) {
            return '';
        }

        $contributors = [];
        foreach ($matches[1] as $tag) {
            $body = $pageManager->getOne($tag)['body'] ?? [];
            $value = is_array($body) ? ($body[$this->arguments['field']] ?? '') : '';
            if (is_string($value) && trim($value) !== '') {
                $name = str_replace(' Et ', ' et ', ucwords(mb_strtolower(trim($value))));
                $contributors[$name] = $name;
            }
        }
        if ($contributors === []) {
            return '';
        }
        ksort($contributors);

        return '<ol>' . implode('', array_map(static fn (string $name): string => '<li>' . htmlspecialchars($name) . '</li>', $contributors)) . '</ol>';
    }
}
