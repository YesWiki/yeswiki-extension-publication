<?php
global $wiki;

if ($this->HasAccess('read') && isset($this->page['metadatas']['publication-title'])) {
    $wiki->AddCSSFile('tools/publication/presentation/styles/publication.css');
}
