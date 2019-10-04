<?php
$field = $this->getParameter('field');
if (!empty($field)) {
    $w = str_replace('/iframe', '', $_GET['wiki']);
    $page = $this->LoadPage($w);
    $re = '/\{\{include\s*page="(.+)".*\}\}/mU';
    $matches = [];
    preg_match_all($re, $page['body'], $matches);
    $contributors = [];
    foreach($matches[1] as $f) {
        $p = $this->LoadPage($f);
        $re = '/"bf_nom":"(.*)"/mU';
        $name = [];
        preg_match_all($re, $p['body'], $name);
        if (!empty($name[1][0])) {
            $v = ucwords(strtolower(trim(json_decode('"'.$name[1][0].'"'))));
            $v = str_replace(' Et ', ' et ', $v);
            $contributors[$v] = $v;
        }
    }
    if (!empty($contributors)) {
        ksort($contributors);
        echo '<ol><li>';
        echo  implode('</li><li>', $contributors);
        echo '</li></ol>';
    }
}