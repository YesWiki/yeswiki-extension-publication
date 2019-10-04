<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5                                                                                        |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2012 Florian Schmitt <florian@outils-reseaux.org>                                      |
// +------------------------------------------------------------------------------------------------------+
// | This library is free software; you can redistribute it and/or                                        |
// | modify it under the terms of the GNU Lesser General Public                                           |
// | License as published by the Free Software Foundation; either                                         |
// | version 2.1 of the License, or (at your option) any later version.                                   |
// |                                                                                                      |
// | This library is distributed in the hope that it will be useful,                                      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU                                    |
// | Lesser General Public License for more details.                                                      |
// |                                                                                                      |
// | You should have received a copy of the GNU Lesser General Public                                     |
// | License along with this library; if not, write to the Free Software                                  |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
// +------------------------------------------------------------------------------------------------------+
//
/**
 *
 * Export de toutes les pages en derniere version, pour creer une pageWiki ebook et son pdf
 *
 *
 *@package ebook
 *
 *@author        Florian Schmitt <florian@outils-reseaux.org>
 *
 *@copyright     Outils-Reseaux 2012
 *@version       $Revision: 0.1 $ $Date: 2010/03/04 14:19:03 $
 */

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

include_once 'tools/tags/libs/tags.functions.php';

// Pages used for intro and outro
$ebookstart = $this->getParameter('ebookstart');
$ebookend = $this->getParameter('ebookend');

// prefix for created pages
$ebookpagenamestart = $this->getParameter('ebookpagenamestart');
if (empty($ebookpagenamestart)) {
    $ebookpagenamestart = 'Ebook';
}

// include defaults pages in page listing ?
$addinstalledpage = $this->getParameter('addinstalledpage');

// default ebook cover
$default = [];
$default['coverimage'] = $this->getParameter('coverimage');

// default ebook title
$default['title'] = $this->getParameter('title');

// default ebook description
$default['desc'] = $this->getParameter('desc');

// default ebook author
$default['author'] = $this->getParameter('author');

// default added pages that can be used to separate content
$chapterpages = $this->getParameter('chapterpages');
if (!$chapterpages) {
    $chapterpages = [];
} else {
    $chapterpages = explode(',', $chapterpages);
    $chapterpages = array_map('trim', $chapterpages);
    $a = [];
    foreach ($chapterpages as $page) {
        $a[$page] = $this->loadPage($page);
    }
    $chapterpages = $a;
}

// app display template
$template = $this->getParameter('template');
if (empty($template)) {
    $template = 'exportpages_table.tpl.html';
}

// titles for groups
$titles = $this->getParameter('titles');
if (!empty($titles)) {
    $titles = explode(',', $titles);
    $titles = array_map('trim', $titles);
    ;
} else {
    $titles = [];
}

