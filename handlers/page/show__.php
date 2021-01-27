<?php
global $wiki;

if ($this->HasAccess('read') && isset($this->page['metadatas']['publication-title'])) {
    include_once 'includes/squelettephp.class.php';

    // user  options
    $options = array(
        "publication-hide-links-url" => '1',
        "publication-cover-page" => '0',
        "publication-book-fold" => '0',
        "publication-page-format" => 'A4',
        "publication-page-orientation" => 'portrait',
        "publication-pagination" => "bottom-center",
        "publication-print-marks" => '0'
    );

    $metadata = array_merge($options, $wiki->page['metadatas']);
    $template = new SquelettePhp('print-show.tpl.html', 'publication');

    $output = $template->render(array(
      'hasWriteAccess' => $wiki->HasAccess('write'),
      'hasDeleteAccess' => $wiki->UserIsAdmin() || $wiki->UserIsOwner(),
      'metadata' => $metadata,
      'page' => $wiki->page,
      'wiki' => $wiki,
    ));

    $plugin_output_new = preg_replace('/<div class="page" >.+<hr class="hr_clear" \/>/siU', '<div class="page" >'. $output .'<hr class="hr_clear" />', $plugin_output_new);
}
