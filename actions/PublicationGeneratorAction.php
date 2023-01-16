<?php

namespace YesWiki\Publication;

use Exception;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Tags\Service\TagsManager;
use YesWiki\Publication\Service\Publication;

class PublicationGeneratorAction extends YesWikiAction
{
    public const COMPATIBILITY_CORRESPONDANCES = [
        ['ebookpagenameprefix', 'pagenameprefix'],
        ['fields', 'readonly'],
        ['ebookstart', 'pagestart'],
        ['ebookend', 'pageend'],
        ['publicationstart', 'pagestart'],
        ['publicationend', 'pageend']
    ];
    public const ACCEPTED_TAGS = '<h1><h2><h3><h4><h5><h6><hr><hr/><br><br/><span><blockquote><i><u><b><strong>'.
    '<ol><ul><li><small><div><p><a><table><tr><th><td><img><figure><caption><iframe><style>';

    protected $dbService;
    protected $entryManager;
    protected $formManager;
    protected $pageManager;
    protected $publicationService;

    public function formatArguments($args)
    {
        // deprecated parameters for checkDeprecated
        $deprecatedParameters = [];
        foreach ($this->getFormattedCorrespondances() as $correspondance) {
            $deprecatedParameters[$correspondance['oldName']] = $args[$correspondance['oldName']] ?? null;
        }

        return $deprecatedParameters + [
            'outputformat' => $this->formatString($args, 'outputformat', 'ebook'),
            'formid' => (!empty($args['formid']) && is_scalar($args['formid']) && intval($args['formid'])>0) ? strval($args['formid']) : '',
            // Indicates if fields and elements are "read only"
            'readonly' => isset($args['readonly']) && in_array($args['readonly'], ['',true], true),
            // Pages used for intro and outro
            'pagestart' => $this->formatString($args, 'pagestart', ''),
            'pageend' => $this->formatString($args, 'pageend', ''),
            // prefix for created pages
            // Only used when outputformat="ebook"
            'pagenameprefix' => $this->formatString($args, 'pagenameprefix', 'Ebook'),
            // include default pages in page listing ?
            'addinstalledpage' => $this->formatBoolean($args, false, 'addinstalledpage'),
            // defaults from the action
            'coverimage' => $this->formatString($args, 'coverimage', ''),
            'title' => $this->formatString($args, 'title', ''),
            'desc' => $this->formatString($args, 'desc', ''),
            'authors' => $this->formatString($args, isset($args['author']) ? 'author' : 'authors', ''),
            // default added pages that can be used to separate content
            'chapterpages' => array_map('trim', $this->formatArray($args['chapterpages'] ?? [])),
            // titles for groups
            'titles' => array_map('trim', $this->formatArray($args['titles'] ?? [])),
            // groups of pages or bazar entries
            'groupselector' => $this->formatString($args, 'groupselector', ''),
        ];
    }

    public function run()
    {
        // get Services
        $this->dbService = $this->getService(DbService::class);
        $this->entryManager = $this->getService(EntryManager::class);
        $this->formManager = $this->getService(FormManager::class);
        $this->pageManager = $this->getService(PageManager::class);
        $this->publicationService = $this->getService(Publication::class);

        include_once 'tools/tags/libs/tags.functions.php';

        if ($this->isNewsletter() &&
            empty($this->arguments['formid'])) {
            throw new Exception(_t('PUBLICATION_MISSING_NEWSLETTER_FORM'));
        }

        $messages = $this->checkDeprecated();

        $results = $this->getResults();

        list(
            'ebookPageName' => $ebookPageName,
            'selectedPages'=>$selectedPages,
            'publicationStart'=>$publicationStart,
            'publicationEnd'=>$publicationEnd,
        ) = $this->getEbookPageName($results);

        try {
            $this->managePost($_POST ?? [], $messages, $ebookPageName);
        } catch (Exception $th) {
            if ($th->getCode() ==  1) {
                return $th->getMessage();
            } else {
                throw $th;
            }
        }

        return $this->render('@publication/publicationgenerator.twig', [
            'messages' => $messages,
            'entries' => $results,
            'areParamsReadonly' => $this->arguments['readonly'],
            'publicationStart' => $this->pageManager->getOne($publicationStart),
            'publicationEnd' => $this->pageManager->getOne($publicationEnd),
            'addInstalledPages' => $this->arguments['addinstalledpage'],
            'installedPageNames' => $this->getInstalledPageNames(),
            'ebookPageName' => $ebookPageName,
            'metadatas' => $this->publicationService->getOptions([
                "publication-cover-image" => $this->arguments['coverimage'],
                "publication" => [
                  "title" => $this->arguments['title'],
                  "description" => $this->arguments['desc'],
                  "authors" => $this->arguments['authors'],
                ]
              ], $_POST, $this->wiki->page["metadatas"] ?: []),
            'selectedPages' => $selectedPages,
            'chapterCoverPages' => $this->getChapterCoverPages(),
            'url' => $this->wiki->href('', $this->wiki->GetPageTag()),
            'name' => $this->getName(),
            'outputFormat' => $this->arguments['outputformat'],
          ]);
    }

