<?php

global $wiki;

if ($wiki->HasAccess('read') && isset($wiki->page['metadatas']['publication-title'])) {
    include_once 'includes/squelettephp.class.php';
    $exportTemplate = new SquelettePhp('print-preview.tpl.html', 'publication');

    $themeCustomCSS = 'themes/' . $wiki->config['favorite_theme'] . '/styles/publication.override.css';

    // user  options
    $options = array(
        "page-format" => 'a4',
        "page-orientation" => 'portrait'
    );

    // build the preview/printing page
    echo $exportTemplate->render(array(
        "baseUrl" => $wiki->getBaseUrl(),
        "content" => $wiki->Format($wiki->page["body"]),
        "siteTitle" => $wiki->GetConfigValue('wakka_name'),
        "title" => $wiki->page['metadatas']['publication-title'],
        "styles" => $wiki->Format("{{linkstyle}}"),
        "stylesheets" => array_filter(array(
            'tools/publication/presentation/styles/preview.css',
            'tools/publication/presentation/styles/base.css',
            'tools/publication/presentation/styles/page-format-'. $options['page-format'] .'.css',
            // 'tools/publication/presentation/styles/page-orientation-'. $options['page-orientation'] .'.css',
            $options['show-marks'] ? 'tools/publication/presentation/styles/page-marks.css' : '',
            file_exists($themeCustomCSS) ? $themeCustomCSS : ''
        )),
        "stylesModifiers" => array(
            "print",
            // could be chosen, when creating an eBook
            "page-format--" . $options['page-format'],
            // could be chosen when creating an eBook
            "page-orientation--" . $options['page-orientation'],
            // OPTION show-print-marks
            "show-print-marks",
            // OPTION hide-links-from-print
            "hide-links-from-print",
        ),
    ));
}
