<?php

namespace YesWiki\Publication;

use YesWiki\Core\YesWikiAction;

class Bazar2PublicationAction extends YesWikiAction
{
    public function formatArguments($args)
    {
        return [
            'title' => $args['title'] ?? _t('PUBLICATION_CREATE_FROM_BAZAR_RESULTS'),
            'icon' => $args['icon'] ?? 'fa-book',
            'class' => $args['class'] ?? '',
            'templatepage' => $args['templatepage'] ?? '',
            'excludedfields' => $this->formatArray($args['excludedfields'] ?? []),
        ];
    }

    public function run()
    {
        $publicationTemplate = !empty($this->arguments['templatepage'])
            ? $this->wiki->loadPage($this->arguments['templatepage'])
            : null;

        $queries = $_GET;
        //remove wiki
        if (array_key_exists('wiki', $queries)) {
            unset($queries['wiki']);
        }
        $href = $this->wiki->Href('pdf', null, $queries// merge GET with wiki and following params
            +[
              'via' => 'bazarliste',
              'template-page' => $this->arguments['templatepage'],
          ]+(
              empty($this->arguments['excludedfields'])
              ? []
              : [
                'excludeFields' => implode(',', $this->arguments['excludedfields'])
              ]
          ));

        return $this->render('@publication/bazar2publication.twig', [
            'href' => $href,
            'options' => $this->arguments,
            'templatePage' => $publicationTemplate,
          ]);
    }
}