    protected function formatString(array $args, string $key, string $default): string
    {
        return (isset($args[$key]) && is_string($args[$key])) ? $args[$key] : $default;
    }

    protected function formatArray($param)
    {
        return (is_string($param) || is_array($param)) ? parent::formatArray($param) : [];
    }

    protected function checkDeprecated(): array
    {
        $messages = [];
        foreach ($this->getFormattedCorrespondances() as $correspondance) {
            if (!is_null($this->arguments[$correspondance['oldName']])) {
                $messages[] = [
                    'message' => _t('PUBLICATION_PARAMETER_DEPRECATED', $correspondance),
                    'type' => 'warning'
                ];
            }
        }
        return $messages;
    }

    protected function getChapterCoverPages(): array
    {
        $chapterCoverPages = [];
        foreach ($this->arguments['chapterpages'] as $pageTag) {
            $chapterCoverPages[$pageTag] = $this->pageManager->getOne($pageTag);
        }
        return $chapterCoverPages;
    }

    protected function getEbookPageName(array $results): array
    {
        $ebookPageName = '';
        $selectedPages = [];
        $publicationStart = $this->arguments['pagestart'];
        $publicationEnd = $this->arguments['pageend'];
        if (isset($this->wiki->page["metadatas"]["publication-title"])) {
            $ebookPageName = $this->wiki->GetPageTag();
            $matches = [];
            if (preg_match_all('/{{include page="(.*)".*}}/Ui', $this->wiki->page['body'], $matches)) {
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
            }
        }

        return compact(['ebookPageName','selectedPages','publicationStart','publicationEnd']);
    }

    protected function getFormattedCorrespondances(): array
    {
        return array_map(
            function ($correspondance) {
                return [
                    'oldName' => $correspondance[0],
                    'newName' => $correspondance[1],
                ];
            },
            self::COMPATIBILITY_CORRESPONDANCES
        );
    }

    protected function getInstalledPageNames(): array
    {
        // recuperation des pages creees a l'installation
        $installedPageNames = [];
        if ($this->arguments['addinstalledpage'] && is_dir("setup/doc/")) {
            $d = dir("setup/doc/");
            while ($doc = $d->read()) {
                if ($doc == '.' || $doc == '..' || is_dir($doc) || substr($doc, -4) != '.txt') {
                    continue;
                }

                if ($doc == '_root_page.txt') {
                    $installedPageNames[$this->params->get("root_page")] = $this->params->get("root_page");
                } else {
                    $pageName = substr($doc, 0, strpos($doc, '.txt'));
                    $installedPageNames[$pageName] = $pageName;
                }
            }
        }
        return $installedPageNames;
    }

