<?php
/*

Copyright 2014 Outils-Réseaux

@license GNU GPL 2
@author Florian SCHMITT

*/

use YesWiki\Publication\Service\PdfHelper;

// Verification de securite
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

ob_start(); // to catch all message that could be put in first lines of pdf file
if (!is_executable($this->config['htmltopdf_path']) && empty($this->config['htmltopdf_service_url'])) {
    echo $this->Header()."\n";
    echo '<div class="alert alert-danger alert-error">'
    ._t('PUBLICATION_NO_EXECUTABLE_FILE_FOUND_ON_PATH').' : '
    .$this->config['htmltopdf_path'].'<br />'
    ._t('PUBLICATION_DID_YOU_INSTALL_CHROMIUM_OR_SET_UP_PATH')
    .'.</div>'."\n";
    echo $this->Footer()."\n";
    exit(1);
}

if (!empty($_GET['url']) && !in_array(parse_url($_SERVER['HTTP_REFERER']??'', PHP_URL_HOST), $this->config['htmltopdf_service_authorized_domains'])) {
    echo $this->Header()."\n";
    echo '<div class="alert alert-danger alert-error">'._t('PUBLICATION_DOMAIN_NOT_AUTORIZED').' : '.$domain.'</div>'."\n";
    echo $this->Footer()."\n";
    exit(1);
}

$url = str_replace(array('/wakka.php?wiki=', '/?'), '', $this->config['base_url']);
$pagedjs_hash = sha1(json_encode(array_merge([
  file_get_contents(__DIR__ . '/../../presentation/browser/print.js'),
  file_get_contents(__DIR__ . '/../../libs/vendor/pagedjs/paged.esm.js')
])));

if (!empty($_GET['url'])) {
    $pageTag = isset($_GET['urlPageTag']) ? $_GET['urlPageTag'] : 'publication';
    $sourceUrl = $_GET['url'];
    $hash = substr(sha1($pagedjs_hash . strtolower($_SERVER['QUERY_STRING'])), 0, 10);
} else {
    $pageTag = $this->GetPageTag();
    $pdfTag = $this->MiniHref('pdf', $pageTag);
    $sourceUrl = $this->href('preview', $pageTag, preg_replace('#^'. $pdfTag .'&?#', '', $_SERVER['QUERY_STRING']), false);

    $pdfHelper = $this->services->get(PdfHelper::class);

    $hash = substr(sha1($pagedjs_hash . json_encode(array_merge(
        $this->page,
        ['query_string' => strtolower($_SERVER['QUERY_STRING']),
        $pdfHelper->getPageEntriesContent(
            $pageTag,
            $_GET['via'] ?? null
        ) ?? []]
    ))), 0, 10);

    // In case we are behind a proxy (like a Docker container)
    // It allows us to properly load the document from within the container itself
    if ($this->config['htmltopdf_base_url']) {
        $sourceUrl = str_replace($this->config['base_url'], $this->config['htmltopdf_base_url'], $sourceUrl);
    }
}

$dlFilename = sprintf(
    '%s-%s.pdf',
    $pageTag,
    $hash
);

$dirname = sys_get_temp_dir()."/yeswiki/";
if (!file_exists($dirname)) {
    mkdir($dirname);
}
$fullFilename = sprintf(
    '%s/yeswiki/%s-%s-%s.pdf',
    sys_get_temp_dir(),
    $pageTag,
    'publication',
    $hash
);


$file_exists = file_exists($fullFilename);
$output = array();

if (($this->UserIsAdmin() && isset($_GET['print-debug']))
|| !$file_exists
|| ($file_exists && isset($_GET['refresh']) && $_GET['refresh']==1)) {
    if (!empty($this->config['htmltopdf_service_url'])) {
        $url = $this->config['htmltopdf_service_url'].'&urlPageTag='.$this->GetPageTag().'&url='.urlencode($sourceUrl).'&hash='.$hash;
        header('Location: '.$url);
        exit;
    } else {
        try {
            $browserFactory = new HeadlessChromium\BrowserFactory($this->config['htmltopdf_path']);
            $browser = $browserFactory->createBrowser($this->config['htmltopdf_options']);

            $page = $browser->createPage();
            $page->navigate($sourceUrl)->waitForNavigation(HeadlessChromium\Page::NETWORK_IDLE);

            $value = $page->evaluate('__is_yw_publication_ready()')->getReturnValue($this->config['page_load_timeout']);

            // now generate PDF
            $page->pdf(array(
              'printBackground' => true,
              'displayHeaderFooter' => true,
              'preferCSSPageSize' => true
            ))->saveToFile($fullFilename);

            $browser->close();
        } catch (Exception $e) {
            echo $this->Header()."\n";
            echo '<div class="alert alert-info">'.$sourceUrl.'</div>'."\n";
            echo '<div class="alert alert-danger alert-error">'.$e->getMessage().'</div>'."\n";

            if (($e instanceof HeadlessChromium\Exception\OperationTimedOut) === false) {
                $html = $page->evaluate('document.documentElement.innerHTML')->getReturnValue();
                echo '<pre><code lang="html">'. htmlentities($html) .'</code></pre>';
            }

            echo $this->Footer()."\n";

            $browser->close();
            exit(1);
        }
    }
}

if (file_exists($fullFilename)) {
    ob_end_clean();
    $size = filesize($fullFilename);
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    header("Content-type: application/force-download");
    header('Pragma: public');
    header("Pragma: no-cache");// HTTP/1.0
    header('Last-Modified: '.gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: no-store, no-cache, must-revalidate'); // HTTP/1.1
    header('Cache-Control: pre-check=0, post-check=0, max-age=0'); // HTTP/1.1
    header('Content-Transfer-Encoding: none');
    header('Content-Type: application/octet-stream; name="' . $dlFilename . '"'); //This should work for the rest
    header('Content-Type: application/octetstream; name="' . $dlFilename . '"'); //This should work for IE & Opera
    header('Content-Type: application/download; name="' . $dlFilename . '"'); //This should work for IE & Opera
    header('Content-Disposition: attachment; filename="'.$dlFilename.'"');
    header("Content-Description: File Transfer");
    header("Content-length: $size");
    readfile($fullFilename);
    exit();
} else {
    ob_end_flush();
    echo $this->Header()."\n";
    echo '<div class="alert alert-danger alert-error">'._t('PUBLICATION_NO_GENERATED_PDF_FILE_FOUND').'</div>'."\n";
    if (count($output) > 0) {
        echo implode('<br>', $output);
    }
    echo $this->Footer()."\n";
}
