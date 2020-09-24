<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

require __DIR__ . '/vendor/autoload.php';

$wakkaConfig = array_merge(array(
  // if you have installed chromium one your server, indicate the path (defaults to debian's path)
  'htmltopdf_path' => '/usr/bin/chromium', // on MacOs : '/Applications/Chromium.app/Contents/MacOS/Chromium'

  // or if you can't install on your server, you can use a service url (that should authorize your domain first)
  'htmltopdf_service_url' => NULL, // should be a complete url like 'https://example.org/yeswiki/?PagePrincipale/pdf'

  // options for chromium
  'htmltopdf_options' => array(
    'windowSize' =>   ['1440', '780'],
    'headless' =>     true,
    'noSandbox' =>    true,
    'debugLogger' =>  isset($_GET['publication-debug']) && $_GET['print-debug'] === 'browser' ? 'php://stdout' : null,
    'ignoreCertificateErrors' => true,
    'customFlags' =>  isset($_GET['publication-debug']) && $_GET['print-debug'] === 'console' ? ['--enable-logging=stderr', '--v=1'] : null
  ),

  // if you want to propose this website as a service for other domains
  'htmltopdf_service_authorized_domains' => [], // should be an array of domains like ['example.org', 'another-one.com']

  // local cache duration for generated pdf, in seconds (use refresh=1 as GET parameer in url to force the refresh)
  'htmltopdf_cache_life' => '300',

), $wakkaConfig);
