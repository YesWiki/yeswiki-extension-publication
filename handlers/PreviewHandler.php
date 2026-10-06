<?php

namespace YesWiki\Publication\Handler;

use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\BazarListService;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Publication\Action\PublicationTemplateAction;
use YesWiki\Publication\Service\PdfHelper;
use YesWiki\Publication\Service\Publication;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TemplateEngine;

/** `/PageName/preview`: the page, or the entries of its list, laid out for print by Paged.js -- what the PDF is a capture of. */
class PreviewHandler extends YesWikiHandler implements RegisteredHandler
{
    private const EXTENSION_DIR = 'extensions/publication/';

    public static function performableName(): string
    {
        return 'preview';
    }

    public function run(): string
    {
        $this->denyAccessUnlessGranted('read');
        $publication = $this->getService(Publication::class);
        $query = $this->getRequest()->query->all();

        $printed = in_array($query['via'] ?? null, ['entrylist', 'bazarliste'], true)
            ? $this->entriesOfTheList($query)
            : $this->thisPage();

        $layout = is_string($query['layout'] ?? null) && in_array($query['layout'], Publication::FANZINE_LAYOUTS, true)
            ? ['publication-fanzine' => ['layout' => $query['layout']]]
            : [];
        $options = $publication->getOptions(
            $publication->isPublication($printed['metadata']) ? [] : ['publication-mode' => Publication::LAYOUT_PAGE],
            $printed['metadata'],
            $layout
        );
        $mode = (string)$options['publication-mode'];
        if (!$publication->isMode($mode)) {
            return $this->renderFullPage('@core/alert-message.twig', ['type' => 'danger', 'message' => _t('PUBLICATION_UNKNOWN_MODE')]);
        }

        $blankPage = $this->getService(ActionRunner::class)->action('blankpage');
        $paged = $publication->isPaged($mode);
        $this->declareAssets($mode, $options, $paged, ($query['browserPrintAfterRendered'] ?? '') === '1');

        $body = $this->render('@publication/print-layouts/' . $mode . '.twig', [
            'blankpage' => $blankPage,
            'content' => $printed['content'],
            'coverImage' => $this->coverImage((string)$options['publication-cover-image']),
            'metadatas' => $options,
            'initialPublicationState' => $paged ? 'awaiting-layout' : 'ready',
            'stylesModifiers' => $publication->getStyles($options, (bool)$this->getService(RuntimeConfig::class)->getValue('debug')),
        ]);
        $body = (string)preg_replace('#(<div class="include publication-cover">.+</div>)(\s*<div class="include)#siU', '$1' . $blankPage . '$2', $body);
        $body = (string)preg_replace('#(<div class="include publication-start[^"]*">.+)(\s*<div class="include)#siU', '$1' . $blankPage . '$2', $body);

        return $this->throughProxy($this->getService(TemplateEngine::class)->renderHead() . $body);
    }

    /**
     * The page itself, or the entry it is, as HTML.
     *
     * @return array{content: string, metadata: array<string, mixed>}
     */
    private function thisPage(): array
    {
        $pageContext = $this->getService(PageContext::class);
        $tag = $pageContext->getTag();
        if ($this->getService(EntryManager::class)->isEntry($tag)) {
            $content = (string)$this->getService(EntryController::class)->view($tag, '', false);
        } else {
            $content = $this->getService(MarkdownFormatterService::class)->format(PageBody::content(($pageContext->getPage() ?? [])['body'] ?? []));
            $content = (string)preg_replace('#(<br />\n)?<ul class="(?:yw-)?pager">.+</ul>#sU', '', $content);
        }
        $content = (string)preg_replace('#(<br />\n){2,}#sU', "\n$1", $content);
        $content = (string)preg_replace('#<br />\n(<h\d)#sU', "\n$1", $content);

        return ['content' => $content, 'metadata' => $pageContext->getMetadata()];
    }

