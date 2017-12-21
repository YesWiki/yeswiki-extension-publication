<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$plugin_output_new = preg_replace('#</div>#', '<a class="link-pdf" href="'.$this->href('pdf').'" title="'._t('EXPORT_PAGE_TO_PDF').'"><i class="glyphicon glyphicon-book"></i> PDF</a>'."\n".'</div>', $plugin_output_new);
