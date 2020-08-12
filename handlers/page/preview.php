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
        "book-cover" => true,
        "hide-links-url" => true,
        "page-format" => 'A4',
        "page-orientation" => 'portrait',
        "show-print-marks" => false
    );

    // build the preview/printing page
    echo $exportTemplate->render(array(
        "baseUrl" => $wiki->getBaseUrl(),
        "content" => $content,
        "siteTitle" => $wiki->GetConfigValue('wakka_name'),
        "options" => $options,
        "title" => $wiki->page['metadatas']['publication-title'],
        "styles" => $wiki->Format("{{linkstyle}}"),
        "stylesheets" => array_filter(array(
            'tools/publication/presentation/styles/preview.css',
            'tools/publication/presentation/styles/base.css',
            file_exists($themeCustomCSS) ? $themeCustomCSS : ''
        )),
        "stylesModifiers" => array(
            "print",
            // could be chosen, when creating an eBook
            "page-format--" . $options['page-format'],
            // could be chosen when creating an eBook
            "page-orientation--" . $options['page-orientation'],
            // OPTION book-cover
            $options['book-cover'] ? "book-cover" : '',
            // OPTION show-print-marks
            $options['show-print-marks'] ? "show-print-marks" : '',
            // OPTION hide-links-from-print
            $options['hide-links-url'] ? "hide-links-url" : '',
        ),
    ));
}
