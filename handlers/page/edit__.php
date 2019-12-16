<?php

if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

// If the page is an ebook, we will display the ebook generator 
// TODO make it work
// if ($this->HasAccess('write') && isset($this->page['metadatas']['ebook-title'])) {
// 	$pageeditionebook = $this->Format('{{ebookgenerator}}');
// 	$plugin_output_new = preg_replace ('/(<div class="page">.*<hr class="hr_clear" \/>)/Uis',
//     '<div class="page">'."\n".$pageeditionebook."\n".'<hr class="hr_clear" />', $plugin_output_new);
// }