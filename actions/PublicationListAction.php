<?php

namespace YesWiki\Publication\Action;

use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Publication\Service\Publication;

/** `{{publicationlist}}`: the publications made with `{{publicationgenerator}}`, found by the prefix of their pages. */
class PublicationListAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'publicationlist';
    }

    public function components(): array
    {
        return [
            Component::for('publicationlist')
                ->category(Category::Lists)
                ->label(_t('AB_publication_publicationlist_label'))
                ->icon('book')
                ->adminOnly()
                ->settings(
                    Setting::text('pagenameprefix')
                        ->label(_t('AB_publication_publicationlist_pagenameprefix_label'))
                        ->default('Ebook'),
                ),
        ];
    }

    public function formatArguments($arg): array
    {
        return [
            'pagenameprefix' => (empty($arg['pagenameprefix']) || !is_string($arg['pagenameprefix'])) ? 'Ebook' : $arg['pagenameprefix'],
        ];
    }

    public function run(): string
    {
        $pageManager = $this->getService(PageManager::class);
        $aclService = $this->getService(AclService::class);
        $publication = $this->getService(Publication::class);
        $prefix = mb_strtolower($this->arguments['pagenameprefix']);

        $publications = [];
        foreach ($pageManager->tagsOfType(PageType::PAGE) as $tag) {
            if (!str_starts_with(mb_strtolower($tag), $prefix) || !$aclService->hasAccess('read', $tag)) {
                continue;
            }
            $metadata = $pageManager->getMetadata($tag) ?? [];
            if (!$publication->isPublication($metadata)) {
                continue;
            }
            $publications[] = [
                'tag' => $tag,
                'metas' => $publication->getOptions($metadata),
                'hasWriteAccess' => $aclService->hasAccess('write', $tag),
                'hasDeleteAccess' => $aclService->isAdmin() || $aclService->isOwner($tag),
            ];
        }

        return $this->render('@publication/publicationlist.twig', ['publications' => $publications]);
    }
}
