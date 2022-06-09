<?php

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Publication\Service\Publication;

global $wiki;

$publicationService = $this->services->get(Publication::class);
$publication = null;

$entryManager = $wiki->services->get(EntryManager::class);
$entryController = $wiki->services->get(EntryController::class);
$templateEngine = $wiki->services->get(TemplateEngine::class);

/**
 * Print from {{ bazar2publication }} (dynamic results)
 */
if ($wiki->HasAccess('read') && isset($_GET['via']) && $_GET['via'] === 'bazarliste') {
    // we assemble bazar pages
    $content = '';
    $templateName = 'rendered-entries.tpl.html';
    if (!$templateEngine->hasTemplate('@bazar/'.$templateName)) {
        // backward compatibilty
        preg_match('#{{\s*bazar.+id="(.+)".+}}#siU', $wiki->page['body'], $matches);
        list(, $formId) = $matches;

        $query = isset($_GET['query']) ? $_GET['query'] : '';

        $results = $entryManager->search(['query' => $query, 'formsIds' => [$formId]]);

        $content = array_reduce($results, function ($html, $fiche) use ($entryController) {
            return $html . $entryController->view($fiche);
        }, '');
    }
    /**
     * Print from page, but render its {{ bazar* }} elements
     */
    elseif (preg_match('/({{(bazarliste|bazarcarto|calendrier|map|gogomap)\s[^}]*}})/i', $wiki->page['body'], $matches)) {
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
            $content = $this->Action($actionName, 0, $params);
        }
    }

    // we gather a few things from
    if (isset($_GET['template-page'])) {
        $templatePage = $wiki->services->get(PageManager::class)->getOne($_GET['template-page']);

        if ($templatePage) {
            // we inherit from template page user-defined styles
            if (isset($templatePage['metadatas']['theme'])) {
                $wiki->config['favorite_theme'] = $templatePage['metadatas']['theme'];
            }
            if (isset($templatePage['metadatas']['style'])) {
                $wiki->config['favorite_style'] = $templatePage['metadatas']['style'];
            }

            // {{bazar2publication templatepage="MyPage"}} + {{publication-template}} in MyPage
            if (preg_match('#{{\s*publication-template\s*}}#siU', $templatePage['body'])) {
                $content = preg_replace('#<!--publication-template-placeholder-->#siU', $content, $wiki->Format($templatePage['body']));
            }
        }
    }

    $publication = [
      'metadatas' => isset($templatePage) ? $templatePage['metadatas'] : array(),
      'content' => $content
    ];
}

/**
 * We print a Wiki page which has been created as an ebook
 */
elseif ($wiki->HasAccess('read')) {
    // if page is a bazar entry format the json into html
    if ($entryManager->isEntry($wiki->GetPageTag())) {
        $content = $entryController->view($this->GetPageTag(), 0);
    } else {
        // we remove the pager from the display
        $content = preg_replace(
            '#(<br />\n)?<ul class="pager">.+</ul>#sU',
            '',
            $wiki->Format($wiki->page["body"])
        );
    }

    $content = preg_replace('#(<br />\n){2,}#sU', "\n$1", $content);
    $content = preg_replace('#<br />\n(<h\d)#sU', "\n$1", $content);

    $publication = array(
      'metadatas' => $wiki->page['metadatas'],
      'content' => $content
    );
}

/**
 * We remove things which are troublesome for the layout
 *
 * 1. bazar fiche info footer (contains only edit/admin links)
 */
$publication['content'] = preg_replace('#<div class="clearfix"></div><div class="BAZ_fiche_info.+<!-- /.BAZ_fiche_info -->#sU', '', $publication['content']);

/**
 * We now generate the content
 */

if ($publication) {
    // user  options
    $metadatas = $publicationService->getOptions(
        $publication['metadatas'] ?? [],
        isset($_GET['layout'])
     ? [ "publication-fanzine" => ["layout" => $_GET['layout'] ] ]
     : []
    );

    //
    if (!$publicationService->isMode($metadatas['publication-mode'])) {
        //TODO turn into template
        return 'Mode inconnu';
    }

    // Load the cascade of publication styles
    $cssFiles = array_merge(
        glob('tools/publication/presentation/styles/print-layouts/'.$metadatas['publication-mode'].'.css'),
        glob('tools/publication/presentation/styles/*.css'),
        glob('themes/'.$wiki->config['favorite_theme'].'/tools/publication/*.css'),
        glob('themes/'.$wiki->config['favorite_theme'].'/tools/publication/print-layouts/'.$metadatas['publication-mode'].'.css'),
        glob('custom/tools/publication/*.css'),
        glob('custom/tools/publication/print-layouts/'.$metadatas['publication-mode'].'.css'),
    );

    array_map(function ($file) use ($wiki) {
        $wiki->AddCSSFile($file);
    }, $cssFiles);

    // cover image
    $coverImage = '';

    if ($metadatas['publication-cover-image']) {
        // use an external image
        if (preg_match('#^(https?://|//|/)#iU', $metadatas['publication-cover-image'])) {
            $coverImage = '<figure class="attached_file attached_file--external cover"><img src="'. $metadatas['publication-cover-image'] .'" alt="" class="img-responsive"></figure>';
        }
        // use a wiki attachment
        else {
            $coverImage = $wiki->Format('{{ attach file="'. $metadatas['publication-cover-image'] .'" desc=" " size="original" class="cover"}}');
        }
    }

    $blankpage = $wiki->Format('{{blankpage}}');

    // build the preview/printing page
    $output = $templateEngine->render('@publication/print-layouts/'.$metadatas['publication-mode'].'.twig', [
        "baseUrl" => $wiki->getBaseUrl(),
        "blankpage" => $blankpage,
        "content" => $publication['content'],
        "coverImage" => $coverImage,
        "siteTitle" => $wiki->GetConfigValue('wakka_name'),
        "metadatas" => $metadatas,
        "styles" => $wiki->Format('{{linkstyle}}{{linkjavascript}}'),
        //
        "initialPublicationState" => $publicationService->isPaged($metadatas['publication-mode']) ?: 'ready',
        "stylesModifiers" => $publicationService->getStyles($metadatas, ['debug' => $wiki->config['debug']]),
    ]);

    // Insert a blank page after a cover page
    $output = preg_replace('#(<section class="publication-cover">.+</section>)(<div class="include)#siU', '$1' . $blankpage . '$2', $output);
    $output = preg_replace('#(<div class="include publication-start">.+)(<div class="include)#siU', '$1' . $blankpage . '$2', $output);

    echo $output;
}
