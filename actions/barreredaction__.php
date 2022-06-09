<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$plugin_output_new = preg_replace('#</div>#', '<a class="link-pdf" href="'.$this->href('pdf').'" title="'._t('PUBLICATION_EXPORT_PAGE_TO_PDF').'"><i class="glyphicon glyphicon-book"></i> '. _t('PUBLICATION_DOWNLOAD_PDF') .'</a>'."\n".'</div>', $plugin_output_new);
