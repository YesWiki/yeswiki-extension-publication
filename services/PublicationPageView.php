<?php

namespace YesWiki\Publication\Service;

use YesWiki\Content\Entity\AppendsToPageView;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Render\Service\TemplateEngine;

/** Below a shown page: its publication card when it is an ebook, else a PDF link for whoever sees the edit bar. */
class PublicationPageView implements AppendsToPageView
{
    public function __construct(
        private PageManager $pageManager,
        private AclService $aclService,
        private AuthenticationService $authenticationService,
        private Publication $publication,
        private TemplateEngine $templateEngine
    ) {
    }

    public function appendToPageView(string $tag): string
    {
        if ($tag === '' || !$this->aclService->hasAccess('read', $tag) || $this->pageManager->getOne($tag) === null) {
            return '';
        }
        $metadata = $this->pageManager->getMetadata($tag) ?? [];
        if ($this->publication->isPublication($metadata)) {
            return $this->templateEngine->render('@publication/show.twig', [
                'publication' => [
                    'tag' => $tag,
                    'metas' => $this->publication->getOptions($metadata),
                    'hasWriteAccess' => $this->aclService->hasAccess('write', $tag),
                    'hasDeleteAccess' => $this->aclService->isAdmin() || $this->aclService->isOwner($tag),
                ],
            ]);
        }
        if ($this->authenticationService->getLoggedUser() === '' && !$this->aclService->hasAccess('write', $tag)) {
            return '';
        }

        return $this->templateEngine->render('@publication/pdf-link.twig', ['tag' => $tag]);
    }
}
