<?php
/*

Copyright 2014 Outils-Réseaux

@license GNU GPL 2
@author Florian SCHMITT

*/

// Verification de securite
if (!defined("WIKINI_VERSION"))
{
    die ("acc&egrave;s direct interdit");
}


$dir = getcwd();
$url = str_replace(array('/wakka.php?wiki=', '/?'), '', $this->config['base_url']);
$dlFilename = str_replace(
    array('http://', 'https://', 'www.', '/', '?'),
    array('', '', '', '-', ''),
    $url
).'-'.$this->GetPageTag().".pdf";
$fullFilename = $dir."/cache/".$dlFilename;
$sourceurl = $this->href('iframe', $this->GetPageTag(), 'share=0&edit=0', false);

if (!file_exists($fullFilename) || (file_exists($fullFilename) && isset($_GET['refresh']) && $_GET['refresh']==1)) {
    $command = $this->config['wkhtmltopdf_path']." '".$sourceurl."' ".$fullFilename;
    //echo $command;
    exec($command, $output); 
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
    echo $this->Header()."\n".'<div class="alert alert-danger alert-error">'._t('NO_GENERATED_PDF_FILE_FOUND').'</div>'."\n".$this->Footer()."\n";
}
