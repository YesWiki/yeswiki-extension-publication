<?php

namespace YesWiki\Publication;

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\AssetsManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Publication\Service\Publication;

class PreviewHandler extends YesWikiHandler
{
    protected $aclService;
    protected $assetsManager;
    protected $entryController;
    protected $entryManager;
    protected $pageManager;
    protected $publicationService;
    protected $templateEngine;

    public function run()
    {
        // get Services
        $this->aclService = $this->getService(AclService::class);
        $this->assetsManager = $this->getService(AssetsManager::class);
        $this->entryController = $this->getService(EntryController::class);
        $this->entryManager = $this->getService(EntryManager::class);
        $this->pageManager = $this->getService(PageManager::class);
        $this->publicationService = $this->getService(Publication::class);
        $this->templateEngine = $this->getService(TemplateEngine::class);

        if (!$this->aclService->hasAccess('read')) {
            return $this->renderInSquelette('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('ERROR_NO_ACCESS'),
            ]);
        }

        $publication = $this->getContentAndPublication($_GET ?? []);

        /*
         * We remove things which are troublesome for the layout
         *
         * 1. bazar fiche info footer (contains only edit/admin links)
         */
        $publication['content'] = preg_replace(
            '#<div class="clearfix"></div><div class="BAZ_fiche_info.+<!-- /.BAZ_fiche_info -->#sU',
            '',
            $publication['content'],
        );

        /**
         * We now generate the content.
         */
        // user  options
        $metadatas = $this->publicationService->getOptions(
            $publication['metadatas'] ?? [],
            isset($_GET['layout'])
                ? ['publication-fanzine' => ['layout' => $_GET['layout']]]
                : [],
        );

