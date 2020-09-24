<?php

global $wiki;

if ($wiki->HasAccess('read') && isset($wiki->page['metadatas']['publication-title'])) {
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

    $metadatas = array_merge($options, $wiki->page['metadatas']);
    $blankpage = $wiki->Format('{{blankpage}}');

    // build the preview/printing page
    $output = $exportTemplate->render(array(
        "baseUrl" => $wiki->getBaseUrl(),
        "blankpage" => $blankpage,
        "content" => $content,
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
