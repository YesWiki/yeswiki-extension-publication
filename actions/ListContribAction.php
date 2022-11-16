<?php

namespace YesWiki\Publication;

use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiAction;

class ListContribAction extends YesWikiAction
{
    public const INCLUSION_ANCHOR = '/\{\{include\s*page="(.+)".*\}\}/mU';
    public const FIELD_ANCHOR = '/"{field}":"(.*)"/mU';

    protected $pageManager;

    public function formatArguments($args)
    {
        return [
            'field' => (empty($args['field']) || !is_string($args['field'])) ? 'bf_nom' : $args['field'],
        ];
    }

    public function run()
    {
        // get Service
        $this->pageManager = $this->getService(PageManager::class);
        // common
        $anchor = str_replace('{field}', preg_quote($this->arguments['field'], '/'), self::FIELD_ANCHOR);
        $output = '';

        if (!empty($this->arguments['field'])) {
            $inclusions = $this->wiki->GetAllInclusions();
            if (!empty($inclusions)) {
                $mainPageTag = array_pop($inclusions);
                $mainPage = $this->pageManager->getOne($mainPageTag);
                if (!empty($mainPage)) {
                    $matches = [];
                    if (preg_match_all(self::INCLUSION_ANCHOR, $mainPage['body'], $matches)) {
                        $contributors = [];
                        foreach ($matches[1] as $pageTag) {
                            $page = $this->pageManager->getOne($pageTag);
                            if (!empty($page)) {
                                $name = [];
                                if (preg_match_all($anchor, $page['body'], $name)) {
                                    if (!empty($name[1][0])) {
                                        $v = ucwords(strtolower(trim(json_decode("\"{$name[1][0]}\""))));
                                        $v = str_replace(' Et ', ' et ', $v);
                                        $contributors[$v] = $v;
                                    }
                                }
                            }
                        }
                        if (!empty($contributors)) {
                            ksort($contributors);
                            $output = '<ol>';
                            $output .= implode('', array_map(function ($c) {
                                return "<li>$c</li>";
                            }, $contributors));
                            $output .= '</ol>';
                        }
                    }
                }
            }
        }
        return $output;
    }
}
