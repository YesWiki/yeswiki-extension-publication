<?php

global $wiki;

if ($wiki->HasAccess('read') && isset($wiki->page['metadatas']['publication-title'])) {
    include_once 'includes/squelettephp.class.php';
    $exportTemplate = new SquelettePhp('print-preview.tpl.html', 'publication');

    $themeCustomCSS = 'themes/' . $wiki->config['favorite_theme'] . '/styles/publication.override.css';

    // we remove the pager from the display
    $content = preg_replace(
        '#(<br />\n)?<ul class="pager">.+</ul>#sU',
        '',
        $wiki->Format($wiki->page["body"])
    );
    $content = preg_replace('#(<br />\n){2,}#sU', "\n$1", $content);
    $content = preg_replace('#<br />\n(<h\d)#sU', "\n$1", $content);

    // user  options
    $options = array(
        "publication-hide-links-url" => '1',
        "publication-cover-page" => '1',
        "publication-page-format" => 'A4',
        "publication-page-orientation" => 'portrait',
        "publication-print-marks" => '0'
    );

    $metadatas = array_merge($options, $wiki->page['metadatas']);

    // build the preview/printing page
    echo $exportTemplate->render(array(
        "baseUrl" => $wiki->getBaseUrl(),
        "content" => $content,
        "siteTitle" => $wiki->GetConfigValue('wakka_name'),
        "metadatas" => $metadatas,
        "styles" => $wiki->Format("{{linkstyle}}"),
        "stylesheets" => array_filter(array(
            'tools/publication/presentation/styles/preview.css',
            'tools/publication/presentation/styles/base.css',
            file_exists($themeCustomCSS) ? $themeCustomCSS : ''
        )),
        "stylesModifiers" => array(
            "print",
            // could be chosen, when creating an eBook
            "page-format--" . $metadatas['publication-page-format'],
            // could be chosen when creating an eBook
            "page-orientation--" . $metadatas['publication-page-orientation'],
            // OPTION book-cover
            $metadatas['publication-cover-page'] === '1' ? "book-cover" : '',
            // OPTION show-print-marks
            $metadatas['publication-print-marks'] === '1' ? "show-print-marks" : '',
            // OPTION hide-links-from-print
            $metadatas['publication-hide-links-url'] === '1' ? "hide-links-url" : '',
        ),
    ));
}