    protected function getResults(): array
    {
        $results = [];
        if (!empty($this->arguments['groupselector'])) {
            $matches = [];
            if (preg_match_all('/(\d+|pages)(\(([^\(\)]*)\))?/m', $this->arguments['groupselector'], $matches)) {
                foreach ($matches[1] as $i => $formId) {
                    // bazar entries
                    if (strval($formId) == strval(intval($formId)) && intval($formId) > 0) {
                        $results[$i]['type'] = 'bazar';
                        $formValues = $this->formManager->getOne(strval($formId));
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
                        $results[$i]['entries'] = $this->entryManager->search(['queries' => $tabQuery, 'formsIds' => [strval($formId)]]);

                        // tri des fiches
                        $this->fieldSort($results[$i]['entries'], 'asc', 'bf_titre');
                    } elseif ($formId == 'pages') {
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

                        $results[$i]['entries'] = $this->loadPages($tagList);
                    }
                }
            }
        } else {
            // we take everything if nothing is specified
            $results[0]['entries'] = $this->loadPages('');
            // wiki pages
            $results[0]['type'] = 'pages';
            $results[0]['name'] = 'Pages wikis';

            // bazar entries
            $results[1]['type'] = 'bazar';
            $results[1]['name'] = 'Fiches bazar';
            $results[1]['entries'] = $this->entryManager->search();
            $this->fieldSort($results[1]['entries'], 'asc', 'bf_titre');
        }
        return $results;
    }

    protected function isEbook(): bool
    {
        return strcasecmp($this->arguments['outputformat'], 'ebook') == 0;
    }

    protected function isNewsletter(): bool
    {
        return strcasecmp($this->arguments['outputformat'], 'newsletter') === 0;
    }

    protected function getName(): string
    {
        return $this->isNewsletter() ? _t('PUBLICATION_NEWSLETTER') : _t('PUBLICATION_EBOOK');
    }

    protected function fieldSort(array &$data, string $order, string $field)
    {
        usort($data, function ($a, $b) use ($order, $field) {
            if ($order == 'desc') {
                return strcoll(mb_strtolower($b[$field]), mb_strtolower($a[$field]));
            } else {
                return strcoll(mb_strtolower($a[$field]), mb_strtolower($b[$field]));
            }
        });
    }

    protected function loadPages(string $tagList = ""): ?array
    {
        // wiki pages
        // very similar to TagsManager::getPagesByTags
        $tripleTableSelection = empty($tagList) ? '' : ", {$this->dbService->prefixTable('triples')} tags";
        $tagListSQL = empty($tagList) ? '' :
            <<<SQL
            AND tags.value IN ($tagList) AND tags.property = "http://outils-reseaux.org/_vocabulary/tag" AND tags.resource = tag
            SQL;

        $sql =
        <<<SQL
        SELECT DISTINCT tag,body FROM {$this->dbService->prefixTable('pages')} $tripleTableSelection
          WHERE latest="Y"
          AND comment_on="" AND tag NOT LIKE "LogDesActionsAdministratives%"
          AND tag NOT IN (
            SELECT resource FROM {$this->dbService->prefixTable('triples')}
              WHERE property="http://outils-reseaux.org/_vocabulary/type"
          )
          $tagListSQL
          ORDER BY tag ASC
        SQL;

        return $this->dbService->loadAll($sql);
    }

