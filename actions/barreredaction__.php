<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$plugin_output_new = preg_replace('#</div>#', '<a class="link-pdf" href="'.$this->href('pdf').'" title="'._t('PUBLICATION_EXPORT_PAGE_TO_PDF').'" onclick="toastMessage(_t(\'PUBLICATION_PDF_GENERATION_LANCHED\'),7000,\'alert alert-primary\');"><i class="glyphicon glyphicon-book"></i> '. _t('PUBLICATION_EXPORT_PAGE_TO_PDF') .'</a>'."\n".'</div>', $plugin_output_new);
