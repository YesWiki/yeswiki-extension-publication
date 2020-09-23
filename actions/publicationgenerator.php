<?php
/**
 *
 * Exports all pages /  in their last version to create the publication
 *
 *
 *@package       publication
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

$this->addCssFile('tools/publication/presentation/styles/publication.css');

// Format of the output. Either you want to generate an ebook or a newsletter
// Default value is ebook
$outputFormat = $this->getParameter('outputformat');
if (empty($outputFormat) || $outputFormat != 'newsletter') {
    $name = _t('PUBLICATION_EBOOK');
    $outputFormat = 'Ebook';
} else {
	$formId = $this->getParameter('formid'); // Bazar form used to store the newsletter
	if (empty($formId)) {
		exit (_t('PUBLICATION_MISSING_NEWSLETTER_FORM'));
	} else {
		$name = _t('PUBLICATION_NEWSLETTER');
	}
}

// Indicates if fields and elements are "read only"
$areParamsReadonly = $this->getParameter('fields', 'writeable') === 'readonly';

// Pages used for intro and outro
$publicationStart = $this->getParameter('pagestart');
$publicationEnd = $this->getParameter('pageend');

// prefix for created pages
// Only used when outputformat="ebook"
$ebookPageNamePrefix = $this->getParameter('ebookpagenameprefix');
if (empty($ebookPageNamePrefix)) {
    $ebookPageNamePrefix = 'Ebook';
}

// include default pages in page listing ?
$addInstalledPages = $this->getParameter('addinstalledpage');

// default publication cover
$default = [];
$default['coverimage'] = $this->getParameter('coverimage');

// default publication title
$default['title'] = $this->getParameter('title');

// default publication description
$default['desc'] = $this->getParameter('desc');

// default publication author
$default['author'] = $this->getParameter('author');

// default added pages that can be used to separate content
$chapterCoverPages = $this->getParameter('chapterpages');
if (!$chapterCoverPages) {
    $chapterCoverPages = [];
} else {
    $chapterCoverPages = explode(',', $chapterCoverPages);
    $chapterCoverPages = array_map('trim', $chapterCoverPages);
    $a = [];
    foreach ($chapterCoverPages as $page) {
        $a[$page] = $this->loadPage($page);
    }
    $chapterCoverPages = $a;
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
} else {
    $titles = [];
}

// groups of pages or bazar entries
$groupselector = $this->getParameter('groupselector');
if (!empty($groupselector)) {
    $matches = [];
    preg_match_all('/(\d+|pages)(\(([^\(\)]*)\))?/m', $groupselector, $matches);
    $groupselector = explode(',', $groupselector);
    $v = array_map('trim', $groupselector);
    $results = $queries = [];
    foreach ($matches[1] as $i => $formId) {
        // bazar entries
        if ($formId != 'pages') {
            $results[$i]['type'] = 'bazar';
            $formValues = baz_valeurs_formulaire($formId);
            if (!empty($titles[$i])) {
                $results[$i]['name'] = $titles[$i];
            } else {
                $results[$i]['name'] = $formValues['bn_label_nature'];
            }
            $tabQuery = [];
            if (isset($matches[3][$i])) {
                $tab = explode('|', $matches[3][$i]); // splits the query using |
                foreach ($tab as $req) {
                    $tabdecoup = explode('=', $req, 2);
                    if (isset($tabQuery[$tabdecoup[0]]) && !empty($tabQuery[$tabdecoup[0]])) {
                        $tabQuery[$tabdecoup[0]] = $tabQuery[$tabdecoup[0]].','.trim($tabdecoup[1]);
                    } else {
                        $tabQuery[$tabdecoup[0]] = !empty($tabdecoup[1]) ? trim($tabdecoup[1]) : '';
                    }
                }
            }
            $results[$i]['entries'] = baz_requete_recherche_fiches($tabQuery, 'alphabetique', $formId, '', 1, '', '', true, '');
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
                $tagList = '"'.implode('","', $tags).'"';
            } else {
                $tagList = '';
            }
            // wiki pages
            $sql = 'SELECT DISTINCT tag,body FROM ' . $this->GetConfigValue('table_prefix') . 'pages';
            if (!empty($tagList)) {
                $sql .= ', ' . $this->config['table_prefix'] . 'triples tags';
            }
            $sql .= ' WHERE latest="Y"
                        AND comment_on="" AND tag NOT LIKE "LogDesActionsAdministratives%" ';
            // don't select bazar entries and lists
            $sql .= ' AND tag NOT IN (SELECT resource FROM ' . $this->GetConfigValue('table_prefix') . 'triples WHERE property="http://outils-reseaux.org/_vocabulary/type") ';

            if (!empty($tagList)) {
                $sql .= ' AND tags.value IN (' . $tagList . ') AND tags.property = "http://outils-reseaux.org/_vocabulary/tag" AND tags.resource = tag';
            }

            if ($addInstalledPages) {
                var_dump(implode(',', $installedPageNames));
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
    if (!empty($tagList)) {
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

// Handling of data submitted by the form
// Page creation
if (isset($_POST["page"])) {
	do { // use of a do-while loop in order to allow for breaks (in case of errors)
		if (!isset($_POST['antispam']) || $_POST['antispam'] != 1) {
			// There may be a spamming problem
			$output = '<div class="alert alert-danger">' . _t('PUBLICATION_SPAM_RISK') . '</div>' . "\n";
			break; // Stops the current do-while loop
		}
		if (!isset($_POST["publication-title"]) || $_POST["publication-title"] == '') {
			// There is no publication-title
			$output = '<div class="alert alert-danger">' . _t('PUBLICATION_NO_TITLE_FOUND') . '</div>' . "\n";
			break; // Stops the current do-while loop
        }

		// So far everything is OK
		if ($_POST['outputformat'] == 'Ebook') {  // We want to produce an ebook (default behaviour)
			do { // use of a do-while loop in order to allow for breaks (in case of errors specific to ebooks)
			  if (isset($_POST["publication-cover-image"]) && $_POST["publication-cover-image"] !== '' && preg_match("/.(jpe?g|png|svg)$/i", $_POST["publication-cover-image"]) != 1) {
					// there is no publication-cover-image
					$output = '<div class="alert alert-danger">' . _t('PUBLICATION_NOT_IMAGE_FILE') . '</div>' . "\n";
					break; // Stops the current do-while loop
				}
				// So far everything is OK (regarding ebooks)
				if (isset($ebookPageName) && !empty($ebookPageName)) {
					$pageName = $ebookPageName;
				} else {
					$pageName = generatePageName($ebookPageNamePrefix . ' ' . $_POST["publication-title"]);
        }


        // Generate the content of the page body
        // @todo refactor it to share its logic with newsletter
        foreach ($_POST["page"] as $page) {
          // we turn some actions into explicit content
          // for now, {{blankpage}}, but later maybe some specific handlers like {{publicationcover}}, {{publicationbookend}}
          if (preg_match('#{{\s*blankpage\s*}}#U', $page)) {
            $output .= $page . "\n";
          }
          // we assume it is a page tag otherwise
          // maybe we should also explicitly check it is a valid page tag instead?
          else {
            $output .= '{{include page="' . $page . '" class=""}}' . "\n";
          }
        }


				$output .= '//' . _t('PUBLICATION_CONTENT_VISIBLE_ONLINE_FROM_PAGE') . ' : ' . $this->href('', $pageName) . ' // {{button link="' . $this->href('pdf', $pageName) . '" text="' . _t('PUBLICATION_DOWNLOAD_PDF') . '" class="btn-primary pull-right" icon="book"}}' . "\n";
				unset($_POST['page']);
        unset($_POST['antispam']);

        if ($this->SavePage($pageName, $output) === 0) {
          $this->SaveMetaDatas($pageName, $_POST);
          $output = $this->Format('""<div class="alert alert-success">' . _t('PUBLICATION_EBOOK_PAGE_CREATED') . ' !""' . "\n" . '{{button class="btn-primary" link="' . $pageName . '" text="' . _t('PUBLICATION_GOTO_EBOOK_PAGE') . ' ' . $pageName . '"}}""</div>""' . "\n");
        }
        else {
          $output = $this->Format('""<div class="alert alert-danger alert-error">' . _t('PUBLICATION_EBOOK_PAGE_CREATION_FAILED') . '.""' . "\n" . '{{button class="btn-primary" link="' . $this->GetPageTag() . '" text="' . _t('PUBLICATION_GOTO_EBOOK_CREATION_PAGE') . ' ' . $this->GetPageTag() . '"}}""</div>""' . "\n");
        }

			} while (FALSE); // end of ebook specific loop
		} elseif ($outputFormat == 'newsletter') { // We want to produce a newsletter
			$fiche['id_typeannonce'] = $formId;
			$fiche['bf_titre'] = $_POST["publication-title"];
			$fiche['bf_description'] = $_POST["publication-description"];
			$fiche['bf_author'] = $_POST["publication-author"];
      $fiche['bf_content'] = '';

      // Generate the content of the page body
      // @todo Refactor this as a function to share it with Ebook logic
			foreach ($_POST["page"] as $page) {
        // we turn some actions into explicit content
        // for now, {{blankpage}}, but later maybe some specific handlers like {{publicationcover}}, {{publicationbookend}}
        if (preg_match('#{{\s*blankpage\s*}}#U', $page)) {
          $fiche['bf_content'] .= $this->Format($page . "\n");
        }
        // we assume it is a page tag otherwise
        // maybe we should also explicitly check it is a valid page tag instead?
        else {
  				$fiche['bf_content'] .= $this->Format('{{include page="' . $page . '" class=""}}' . "\n");
        }
      }

			$acceptedTags = '<h1><h2><h3><h4><h5><h6><hr><hr/><br><br/><span><blockquote><i><u><b><strong><ol><ul><li><small><div><p><a><table><tr><th><td><img><figure><caption><iframe>';
			$fiche['bf_content'] = strip_tags($fiche['bf_content'], $acceptedTags);
			$fiche = baz_insertion_fiche($fiche);
			$output = $this->Format('""<div class="alert alert-success">' . _t('PUBLICATION_NEWSLETTER_CREATED') . ' !""' . "\n" . '{{button class="btn-primary" link="' . $fiche['id_fiche'] . '" text="' . _t('PUBLICATION_SEE_NEWSLETTER') . ' ' . $fiche['bf_titre'] . '"}}""</div>""' . "\n");
		}
	} while (FALSE); // End of global do-while loop
} else { // Not isset($_POST["page"])
    // recuperation des pages creees a l'installation
    $installedPageNames = [];
    if (!empty($addInstalledPages)) {
      $d = dir("setup/doc/");
      while ($doc = $d->read()) {
          if ($doc == '.' || $doc == '..' || is_dir($doc) || substr($doc, -4) != '.txt') {
              continue;
          }

          if ($doc == '_root_page.txt') {
              $installedPageNames[$this->GetConfigValue("root_page")] = $this->GetConfigValue("root_page");
          } else {
              $pageName = substr($doc, 0, strpos($doc, '.txt'));
              $installedPageNames[$pageName] = $pageName;
          }
      }
    }

    if (isset($this->page["metadatas"]["publication-title"])) {
        $ebookPageName = $this->GetPageTag();
        preg_match_all('/{{include page="(.*)".*}}/Ui', $this->page['body'], $matches);
        $publicationStart = $matches[1][0];
        $last = count($matches[1]) - 1;
        $publicationEnd = $matches[1][$last];
        unset($matches[1][0]);
        unset($matches[1][$last]);
        foreach ($matches[1] as $key => $value) {
            $pagesFiltre = filter_by_value($results, 'tag', $value);
            $selectedPages[] = array_shift($pagesFiltre);
            $key = array_keys($pagesFiltre);
            if ($key && isset($pages[$key[0]])) {
                unset($pages[$key[0]]);
            }
        }
    } else {
        $ebookPageName = '';
        $selectedPages = array();
    }

    include_once 'includes/squelettephp.class.php';
    $exportTemplate = new SquelettePhp($template, 'publication');

    $output .= $exportTemplate->render(
        array(
            'entries' => $results,
            'areParamsReadonly' => $areParamsReadonly,
            'publicationStart' => $this->loadPage($publicationStart),
            'publicationEnd' => $this->loadPage($publicationEnd),
            'addInstalledPages' => $addInstalledPages,
            'installedPageNames' => $installedPageNames,
            'default' => $default,
            'ebookPageName' => $ebookPageName,
            'metaDatas' => $this->page["metadatas"],
            'selectedPages' => $selectedPages,
            'chapterCoverPages' => $chapterCoverPages,
            'url' => $this->href('', $this->GetPageTag()),
            'name' => $name,
            'outputFormat' => $outputFormat,
        )
    );
}

echo $output . "\n";
