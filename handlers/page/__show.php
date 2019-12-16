<?php
global $wiki;

$wiki->AddCSSFile('tools/ebook/presentation/styles/ebook.css');
$wiki->page['body'] = '{{button link="'.$wiki->href('pdf', '').'" text="PDF" class="btn-primary pull-right space-left" icon="fas fa-book"}} {{button link="'.$wiki->href('preview', '').'" text="Aperçu" class="btn-info pull-right" icon="fas fa-book-reader"}}""<div class="clearfix"></div>""'.$wiki->page['body'];