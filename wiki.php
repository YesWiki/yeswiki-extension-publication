<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

require __DIR__ . '/vendor/autoload.php';

$wakkaConfig = array_merge(array(
  'htmltopdf_key' =>        NULL,
  'htmltopdf_url' =>        NULL,
  'htmltopdf_path' =>       '/usr/bin/chromium', // on MacOs /Applications/Chromium.app/Contents/MacOS/Chromium
  'htmltopdf_options' => array(
    'windowSize' =>   ['1440', '780'],
    'noSandbox' =>    true,
    // 'debugLogger' =>  'php://stdout',
  )), $wakkaConfig);
