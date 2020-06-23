<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$plugin_output_new = preg_replace('#</div>#', '<a class="link-pdf" href="'.$this->href('pdf').'" title="'._t('PUBLICATION_EXPORT_PAGE_TO_PDF').'"><i class="glyphicon glyphicon-book"></i> PDF (print)</a>'.'<a class="link-pdf" href="'.$this->href('pdf', $this->GetPageTag(), 'output_like_screen=1', false).'" title="'._t('PUBLICATION_EXPORT_PAGE_TO_PDF').'"><i class="glyphicon glyphicon-book"></i> PDF (screen)</a>'."\n".'</div>', $plugin_output_new);
