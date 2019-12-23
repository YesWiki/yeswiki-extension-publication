<?php
global $wiki;
if ($this->HasAccess('write') && isset($this->page['metadatas']['publication-title'])) {
    $wiki->AddCSSFile('tools/publication/presentation/styles/publication.css');
    $wiki->page['body'] = '{{button link="'.$wiki->href('pdf', '').'" text="PDF" class="btn-primary pull-right space-left" icon="fas fa-book"}} {{button link="'.$wiki->href('preview', '').'" text="'._t('PUBLICATION_PREVIEW').'" class="btn-info pull-right" icon="fas fa-book-reader"}}""<div class="clearfix"></div>""'.$wiki->page['body'];
}