// groups of pages or bazar entries
$id = $this->getParameter('id');
if (!empty($id)) {
    $matches = [];
    preg_match_all('/(\d+|pages)(\(([^\(\)]*)\))?/m', $id, $matches);
    $id = explode(',', $id);
    $id = array_map('trim', $id);
    $results = $queries = [];
    
    foreach ($matches[1] as $i => $formid) {
        // bazar entries
        if ($formid != 'pages') {
            $results[$i]['type'] = 'bazar';
            $formValues = baz_valeurs_formulaire($formid);
            if (!empty($titles[$i])) {
                $results[$i]['name'] = $titles[$i];
            } else {
                $results[$i]['name'] = $formValues['bn_label_nature'];
            }
            $tabquery = [];
            if (isset($matches[3][$i])) {
                $tab = explode('|', $matches[3][$i]); //découpe la requete autour des |
                foreach ($tab as $req) {
                    $tabdecoup = explode('=', $req, 2);
                    if (isset($tabquery[$tabdecoup[0]]) && !empty($tabquery[$tabdecoup[0]])) {
                        $tabquery[$tabdecoup[0]] = $tabquery[$tabdecoup[0]].','.trim($tabdecoup[1]);
                    } else {
                        $tabquery[$tabdecoup[0]] = !empty($tabdecoup[1]) ? trim($tabdecoup[1]) : '';
                    }
                }
            }
            $results[$i]['entries'] = baz_requete_recherche_fiches($tabquery, 'alphabetique', $formid, '', 1, '', '', true, '');
            $results[$i]['entries'] = searchResultstoArray($results[$i]['entries'], array(), $formValues);
            // tri des fiches
            $GLOBALS['ordre'] = 'asc';
            $GLOBALS['champ'] = 'bf_titre';
            usort($results[$i]['entries'], 'champCompare');
        } else {
            $results[$i]['type'] = 'pages';
            if (!empty($titles[$i])) {
                $results[$i]['name'] = $titles[$i];
            } else {
                $results[$i]['name'] = 'Pages wikis';
            }
            if (!empty($matches[3][$i])) {
                $tags = explode(',', $matches[3][$i]);
                $tags = array_map('trim', $tags);
                $taglist = '"'.implode('","', $tags).'"';
            } else {
                $taglist = '';
            }
            // wiki pages
            $sql = 'SELECT DISTINCT tag,body FROM ' . $this->GetConfigValue('table_prefix') . 'pages';
            if (!empty($taglist)) {
                $sql .= ', ' . $this->config['table_prefix'] . 'triples tags';
            }
            $sql .= ' WHERE latest="Y"
                        AND comment_on="" AND tag NOT LIKE "LogDesActionsAdministratives%" ';
            // don't select bazar entries and lists
            $sql .= ' AND tag NOT IN (SELECT resource FROM ' . $this->GetConfigValue('table_prefix') . 'triples WHERE property="http://outils-reseaux.org/_vocabulary/type") ';

            if (!empty($taglist)) {
                $sql .= ' AND tags.value IN (' . $taglist . ') AND tags.property = "http://outils-reseaux.org/_vocabulary/tag" AND tags.resource = tag';
            }

            $sql .= ' ORDER BY tag ASC';
            $results[$i]['entries'] = $this->LoadAll($sql);
        }
    }
} else {
    // we take everything if nothing is specified
    $result = [];
    // wiki pages
    $results[0]['type'] = 'pages';
    $results[0]['name'] = 'Pages wikis';
    $sql = 'SELECT DISTINCT tag,body FROM ' . $this->GetConfigValue('table_prefix') . 'pages';
    if (!empty($taglist)) {
        $sql .= ', ' . $this->config['table_prefix'] . 'triples tags';
    }
    $sql .= ' WHERE latest="Y"
                AND comment_on="" AND tag NOT LIKE "LogDesActionsAdministratives%" ';
    // don't select bazar entries and lists
    $sql .= ' AND tag NOT IN (SELECT resource FROM ' . $this->GetConfigValue('table_prefix') . 'triples WHERE property="http://outils-reseaux.org/_vocabulary/type") ';
    $sql .= ' ORDER BY tag ASC';
    $results[0]['entries'] = $this->LoadAll($sql);

    // bazar entries
    $results[1]['type'] = 'bazar';
    $results[1]['name'] = 'Fiches bazar';
    $results[1]['entries'] = baz_requete_recherche_fiches('', 'alphabetique', '', '', 1, '', '', true, '');
    $results[1]['entries'] = searchResultstoArray($results[1]['entries'], array());
    $GLOBALS['ordre'] = 'asc';
    $GLOBALS['champ'] = 'bf_titre';
    usort($results[1]['entries'], 'champCompare');
}

$output = '';

