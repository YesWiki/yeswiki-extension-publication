<?php

global $wiki;

$publication = null;

/**
 * We print bazar list results
 */
if ($wiki->HasAccess('read') && preg_match('#{{bazar#', $wiki->page['body'])) {
  // we assemble bazar pages
  preg_match('#{{\s*bazar.+id="(.+)".+}}#siU', $wiki->page['body'], $matches);
  list(, $id) = $matches;

  $results = baz_requete_recherche_fiches('', '', $id);
  $content = array_reduce($results, function($html, $fiche){
    return $html . baz_voir_fiche('', $fiche['tag']);
  }, '');

  // we gather a few things from
  if (isset($_GET['template-page'])) {
    $templatePage = $wiki->loadPage($_GET['template-page']);

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

  $publication = array(
    'metadatas' => $templatePage ? $templatePage['metadatas'] : array(),
    'content' => $content
  );
}

/**
 * We print a Wiki page which has been created as an ebook
 */
elseif ($wiki->HasAccess('read') && isset($wiki->page['metadatas']['publication-title'])) {
  // we remove the pager from the display
  $content = preg_replace(
      '#(<br />\n)?<ul class="pager">.+</ul>#sU',
      '',
      $wiki->Format($wiki->page["body"])
  );

  $content = preg_replace('#(<br />\n){2,}#sU', "\n$1", $content);
  $content = preg_replace('#<br />\n(<h\d)#sU', "\n$1", $content);

  $publication = array(
    'metadatas' => $wiki->page['metadatas'],
    'content' => $content
  );
}

/**
 * We now generate the content
 */

if ($publication) {
    include_once 'includes/squelettephp.class.php';
    $exportTemplate = new SquelettePhp('print-preview.tpl.html', 'publication');
    $publicationStyle = preg_replace('#^(.+)\.(.+)$#siU', '$1.publication.$2', $wiki->config['favorite_style']);

    //
    $wiki->AddCSSFile('tools/publication/presentation/styles/base.css');
    $wiki->AddCSSFile('tools/publication/presentation/styles/preview.css');
    $wiki->AddCSSFile('custom/tools/publication/print.css');
    $wiki->AddCSSFile('themes/'.$wiki->config['favorite_theme'].'/tools/publication/print.css');
    $wiki->AddCSSFile('themes/'.$wiki->config['favorite_theme'].'/styles/'.$publicationStyle);

    // user  options
    $options = array(
        "publication-hide-links-url" => '1',
        "publication-cover-page" => '0',
        "publication-book-fold" => '0',
        "publication-page-format" => 'A4',
        "publication-page-orientation" => 'portrait',
        "publication-pagination" => "bottom-center",
        "publication-print-marks" => '0'
    );

    $metadatas = array_merge($options, $publication['metadatas']);
    $blankpage = $wiki->Format('{{blankpage}}');

    // build the preview/printing page
    $output = $exportTemplate->render(array(
        "baseUrl" => $wiki->getBaseUrl(),
        "blankpage" => $blankpage,
        "content" => $publication['content'],
        "siteTitle" => $wiki->GetConfigValue('wakka_name'),
        "metadatas" => $metadatas,
        "styles" => $wiki->Format('{{linkstyle}}{{linkjavascript}}'),
        "stylesModifiers" => array(
            "yeswiki-publication",
            $wiki->config['debug'] === 'yes' ? 'debug' : '',
            // could be chosen, when creating an eBook
            "page-format--" . $metadatas['publication-page-format'],
            // could be chosen when creating an eBook
            "page-orientation--" . $metadatas['publication-page-orientation'],
            // OPTION book-cover
            $metadatas['publication-cover-page'] === '1' ? "book-cover" : '',
            // OPTION book-fold
            $metadatas['publication-book-fold'] === '1' ? "book-fold" : '',
            // OPTION show-print-marks
            $metadatas['publication-print-marks'] === '1' ? "show-print-marks" : '',
            // OPTION show-print-marks
            "page-number-position--" . $metadatas['publication-pagination'],
            // OPTION hide-links-from-print
            $metadatas['publication-hide-links-url'] === '1' ? "hide-links-url" : '',
        ),
    ));

    // Insert a blank page after a cover page
    $output = preg_replace('#(<section class="publication-cover">.+</section>)(<div class="include)#siU', '$1' . $blankpage . '$2', $output);
    $output = preg_replace('#(<div class="include publication-start">.+)(<div class="include)#siU', '$1' . $blankpage . '$2', $output);

    echo $output;
}
