<?php

use YesWiki\Publication\Service\Publication;

global $wiki;

if ($this->HasAccess('read') && (isset($this->page['metadatas']['publication-title']) || isset($this->page['metadatas']['publication']['title']))) {
    $publicationService = $this->services->get(Publication::class);
    $metadata = $publicationService->getOptions($wiki->page['metadatas']);

    $output = $wiki->render('@publication/show.twig', [
      'hasWriteAccess' => $wiki->HasAccess('write'),
      'hasDeleteAccess' => $wiki->UserIsAdmin() || $wiki->UserIsOwner(),
      'metadata' => $metadata,
      'page' => $wiki->page
    ]);

    $plugin_output_new = preg_replace('#<div class="page".+<hr class="hr_clear" />#siU', '<div class="page" >'. $output .'<hr class="hr_clear" />', $plugin_output_new);
}
