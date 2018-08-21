<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$wakkaConfig['wkhtmltopdf_path'] = isset($wakkaConfig['wkhtmltopdf_path']) ?
    $wakkaConfig['wkhtmltopdf_path']
    : '/usr/local/bin/wkhtmltopdf';
