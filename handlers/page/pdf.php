<?php
/*

Copyright 2014 Outils-Réseaux

@license GNU GPL 2
@author Florian SCHMITT

*/

// Verification de securite
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

if (is_executable($this->config['htmltopdf_path'])) {
    $dir = getcwd();
    $url = str_replace(array('/wakka.php?wiki=', '/?'), '', $this->config['base_url']);
    $dlFilename = str_replace(
        array('http://', 'https://', 'www.', '/', '?'),
        array('', '', '', '-', ''),
        $url
    ).'-'.$this->GetPageTag().".pdf";
    $fullFilename = $dir."/cache/".$dlFilename;
    if (!empty($_GET['url'])) {
        $domain = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        if (in_array($domain, $this->config['htmltopdf_service_authorized_domains'])) {
            $sourceUrl = $_GET['url'];
            $_GET['refresh']=1; //for external pdfs, no cache
            $fullFilename = '/tmp/page.pdf';
            $dlFilename  = 'page.pdf';
        } else {
            echo $this->Header()."\n";
            echo '<div class="alert alert-danger alert-error">'._t('PUBLICATION_DOMAIN_NOT_AUTORIZED').' : '.$domain.'</div>'."\n";
            echo $this->Footer()."\n";
            exit;
        }
    } else {
        $pdfTag = $this->MiniHref('pdf', $this->GetPageTag());
        $sourceUrl = $this->href('preview', $this->GetPageTag(), preg_replace('#^'. $pdfTag .'&#U', '', $_SERVER['QUERY_STRING']), false);
      }

    $cache_life = '300'; //caching time, in seconds
    $file_exists = file_exists($fullFilename);
    $fileLastModifiedTime = $file_exists ? @filemtime($fullFilename) : 0;  // returns FALSE if file does not exist
    $output = array();

    if (($this->UserIsAdmin() && isset($_GET['print-debug']))
    || !$file_exists
    || ($file_exists && isset($_GET['refresh']) && $_GET['refresh']==1)
    || ($file_exists && (time() - $fileLastModifiedTime >= $this->config['htmltopdf_cache_life']))
    ) {
        if (!empty($this->config['htmltopdf_service_url'])) {
            $url = $this->config['htmltopdf_service_url'].'&url='.urlencode($sourceUrl);
            header('Location: '.$url);
            exit;
        } else {
            $browserFactory = new HeadlessChromium\BrowserFactory($this->config['htmltopdf_path']);
            $browser = $browserFactory->createBrowser($this->config['htmltopdf_options']);
            $page = $browser->createPage();

            $page->navigate($sourceUrl)->waitForNavigation(HeadlessChromium\Page::NETWORK_IDLE);

            // now generate PDF
            $page->pdf()->saveToFile($fullFilename);
            $browser->close();
        }
    }

    if (file_exists($fullFilename)) {
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
    } else {
        echo $this->Header()."\n";
        echo '<div class="alert alert-danger alert-error">'._t('PUBLICATION_NO_GENERATED_PDF_FILE_FOUND').'</div>'."\n";
        if (count($output) > 0) {
            echo implode('<br>', $output);
        }
        echo $this->Footer()."\n";
    }

} else {
    echo $this->Header()."\n";
    echo '<div class="alert alert-danger alert-error">'
      ._t('PUBLICATION_NO_EXECUTABLE_FILE_FOUND_ON_PATH').' : '
      .$this->config['htmltopdf_path'].'<br />'
      ._t('PUBLICATION_DID_YOU_INSTALL_CHROMIUM_OR_SET_UP_PATH')
      .'.</div>'."\n";
    echo $this->Footer()."\n";
}

