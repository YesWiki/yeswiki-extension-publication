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
    protected $aclService ;
    protected $assetsManager ;
    protected $entryController ;
    protected $entryManager ;
    protected $pageManager ;
    protected $publicationService ;
    protected $templateEngine ;

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

        $publication = $this->getContentAndPublication($_GET ?? []);

        /**
         * We remove things which are troublesome for the layout
         *
         * 1. bazar fiche info footer (contains only edit/admin links)
         */
        $publication['content'] = preg_replace('#<div class="clearfix"></div><div class="BAZ_fiche_info.+<!-- /.BAZ_fiche_info -->#sU', '', $publication['content']);

        /**
         * We now generate the content
         */
        // user  options
        $metadatas = $this->publicationService->getOptions(
            $publication['metadatas'] ?? [],
            isset($_GET['layout'])
                ? [ "publication-fanzine" => ["layout" => $_GET['layout'] ] ]
                : []
        );

        if (!$this->publicationService->isMode($metadatas['publication-mode'])) {
            return $this->renderInSquelette('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => 'Mode inconnu'
            ]);
        }

        $this->addCssFiles($metadatas);
        $blankpage = $this->wiki->Format('{{blankpage}}');

        // build the preview/printing page
        $output = $this->render('@publication/print-layouts/'.$metadatas['publication-mode'].'.twig', [
            "baseUrl" => $this->wiki->getBaseUrl(),
            "blankpage" => $blankpage,
            "content" => $publication['content'],
            "coverImage" => $this->getCoverImage($metadatas),
            "siteTitle" => $this->params->get('wakka_name'),
            "metadatas" => $metadatas,
            "styles" => $this->wiki->Format('{{linkstyle}}{{linkjavascript}}'),
            //
            "initialPublicationState" => $this->publicationService->isPaged($metadatas['publication-mode']) ?: 'ready',
            "stylesModifiers" => $this->publicationService->getStyles($metadatas, ['debug' => $this->wiki->config['debug']]),
        ]);

        // Insert a blank page after a cover page
        $output = preg_replace('#(<section class="publication-cover">.+</section>)(<div class="include)#siU', '$1' . $blankpage . '$2', $output);
        $output = preg_replace('#(<div class="include publication-start">.+)(<div class="include)#siU', '$1' . $blankpage . '$2', $output);

        $this->sanitizeUrlForProxy($output);

        return $output;
    }

    protected function getContentAndPublication(array $get): array
    {
        $content = '';
        $publication = [];
        /**
         * Print from {{ bazar2publication }} (dynamic results)
         */
        if ($this->aclService->hasAccess('read') && isset($get['via']) && $get['via'] === 'bazarliste') {
            // we assemble bazar pages
            $content = '';
            $templateName = 'rendered-entries.twig';
            if (!$this->templateEngine->hasTemplate('@bazar/'.$templateName)) {
                // backward compatibilty
                if (preg_match('#{{\s*bazar.+id="(.+)".+}}#siU', $this->wiki->page['body'], $matches)) {
                    list(, $formId) = $matches;

                    $query = $get['query'] ?? '';

                    $results = $this->entryManager->search(['query' => $query, 'formsIds' => [$formId]]);

                    $content = array_reduce($results, function ($html, $fiche) {
                        return $html . $this->entryController->view($fiche);
                    }, '');
                }
            }
            /**
             * Print from page, but render its {{ bazar* }} elements
             */
            elseif (preg_match('/({{(bazarliste|bazarcarto|calendrier|map|gogomap)\s[^}]*}})/i', $this->wiki->page['body'], $matches)) {
                $actionText = $matches[1];
                $actionName = $matches[2];
                $matches = [];
                $params = [];
                if (preg_match_all('/([a-zA-Z0-9_]*)=\"(.*)\"/U', $actionText, $matches)) {
                    foreach ($matches[0] as $id => $match) {
                        $params[$matches[1][$id]] = $matches[2][$id];
                    }
                    // redefine template
                    $params['template'] = $templateName;
                    $params['dynamic'] = false;
                    $params['search'] = false;
                    $params['entryController'] = $this->entryController;
                    if (isset($params['groups'])) {
                        unset($params['groups']);
                    }
                    $content = $this->wiki->Action($actionName, 0, $params);
                }
            }

            // we gather a few things from
            if (isset($get['template-page'])) {
                if (!is_string($get['template-page'])) {
                    throw new Exception("'template-page' should be a string");
                }
                $templatePage = $this->pageManager->getOne($get['template-page']);

                if ($templatePage) {
                    // we inherit from template page user-defined styles
                    if (isset($templatePage['metadatas']['theme'])) {
                        $this->wiki->config['favorite_theme'] = $templatePage['metadatas']['theme'];
                    }
                    if (isset($templatePage['metadatas']['style'])) {
                        $this->wiki->config['favorite_style'] = $templatePage['metadatas']['style'];
                    }

                    // {{bazar2publication templatepage="MyPage"}} + {{publication-template}} in MyPage
                    if (preg_match('#{{\s*publication-template\s*}}#siU', $templatePage['body'])) {
                        $content = preg_replace('#<!--publication-template-placeholder-->#siU', $content, $this->wiki->Format($templatePage['body']));
                    }
                }
            }

            $publication = [
                'metadatas' => $templatePage['metadatas'] ?? [],
                'content' => $content
            ];
        }

        /**
         * We print a Wiki page which has been created as an ebook
         */
        elseif ($this->aclService->hasAccess('read')) {
            // if page is a bazar entry format the json into html
            if ($this->entryManager->isEntry($this->wiki->GetPageTag())) {
                $content = $this->entryController->view($this->GetPageTag(), 0);
            } else {
                // we remove the pager from the display
                $content = preg_replace(
                    '#(<br />\n)?<ul class="pager">.+</ul>#sU',
                    '',
                    $this->wiki->Format($this->wiki->page["body"])
                );
            }

            $content = preg_replace('#(<br />\n){2,}#sU', "\n$1", $content);
            $content = preg_replace('#<br />\n(<h\d)#sU', "\n$1", $content);

            $publication = array(
                'metadatas' => $this->wiki->page['metadatas'],
                'content' => $content
            );
        }
        return $publication;
    }

    protected function addCssFiles(array $metadatas)
    {
        // Load the cascade of publication styles
        $cssFiles = array_merge(
            glob('tools/publication/styles/print-layouts/'.$metadatas['publication-mode'].'.css'),
            glob('tools/publication/styles/*.css'),
            glob('themes/'.$this->wiki->config['favorite_theme'].'/tools/publication/*.css'),
            glob('themes/'.$this->wiki->config['favorite_theme'].'/tools/publication/print-layouts/'.$metadatas['publication-mode'].'.css'),
            glob('custom/tools/publication/*.css'),
            glob('custom/tools/publication/print-layouts/'.$metadatas['publication-mode'].'.css'),
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
            if (preg_match('#^(https?://|//|/)#iU', $metadatas['publication-cover-image'])) {
                $coverImage = '<figure class="attached_file attached_file--external cover"><img src="'. $metadatas['publication-cover-image'] .'" alt="" class="img-responsive"></figure>';
            }
            // use a wiki attachment
            else {
                $coverImage = $this->wiki->Format('{{ attach file="'. $metadatas['publication-cover-image'] .'" desc=" " size="original" class="cover"}}');
            }
        }

        return $coverImage;
    }

    protected function sanitizeUrlForProxy(string &$output)
    {
        if ($this->params->get('htmltopdf_base_url')) {
            ['scheme' => $scheme, 'host' => $host, 'port' => $port] = parse_url($this->params->get('base_url'));
            $base_url = $scheme . '://' . $host . (!$port || (string)$port === '80' ? '' : ':'.$port) . '/';

            ['scheme' => $scheme, 'host' => $host, 'port' => $port] = parse_url($this->params->get('htmltopdf_base_url'));
            $new_base_url = $scheme . '://' . $host . (!$port || (string)$port === '80' ? '' : ':'.$port) . '/';

            $full_request_url = $_SERVER['REQUEST_SCHEME'] . '://'. $_SERVER['HTTP_HOST'] . ((string)$_SERVER['SERVER_PORT'] === '80' ? '' : ':'.$_SERVER['SERVER_PORT']) . $_SERVER['REQUEST_URI'];

            // Replaces https://example.com/?Accueil by http://localhost:8000/?Accueil
            // Replaces https://example.com/favicon.ico by http://localhost:8000/favicon.ico
            if (strpos($full_request_url, $new_base_url) === 0) {
                $output = str_replace([$this->params->get('base_url'), $base_url], [$this->params->get('htmltopdf_base_url'), $new_base_url], $output);
            }
        }
    }
}
