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


$dir = getcwd();
$url = str_replace(array('/wakka.php?wiki=', '/?'), '', $this->config['base_url']);
$dlFilename = str_replace(
    array('http://', 'https://', 'www.', '/', '?'),
    array('', '', '', '-', ''),
    $url
).'-'.$this->GetPageTag().".pdf";
$fullFilename = $dir."/cache/".$dlFilename;
if (!empty($this->config['wkhtmltopdf_apikey']) and !empty($_GET['url']) and !empty($_GET['key']) and $this->config['wkhtmltopdf_apikey'] == $_GET['key']) {
    $sourceurl = $_GET['url'];
    $_GET['refresh']=1;
    $fullFilename = '/tmp/page.pdf';
} else {
    $sourceurl = $this->href('iframe', $this->GetPageTag(), 'share=0&edit=0', false);
}

$cache_life = '600'; //caching time, in seconds
$filemtime = @filemtime($fullFilename);  // returns FALSE if file does not exist
$command = '';
$output = array();

if (!file_exists($fullFilename)
  || (file_exists($fullFilename) && isset($_GET['refresh']) && $_GET['refresh']==1)
  || (file_exists($fullFilename) && (time() - $filemtime >= $cache_life))
) {
    if (!empty($this->config['wkhtmltopdf_url']) and !empty($this->config['wkhtmltopdf_key'])) {
        $url = $this->config['wkhtmltopdf_url'].'&url='.urlencode($sourceurl).'&key='.urlencode($this->config['wkhtmltopdf_key']);
        header('Location: '.$url);
        exit;
    } else {
        $command = $this->config['wkhtmltopdf_path']." '".$sourceurl."' ".$fullFilename;
        exec($command, $output);
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
    echo '<div class="alert alert-danger alert-error">'._t('NO_GENERATED_PDF_FILE_FOUND').'</div>'."\n";
    if (!empty($command)) {
        echo $command.'<br>';
    }
    if (count($output) > 0) {
        echo implode('<br>', $output);
    }
    echo $this->Footer()."\n";
}
