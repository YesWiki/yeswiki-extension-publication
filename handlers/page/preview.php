<?php

global $wiki;

if ($wiki->HasAccess('read') && isset($wiki->page['metadatas']['publication-title'])) {
    include_once 'includes/squelettephp.class.php';
    $exportTemplate = new SquelettePhp('print-preview.tpl.html', 'publication');

    echo $exportTemplate->render(array(
        "baseUrl" => $wiki->getBaseUrl(),
        "content" => $wiki->Format($wiki->page["body"]),
        "siteTitle" => $wiki->GetConfigValue('wakka_name'),
        "title" => $wiki->page['metadatas']['publication-title'],
        "styles" => $wiki->Format("{{linkstyle}}"),
        "stylesModifiers" => array(
            "print",
            "page-format--A4",
            "page-orientation--portrait"
        ),
    ));
}