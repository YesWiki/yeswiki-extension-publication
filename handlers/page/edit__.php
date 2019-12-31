<?php

if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

// If the page is a publication, we will display the publication generator
// if ($this->HasAccess('write') && isset($this->page['metadatas']['publication-title'])) {
// 	$publicationEditionPage = $this->Format('{{publicationgenerator}}');
// 	$plugin_output_new = preg_replace ('/(<div class="page">.*<hr class="hr_clear" \/>)/Uis',
//     '<div class="page">'."\n".$publicationEditionPage."\n".'<hr class="hr_clear" />', $plugin_output_new);
// }
