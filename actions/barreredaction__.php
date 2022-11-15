<?php

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$plugin_output_new = preg_replace('#</div>#', $this->render('@bazar/entries/_publication_button.twig', ['forPage'=>true])."\n".'</div>', $plugin_output_new);