        if (
            !$this->publicationService->isMode($metadatas['publication-mode'])
        ) {
            return $this->renderInSquelette('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => 'Mode inconnu',
            ]);
        }

        $this->addCssFiles($metadatas);
        $blankpage = $this->wiki->Format('{{blankpage}}');

        // build the preview/printing page
        $output = $this->render(
            '@publication/print-layouts/' .
                $metadatas['publication-mode'] .
                '.twig',
            [
                'baseUrl' => $this->wiki->getBaseUrl(),
                'blankpage' => $blankpage,
                'content' => $publication['content'],
                'coverImage' => $this->getCoverImage($metadatas),
                'siteTitle' => $this->params->get('wakka_name'),
                'metadatas' => $metadatas,
                'styles' => $this->wiki->Format(
                    '{{linkstyle}}{{linkjavascript}}',
                ),

                // isPaged returns a bool, twig used to print it as '1'
                'initialPublicationState' => $this->publicationService->isPaged(
                    $metadatas['publication-mode'],
                ) ? 'awaiting-layout' : 'ready',
                'stylesModifiers' => $this->publicationService->getStyles(
                    $metadatas,
                    ['debug' => $this->wiki->config['debug']],
                ),
                'browserPrintAfterRendered' => filter_input(
                    INPUT_GET,
                    'browserPrintAfterRendered',
                    FILTER_VALIDATE_BOOLEAN,
                ) === true,
            ],
        );

        // Insert a blank page after a cover page
        $output = preg_replace(
            '#(<section class="publication-cover">.+</section>)(<div class="include)#siU',
            '$1' . $blankpage . '$2',
            $output,
        );
        $output = preg_replace(
            '#(<div class="include publication-start">.+)(<div class="include)#siU',
            '$1' . $blankpage . '$2',
            $output,
        );

        $this->sanitizeUrlForProxy($output);

        return $output;
    }

    protected function getContentAndPublication(array $get): array
    {
        $content = '';
        $publication = ['metadatas' => [], 'content' => ''];
        /*
         * Print from {{ bazar2publication }} (dynamic results)
         */
        if (
            isset($get['via'])
            && $get['via'] === 'bazarliste'
        ) {
            // we assemble bazar pages
            $content = '';
            $query = $get['query'] ?? '';
            $results = $this->entryManager->search([
                'queries' => $query,
            ]);

            $content = array_reduce(
                $results,
                function ($html, $fiche) {
                    return $html . $this->entryController->view($fiche);
                },
                '',
            );

            // we gather a few things from
            if (!empty($get['template-page'])) {
                if (!is_string($get['template-page'])) {
                    throw new \Exception("'template-page' should be a string");
                }
                $templatePage = $this->pageManager->getOne(
                    $get['template-page'],
                );

                if ($templatePage) {
                    // we inherit from template page user-defined styles
                    if (isset($templatePage['metadatas']['theme'])) {
                        $this->wiki->config['favorite_theme'] =
                            $templatePage['metadatas']['theme'];
                    }
                    if (isset($templatePage['metadatas']['style'])) {
                        $this->wiki->config['favorite_style'] =
                            $templatePage['metadatas']['style'];
                    }

                    // {{bazar2publication templatepage="MyPage"}} + {{publication-template}} in MyPage
                    if (
                        preg_match(
                            "#{{\s*publication-template\s*}}#siU",
                            $templatePage['body'],
                        )
                    ) {
                        $content = preg_replace(
                            '#<!--publication-template-placeholder-->#siU',
                            $content,
                            $this->wiki->Format($templatePage['body']),
                        );
                    }
                }
            }

            $publication = [
                'metadatas' => $templatePage['metadatas'] ?? [],
                'content' => $content,
            ];
        } /*
         * We print a Wiki page which has been created as an ebook
         */ else {
            // if page is a bazar entry format the json into html
            if ($this->entryManager->isEntry($this->wiki->GetPageTag())) {
                $content = $this->entryController->view(
                    $this->wiki->GetPageTag(),
                    0,
                );
            } else {
                // we remove the pager from the display
                $content = preg_replace(
                    '#(<br />\n)?<ul class="pager">.+</ul>#sU',
                    '',
                    $this->wiki->Format($this->wiki->page['body']),
                );
            }

            $content = preg_replace('#(<br />\n){2,}#sU', "\n$1", $content);
            $content = preg_replace('#<br />\n(<h\d)#sU', "\n$1", $content);

            $publication = [
                'metadatas' => $this->wiki->page['metadatas'] ?? [],
                'content' => $content,
            ];
        }

        return $publication;
    }

    protected function addCssFiles(array $metadatas)
    {
        $mode = $metadatas['publication-mode'];
        $theme = $this->wiki->config['favorite_theme'] ?? '';

        // Load the cascade of publication styles
        $cssFiles = array_merge(
            ...array_map(function ($pattern) {
                // glob returns false when the directory cannot be read
                return glob($pattern) ?: [];
            }, [
                "tools/publication/styles/print-layouts/$mode.css",
                'tools/publication/styles/*.css',
                "themes/$theme/tools/publication/*.css",
                "themes/$theme/tools/publication/print-layouts/$mode.css",
                'custom/tools/publication/*.css',
                "custom/tools/publication/print-layouts/$mode.css",
            ])
        );

        array_map(function ($file) {
            $this->assetsManager->AddCSSFile($file);
        }, $cssFiles);
    }

    protected function getCoverImage(array $metadatas): string
    {
        // cover image
        $coverImage = '';

        if ($metadatas['publication-cover-image']) {
            // use an external image
            if (
                preg_match(
                    '#^(https?://|//|/)#iU',
                    $metadatas['publication-cover-image'],
                )
            ) {
                $coverImage =
                    '<figure class="attached_file attached_file--external cover"><img src="' .
                    $metadatas['publication-cover-image'] .
                    '" alt="" class="img-responsive"></figure>';
            }
            // use a wiki attachment
            else {
                $coverImage = $this->wiki->Format(
                    '{{ attach file="' .
                        $metadatas['publication-cover-image'] .
                        '" desc=" " size="original" class="cover"}}',
                );
            }
        }

        return $coverImage;
    }

    protected function sanitizeUrlForProxy(string &$output)
    {
        $proxyBaseUrl = $this->params->get('htmltopdf_base_url');
        if (empty($proxyBaseUrl)) {
            return;
        }

        $base_url = $this->getOrigin($this->params->get('base_url'));
        $new_base_url = $this->getOrigin($proxyBaseUrl);

        // Replaces https://example.com/?Accueil by http://localhost:8000/?Accueil
        // Replaces https://example.com/favicon.ico by http://localhost:8000/favicon.ico
        if (strpos($this->getCurrentUrl(), $new_base_url) === 0) {
            $output = str_replace(
                [$this->params->get('base_url'), $base_url],
                [$proxyBaseUrl, $new_base_url],
                $output,
            );
        }
    }

    /**
     * scheme, host and non default port of an url, with a trailing slash
     */
    protected function getOrigin(string $url): string
    {
        $parts = parse_url($url) ?: [];
        $scheme = $parts['scheme'] ?? 'http';

        return $scheme
            . '://'
            . ($parts['host'] ?? '')
            . $this->formatPort($scheme, strval($parts['port'] ?? ''))
            . '/';
    }

    /**
     * url the current request came in on
     */
    protected function getCurrentUrl(): string
    {
        $scheme = $_SERVER['REQUEST_SCHEME']
            ?? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
        // HTTP_HOST already carries the port, appending SERVER_PORT would double it
        $host = strval($_SERVER['HTTP_HOST'] ?? '');
        $port = '';
        if (preg_match('/^(.*):(\d+)$/', $host, $matches)) {
            $host = $matches[1];
            $port = $matches[2];
        }

        return $scheme
            . '://'
            . $host
            . $this->formatPort($scheme, $port)
            . strval($_SERVER['REQUEST_URI'] ?? '');
    }

    /**
     * ':1234', or an empty string when the port is the default one of the scheme
     */
    protected function formatPort(string $scheme, string $port): string
    {
        $defaultPort = ($scheme === 'https') ? '443' : '80';

        return (empty($port) || $port === $defaultPort) ? '' : ':' . $port;
    }
}
