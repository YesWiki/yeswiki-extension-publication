<?php

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

global $wiki;

include_once 'includes/squelettephp.class.php';

$wiki->addCssFile('tools/publication/presentation/styles/publication.css');
$wiki->addJavascriptFile('tools/publication/presentation/actions/bazar2publication.js');

$options = array(
  'title' => $wiki->getParameter('title', _t('PUBLICATION_CREATE_FROM_BAZAR_RESULTS')),
  'icon' => $wiki->getParameter('icon', 'fa-book'),
  'class' => $wiki->getParameter('class', ''),
  'ebook' => ($wiki->getParameter('ebook') ? $wiki->loadPage($wiki->getParameter('ebook')) : null),
  'bazarlist-id' => $wiki->getParameter('bazarlist-id', 'bazar-list-1')
);

$template = new SquelettePhp('bazar2publication.tpl.html', 'publication');

echo $template->render(array(
  'options' => $options
));
