<?php
global $wiki;

if ($this->HasAccess('write') && isset($this->page['metadatas']['publication-title'])) {
    $wiki->AddCSSFile('tools/publication/presentation/styles/publication.css');
}