    /**
     * The entries the page's first list shows with the facets in the address, each drawn in full; inside a template page when one is named.
     *
     * @param array<string, mixed> $query
     *
     * @return array{content: string, metadata: array<string, mixed>}
     */
    private function entriesOfTheList(array $query): array
    {
        $pageContext = $this->getService(PageContext::class);
        $listArguments = $this->getService(PdfHelper::class)->firstListArguments(PageBody::content(($pageContext->getPage() ?? [])['body'] ?? []));
        if (is_string($query['query'] ?? null) && $query['query'] !== '') {
            $listArguments['query'] = $query['query'];
        }
        $listService = $this->getService(BazarListService::class);
        $entries = $listService->filterEntriesOnFacets($listService->getEntries($listArguments + ['id' => '']));

        $entryController = $this->getService(EntryController::class);
        $content = implode('', array_map(static fn (array $entry): string => (string)$entryController->view($entry, '', false), $entries));

        $metadata = [];
        $templateTag = is_string($query['template-page'] ?? null) ? trim($query['template-page']) : '';
        $templatePage = $templateTag === '' ? null : $this->getService(PageManager::class)->getOne($templateTag);
        if ($templatePage !== null) {
            $metadata = is_array($templatePage['metadatas'] ?? null) ? $templatePage['metadatas'] : [];
            $templateContent = PageBody::content(is_array($templatePage['body'] ?? null) ? $templatePage['body'] : []);
            if (preg_match('/\{\{\s*publication-template\s*\}\}/i', $templateContent) === 1) {
                $content = str_replace(
                    PublicationTemplateAction::PLACEHOLDER,
                    $content,
                    $this->getService(MarkdownFormatterService::class)->format($templateContent)
                );
            }
        }

        return ['content' => $content, 'metadata' => $metadata];
    }

    /**
     * The print layout's stylesheets and scripts, declared for the head to carry them.
     *
     * @param array<string, mixed> $options
     */
    private function declareAssets(string $mode, array $options, bool $paged, bool $printWhenReady): void
    {
        $assets = $this->getService(AssetRegistry::class);
        $storage = $this->getService(Storage::class);
        foreach ([self::EXTENSION_DIR . "styles/print-layouts/$mode.css", self::EXTENSION_DIR . 'styles/print.css', self::EXTENSION_DIR . 'styles/preview.css'] as $file) {
            $assets->addCssFile($file);
        }
        foreach (array_merge($storage->glob('custom/publication/*.css'), $storage->glob("custom/publication/print-layouts/$mode.css")) as $file) {
            $assets->addCssFile($file);
        }
        if ($mode !== Publication::LAYOUT_FANZINE) {
            $book = $options['publication-book'];
            $marks = $book['print-marks'] === '1' ? ' bleed: 6mm; marks: crop cross;' : '';
            $size = preg_replace('/[^A-Za-z0-9 ]/', '', $book['page-format'] . ' ' . $book['page-orientation']);
            $assets->addCss("@media print { @page { size: {$size};{$marks} } }");
        }
        $assets->addJs('var browserPrintAfterRendered = ' . ($printWhenReady ? 'true' : 'false') . ';');
        $assets->addJsFile(self::EXTENSION_DIR . 'javascripts/browser/is-pdf-ready.js');
        if ($paged) {
            $assets->addJsFile(self::EXTENSION_DIR . 'javascripts/browser/print.js', false, true);
        } elseif ($printWhenReady) {
            $assets->addJs('window.addEventListener("load", () => window.print());');
        }
    }

    /** The cover: an image from elsewhere, or a file attached to the wiki. */
    private function coverImage(string $image): string
    {
        if ($image === '') {
            return '';
        }
        if (preg_match('#^(https?://|//|/)#i', $image) === 1) {
            return '<figure class="attached_file attached_file--external cover"><img src="' . htmlspecialchars($image) . '" alt=""></figure>';
        }

        return $this->getService(ActionRunner::class)->action('attach', [
            'file' => $image,
            'desc' => ' ',
            'size' => 'original',
            'class' => 'cover',
        ]);
    }

    /** The page as the headless browser fetches it, when it reaches the wiki through another address than readers do. */
    private function throughProxy(string $output): string
    {
        $proxyBaseUrl = $this->stringParam('htmltopdf_base_url');
        if ($proxyBaseUrl === '') {
            return $output;
        }
        $baseUrl = $this->stringParam('base_url');
        $origin = $this->origin($baseUrl);
        $proxyOrigin = $this->origin($proxyBaseUrl);
        $request = $this->getRequest();
        if (!str_starts_with($request->getSchemeAndHttpHost() . '/', $proxyOrigin)) {
            return $output;
        }

        return str_replace([$baseUrl, $origin], [$proxyBaseUrl, $proxyOrigin], $output);
    }

    /** Scheme, host and any port that is not the scheme's, with a trailing slash. */
    private function origin(string $url): string
    {
        $parts = parse_url($url) ?: [];
        $scheme = $parts['scheme'] ?? 'http';
        $port = (string)($parts['port'] ?? '');
        $port = ($port === '' || $port === ($scheme === 'https' ? '443' : '80')) ? '' : ':' . $port;

        return $scheme . '://' . ($parts['host'] ?? '') . $port . '/';
    }

    private function stringParam(string $name): string
    {
        $value = $this->params->has($name) ? $this->params->get($name) : '';

        return is_scalar($value) ? (string)$value : '';
    }
}