if (isset($_POST["page"])) {
    if (isset($_POST['antispam']) && $_POST['antispam'] == 1) {
        if (isset($_POST["ebook-title"]) && $_POST["ebook-title"] != '') {
            if (isset($_POST["ebook-description"]) && $_POST["ebook-description"] != '') {
                if (isset($_POST["ebook-author"]) && $_POST["ebook-author"] != '') {
                    if (isset($_POST["ebook-cover-image"]) && $_POST["ebook-cover-image"] != '') {
                        if (preg_match("/.(jpe?g)$/i", $_POST["ebook-cover-image"]) == 1) {
                            if (isset($ebookpagename) && !empty($ebookpagename)) {
                                $pagename = $ebookpagename;
                            } else {
                                $pagename = generatePageName($ebookpagenamestart . ' ' . $_POST["ebook-title"]);
                            }
                            foreach ($_POST["page"] as $page) {
                                $output .= '{{include page="' . $page . '" class=""}}' . "\n";
                            }
                            $output .= '//' . _t('TAGS_CONTENT_VISIBLE_ONLINE_FROM_PAGE') . ' : ' . $this->href('', $pagename) . ' // {{button link="' . $this->href('pdf', $pagename) . '" text="' . _t('TAGS_DOWNLOAD_PDF') . '" class="btn-primary pull-right" icon="book"}}' . "\n";

                            unset($_POST['page']);
                            unset($_POST['antispam']);
                            $this->SaveMetaDatas($pagename, $_POST);
                            $this->SavePage($pagename, $output);
                            $output = $this->Format('""<div class="alert alert-success">' . _t('TAGS_EBOOK_PAGE_CREATED') . ' !""' . "\n" . '{{button class="btn-primary" link="' . $pagename . '" text="' . _t('TAGS_GOTO_EBOOK_PAGE') . ' ' . $pagename . '"}}""</div>""' . "\n");
                        } else {
                            $output = '<div class="alert alert-danger">' . _t('TAGS_NOT_IMAGE_FILE') . '</div>' . "\n";
                        }
                    } else {
                        $output = '<div class="alert alert-danger">' . _t('TAGS_NO_IMAGE_FOUND') . '</div>' . "\n";
                    }
                } else {
                    $output = '<div class="alert alert-danger">' . _t('TAGS_NO_AUTHOR_FOUND') . '</div>' . "\n";
                }
            } else {
                $output = '<div class="alert alert-danger">' . _t('TAGS_NO_DESC_FOUND') . '</div>' . "\n";
            }
        } else {
            $output = '<div class="alert alert-danger">' . _t('TAGS_NO_TITLE_FOUND') . '</div>' . "\n";
        }
    } else {
        $output = '<div class="alert alert-danger">' . _t('TAGS_SPAM_RISK') . '</div>' . "\n";
    }
} else {
    // recuperation des pages creees a l'installation
    $d = dir("setup/doc/");
    while ($doc = $d->read()) {
        if ($doc == '.' || $doc == '..' || is_dir($doc) || substr($doc, -4) != '.txt') {
            continue;
        }

        if ($doc == '_root_page.txt') {
            $installpagename[$this->GetConfigValue("root_page")] = $this->GetConfigValue("root_page");
        } else {
            $pagename = substr($doc, 0, strpos($doc, '.txt'));
            $installpagename[$pagename] = $pagename;
        }
    }

    if (isset($this->page["metadatas"]["ebook-title"])) {
        $ebookpagename = $this->GetPageTag();
        preg_match_all('/{{include page="(.*)".*}}/Ui', $this->page['body'], $matches);
        $ebookstart = $matches[1][0];
        $last = count($matches[1]) - 1;
        $ebookend = $matches[1][$last];
        unset($matches[1][0]);
        unset($matches[1][$last]);
        foreach ($matches[1] as $key => $value) {
            $pagesfiltre = filter_by_value($results, 'tag', $value);
            $selectedpages[] = array_shift($pagesfiltre);
            $key = array_keys($pagesfiltre);
            if ($key && isset($pages[$key[0]])) {
                unset($pages[$key[0]]);
            }
        }
    } else {
        $ebookpagename = '';
        $selectedpages = array();
    }

    include_once 'includes/squelettephp.class.php';
    $template_export = new SquelettePhp($template, 'ebook');
    
    $output .= $template_export->render(
        array(
            'entries' => $results,
            'ebookstart' => $ebookstart,
            'ebookend' => $ebookend,
            'addinstalledpage' => $addinstalledpage,
            'installedpages' => $installpagename,
            'default' => $default,
            'ebookpagename' => $ebookpagename,
            'metadatas' => $this->page["metadatas"],
            'selectedpages' => $selectedpages,
            'chapterpages' => $chapterpages,
            'url' => $this->href('', $this->GetPageTag())
        )
    );
}

echo $output . "\n";
