<?php

global $wiki;

if ($wiki->HasAccess('read') && isset($wiki->page['metadatas']['publication-title'])) {
    include_once 'includes/squelettephp.class.php';
    $exportTemplate = new SquelettePhp('print-preview.tpl.html', 'publication');

    $themeCustomCSS = 'themes/' . $wiki->config['favorite_theme'] . '/styles/publication.override.css';

    echo $exportTemplate->render(array(
        "baseUrl" => $wiki->getBaseUrl(),
        "content" => $wiki->Format($wiki->page["body"]),
        "siteTitle" => $wiki->GetConfigValue('wakka_name'),
        "title" => $wiki->page['metadatas']['publication-title'],
        "styles" => $wiki->Format("{{linkstyle}}"),
        "themeCustomCSS" => file_exists($themeCustomCSS) ? $themeCustomCSS : '',
        "stylesModifiers" => array(
            "print",
            // could be chosen, when creating an eBook
            "page-format--A4",
            // could be chosen when creating an eBook
            "page-orientation--portrait",
            // could be optional, per eBook
            "hide-links-from-print",
        ),
    ));
}