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
  'class' => $wiki->getParameter('class'),
  'templatepage' => $wiki->getParameter('templatepage'),
);

$publicationTemplate = $wiki->getParameter('templatepage') ? $wiki->loadPage($wiki->getParameter('templatepage')) : null;

$template = new SquelettePhp('bazar2publication.tpl.html', 'publication');

$queries = $_GET;
//remove wiki
if (array_key_exists('wiki', $queries)) {
    unset($queries['wiki']);
}
$href = $wiki->Href('pdf', null, $queries// merge GET with wiki and following params
  +array(
    'via' => 'bazarliste',
    'template-page' => $wiki->getParameter('templatepage'),
));

echo $template->render(array(
  'href' => $href,
  'options' => $options,
  'templatePage' => $publicationTemplate,
));