    /**
     * Handling of data submitted by the form
     * page creation
     */
    protected function managePost(array $post, array &$messages, string $ebookPageName)
    {
        if (!empty($post) && $this->checkPostValues($post, $messages)) {
            if ($this->isEbook()) {
                // We want to produce an ebook (default behaviour)
                if (!empty($post["publication-cover-image"]) && (
                    !is_string($post["publication-cover-image"]) ||
                    preg_match("/.(jpe?g|png|svg)$/i", $post["publication-cover-image"]) != 1
                )) {
                    // there is no publication-cover-image
                    $messages[] = [
                        'message' =>_t('PUBLICATION_NOT_IMAGE_FILE'),
                        'type' => 'danger'
                    ];
                } else {
                    $pageName = !empty($ebookPageName) ? $ebookPageName : generatePageName("$ebookPageNamePrefix {$post["publication"]["title"]}");

                    $output = '';
                    // Generate the content of the page body
                    // @todo refactor it to share its logic with newsletter
                    foreach ($post["page"] as $page) {
                        // we turn some actions into explicit content
                        // for now, {{blankpage}}, but later maybe some specific handlers like {{publicationcover}}, {{publicationbookend}}
                        if (preg_match('#{{\s*blankpage\s*}}#U', $page)) {
                            $output .= $page . "\n";
                        }
                        // we assume it is a page tag otherwise
                        // maybe we should also explicitly check it is a valid page tag instead?
                        // $page can be 'SomeTag' or 'SomeTag?parameter=value'
                        // the query string is used to parametrize book creation
                        else {
                            $includeCode = $this->publicationService->getIncludeActionFromPageTag($page);
                            $output .= $includeCode;
                        }
                    }
                    unset($post['page']);
                    unset($post['antispam']);

                    if ($this->pageManager->save($pageName, $output) === 0) {
                        $this->pageManager->setMetadata($pageName, $post);
                        $this->wiki->SetMessage(_t('PUBLICATION_EBOOK_PAGE_CREATED'));
                        $this->wiki->Redirect($this->wiki->Href('', $pageName));
                    } else {
                        $t = [
                            'PUBLICATION_EBOOK_PAGE_CREATION_FAILED' => _t('PUBLICATION_EBOOK_PAGE_CREATION_FAILED'),
                            'PUBLICATION_GOTO_EBOOK_CREATION_PAGE' => _t('PUBLICATION_GOTO_EBOOK_CREATION_PAGE')
                        ];
                        $errorContent = $this->wiki->Format(
                            <<<STR
                            ""<div class="alert alert-danger alert-error">{$t['PUBLICATION_EBOOK_PAGE_CREATION_FAILED']}""\n
                            {{button class="btn-primary" link="{$this->wiki->GetPageTag()}" text="{$t['PUBLICATION_GOTO_EBOOK_CREATION_PAGE']} {$this->wiki->GetPageTag()}"}}""</div>""\n
                            STR
                        );
                        throw new Exception($errorContent, 1);
                    }
                }
            } elseif ($this->isNewsletter()) {
                $fiche = [
                    'id_typeannonce' => $this->arguments['formid'],
                    'bf_titre' => implode(' ', [$this->arguments['outputformat'], $post["publication"]["title"]]),
                    'bf_description' => $post["publication"]["bf_description"] ?? '',
                    'bf_author' => $post["publication"]["authors"] ?? '',
                    'bf_content' => '',
                ];

                // Generate the content of the page body
                // @todo Refactor this as a function to share it with Ebook logic
                foreach ($post["page"] as $page) {
                    // we turn some actions into explicit content
                    // for now, {{blankpage}}, but later maybe some specific handlers like {{publicationcover}}, {{publicationbookend}}
                    if (preg_match('#{{\s*blankpage\s*}}#U', $page)) {
                        $fiche['bf_content'] .= $this->wiki->Format($page . "\n");
                    }
                    // we assume it is a page tag otherwise
                    // maybe we should also explicitly check it is a valid page tag instead?
                    else {
                        $includeCode = $this->publicationService->getIncludeActionFromPageTag($page);
                        $fiche['bf_content'] .= $this->wiki->Format($includeCode);
                    }
                }
                $fiche['bf_content'] = strip_tags($fiche['bf_content'], self::ACCEPTED_TAGS);
                $fiche['antispam'] = 1;
                $fiche = $this->entryManager->create($this->arguments['formid'], $fiche);
                if (!empty($fiche)) {
                    $messages[] = [
                        'message' =>_t('PUBLICATION_NEWSLETTER_CREATED'),
                        'type' => 'success'
                    ];
                } else {
                    $messages[] = [
                        'message' => 'error when creating entry',
                        'type' => 'warning'
                    ];
                }
            }
        }
    }

    protected function checkPostValues(array $post, array &$messages): bool
    {
        if (!isset($post['antispam']) || $post['antispam'] != 1) {
            // There may be a spamming problem
            $messages[] = [
                'message' => _t('PUBLICATION_SPAM_RISK'),
                'type' => 'danger'
            ];
            return false;
        }
        if (!isset($post["page"]) || count($post["page"]) === 0 || !(is_string($post["page"]) || is_array($post["page"]))) {
            // There is no page selected
            $messages[] = [
                'message' => _t('PUBLICATION_NO_PAGE_FOUND'),
                'type' => 'danger'
            ];
            return false;
        }
        if (!isset($post["publication"]["title"]) || trim($post["publication"]["title"]) === '') {
            // There is no publication-title
            $messages[] = [
                'message' => _t('PUBLICATION_NO_PAGE_FOUND'),
                'type' => 'danger'
            ];
            return false;
        }
        return true;
    }
}
