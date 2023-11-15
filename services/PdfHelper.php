<?php

namespace YesWiki\Publication\Service;

use Exception;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Cookies\Cookie;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Page;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Throwable;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Publication\Exception\ExceptionWithHtml;
use YesWiki\Publication\Service\SessionManager;
use YesWiki\Wiki;

class PdfHelper
{
    public const SESSION_KEY = 'pdf';
    public const SESSION_TIME_KEY = 0;
    public const SESSION_FULLFILENAMEREADY = 1;
    public const SESSION_FILE_STATUS = 2;
    public const SESSION_BROWSER_READY = 3;
    public const SESSION_PAGE_STATUS = 4;
    public const SESSION_PDF_CREATED = 5;

    private const PATH_LIST = [
        'custom/templates/bazar/',
        'custom/templates/bazar/templates/',
        'themes/tools/bazar/presentation/templates/',
        'themes/tools/bazar/templates/',
        'tools/bazar/presentation/templates/',
    ];

    protected $dbService;
    protected $entryManager;
    protected $templateEngine;
    protected $pageManager;
    protected $params;
    protected $sessionManager;
    protected $wiki;

    public function __construct(
        DbService $dbService,
        EntryManager $entryManager,
        TemplateEngine $templateEngine,
        PageManager $pageManager,
        ParameterBagInterface $params,
        SessionManager $sessionManager,
        Wiki $wiki
    ) {
        $this->dbService = $dbService;
        $this->entryManager = $entryManager;
        $this->templateEngine = $templateEngine;
        $this->pageManager = $pageManager;
        $this->params = $params;
        $this->sessionManager = $sessionManager;
        $this->wiki = $wiki;
    }

    /**
     * Check if the current page to export to pdf is :
     *  - an entry
     *  - a page called with $_GET['bazarliste']
     *
     * If an entry, get content from eventually associated template fiche-x.tpl.html
     * If called by 'bazarliste', get content from eventually associated templates fiche-x.tpl.html
     *  and date of the last modified entry.
     *
     * Return an array containing these data to gives that to sha1 function to obtain an hash depnding
     * of templates content or date of latest entry.
     *
     * Aim : force generation of a new pdf file if the associated entry template or an entry of the forms were modified.
     *
     * @param string $pageTag
     * @param null|string $get
     * @return array
     */
    public function getPageEntriesContent(string $pageTag, ?string $via = null): array
    {
        $return = [];
        if ($this->entryManager->isEntry($pageTag)) {
            $entry = $this->entryManager->getOne($pageTag);
            $formId = $entry['id_typeannonce'];
            $templatePath = $this->getTemplatePathFromFormId($formId);
            if (!empty($templatePath)) {
                $return['template content'] = file_get_contents($templatePath);
            }
        } elseif ($via === 'bazarliste') {
            $page = $this->pageManager->getOne($pageTag);
            if ($page && preg_match('/({{(bazarliste|bazarcarto|calendrier|map|gogomap)\s[^}]*}})/i', $page['body'], $matches)) {
                if (preg_match_all('/([a-zA-Z0-9_]*)=\"(.*)\"/U', $matches[1], $matchesLevel2)) {
                    $params = [];
                    $matches = [];
                    foreach ($matchesLevel2[0] as $id => $match) {
                        $params[$matchesLevel2[1][$id]] = $matchesLevel2[2][$id];
                    }
                    $ids = explode(',', $params['id'] ?? null);
                    if (!empty($ids)) {
                        $ids = array_map(function ($id) {
                            return trim($id);
                        }, $ids);
                        $ids = array_filter($ids, function ($id) {
                            return ((substr($id, 0, 4) != 'http') && (strval(intval($id)) == strval($id)));
                        });
                    }
                    if (!empty($ids)) {
                        $latestEntry = $this->getMostRecentEntry($ids);
                        $return['entries last-date'] = $latestEntry['time'] ??  '';
                        foreach ($ids as $id) {
                            $templatePath = $this->getTemplatePathFromFormId($id);
                            if (!empty($templatePath)) {
                                $return['template fiche-'.$id] = file_get_contents($templatePath);
                            }
                        }
                    }
                }
            }
        }
        return $return;
    }

    /**
     * rerieve fiche-X.tpl.html path and filename form formId
     * TODO : update TemplateEngine with a new function that allow to extract that instead of the current function
     * @param string $formId
     * @return string|null $path
     */
    private function getTemplatePathFromFormId(string $formId): ?string
    {
        $templateFileName = 'fiche-'.trim($formId);
        if ($this->templateEngine->hasTemplate('@bazar/'.$templateFileName.'.tpl.html')) {
            $templateFileName .= '.tpl.html';
        } elseif ($this->templateEngine->hasTemplate('@bazar/'.$templateFileName.'.twig')) {
            $templateFileName .= '.twig';
        } else {
            $templateFileName = '';
        }

        if (!empty($templateFileName)) {
            foreach (self::PATH_LIST as $path) {
                if (file_exists($path.$templateFileName)) {
                    return $path.$templateFileName;
                }
            }
        }
        return null;
    }

    /**
     * find date of last entry of the forms
     * @param array $formsIds
     * @return ?array $entry
     */
    private function getMostRecentEntry(array $formsIds): ?array
    {
        $EntriesRequest =
            'SELECT DISTINCT resource FROM ' . $this->dbService->prefixTable('triples') .
            'WHERE value = "fiche_bazar" AND property = "http://outils-reseaux.org/_vocabulary/type" ' .
            'ORDER BY resource ASC';
        $FormIdRequest = join(
            ' OR ',
            array_map(function ($id) {
                return 'body LIKE \'%"id_typeannonce":"'.trim($id).'"%\'';
            }, $formsIds)
        );

        if (empty($FormIdRequest)) {
            throw new \Exception("No form id request ! \$formsIds = ".json_encode($formsIds));
        }

        $SQLRequest =
        'SELECT DISTINCT time FROM ' . $this->dbService->prefixTable('pages') . ' '.
        'WHERE latest="Y" AND comment_on = \'\' ' .
        'AND ('.$FormIdRequest.') ' .
        'AND tag IN (' . $EntriesRequest . ') '.
        'ORDER BY time DESC '.
        'LIMIT 1';

        return $results = $this->dbService->loadSingle($SQLRequest);
    }

    /**
     * getData to prepare export PDF
     * @param array $get
     * @param array $server
     * @return array compact(['pageTag','sourceUrl','hash','dlFilename','fullFilename'])
     */
    public function getFullFileName(array $get, array $server): array
    {
        list('pageTag'=>$pageTag, 'sourceUrl'=>$sourceUrl, 'hash'=>$hash) =
            $this->getSourceUrl($get, $server);

        $dlFilename = sprintf(
            '%s-%s.pdf',
            $pageTag,
            $hash
        );
        $sanitizeWebsiteName = preg_replace(
            "/-+$/",
            "",
            preg_replace(
                "/[^A-Za-z0-9]/",
                "-",
                preg_replace(
                    "/^https?:\/\//",
                    "",
                    $this->params->get('base_url')
                )
            )
        );
        $dirname = sys_get_temp_dir()."/yeswiki-$sanitizeWebsiteName/";
        if (!file_exists($dirname)) {
            mkdir($dirname);
        }
        $fullFilename = "$dirname$pageTag-publication-$hash.pdf";
        return compact(['pageTag','sourceUrl','hash','dlFilename','fullFilename']);
    }

    /**
     * @param array $get
     * @param array $server
     * @return array compact(['pageTag','sourceUrl','hash',])
     */
    public function getSourceUrl(array $get, array $server): array
    {
        $data = [];
        foreach (array_merge(
            [
                'tools/publication/javascripts/browser/print.js',
                'tools/publication/javascripts/vendor/pagedjs/paged.esm.js',
                'custom/templates/publication/print-layouts/base.twig',
                'tools/publication/infos.json',
            ],
            glob('custom/tools/publication/*.css'),
            glob('custom/tools/publication/print-layouts/*.css'),
        ) as $path) {
            if (file_exists($path)) {
                $data[] = file_get_contents($path);
            }
        }
        $pagedjs_hash = sha1(json_encode(array_merge($data)));

        return $this->getData($get, $server, $pagedjs_hash);
    }

    /**
     * getData to prepare export PDF
     * @param array $get
     * @param array $server
     * @param string $pagedjs_hash
     * @return array compact(['pageTag','sourceUrl','hash'])
     */
    protected function getData(array $get, array $server, string $pagedjs_hash): array
    {
        $pageTag = '';
        $sourceUrl = '';
        $hash = '';
        if (!empty($get['url'])) {
            $pageTag = (isset($get['urlPageTag']) && is_string($get['urlPageTag'])) ? $get['urlPageTag'] : 'publication';
            $sourceUrl = strval($get['url']);
            $queryString = preg_replace('/&uuid=[A-Za-z0-9\-]+(&|$)/', '$1', $server['QUERY_STRING'] ?? '');
            $queryString = preg_replace('/(?|&)refresh=[A-Za-z0-9\-]+(&|$)/', '$1', $queryString);
            $hash = substr(sha1($pagedjs_hash . strtolower($queryString)), 0, 10);
        } else {
            $pageTag = $this->wiki->GetPageTag();
            $pdfTag = $this->wiki->MiniHref('pdf'.testUrlInIframe(), $pageTag);
            $queryString = preg_replace('#^'. $pdfTag .'&?#', '', $server['QUERY_STRING'] ?? '');
            $queryString = preg_replace('/refresh=[A-Za-z0-9\-]+(&|$)/', '', $queryString);
            $sourceUrl = $this->wiki->href('preview', $pageTag, $queryString, false);


            $hash = substr(sha1($pagedjs_hash . json_encode(array_merge(
                $this->wiki->page,
                ['query_string' => strtolower($queryString),
                $this->getPageEntriesContent(
                    $pageTag,
                    $get['via'] ?? null
                ) ?? []]
            ))), 0, 10);

            // In case we are behind a proxy (like a Docker container)
            // It allows us to properly load the document from within the container itself
            if (!empty($this->params->get('htmltopdf_base_url'))) {
                $sourceUrl = str_replace($this->params->get('base_url'), $this->params->get('htmltopdf_base_url'), $sourceUrl);
            }
        }
        return compact(['pageTag','sourceUrl','hash']);
    }

    /**
     * generate the pdf file from content
     * @param string $sourceUrl
     * @param string $fullFilename
     * @param string $uuid
     * @param array $cookies
     * @throws ExceptionWithHtml
     * @throws Exception with code = 2
     */
    public function useBrowserToCreatePdfFromPage(
        string $sourceUrl,
        string $fullFilename,
        string $uuid = '',
        array $cookies = []
    ) {
        $this->assertCanExecChromium();
        try {
            $browserFactory = new BrowserFactory($this->params->get('htmltopdf_path'));
            $options = $this->params->get('htmltopdf_options');
            $browser = $browserFactory->createBrowser($options);
            $this->setValueInSession($uuid, PdfHelper::SESSION_BROWSER_READY, 1);

            $timeout = (
                empty($options['sendSyncDefaultTimeout']) ||
                !is_scalar($options['sendSyncDefaultTimeout']) ||
                intval($options['sendSyncDefaultTimeout']) < 10000 // in ms
            ) ? 20 // in sec
            : ceil(intval($options['sendSyncDefaultTimeout'])*2/1000); // in s
            // (twice to be sure that Browser manages timeout and not php)
            set_time_limit($timeout);


            $page = $browser->createPage();
            $this->setValueInSession($uuid, PdfHelper::SESSION_PAGE_STATUS, 1);

            if (!empty($cookies)) {
                $formattedCookies = [];
                foreach ($cookies as $cookie) {
                    $cookie = array_filter($cookie, function ($v, $k) {
                        return in_array($k, ['domain','path','name','value'], true) && !empty($v) && is_string($v);
                    }, ARRAY_FILTER_USE_BOTH);
                    if (count($cookie) == 4) {
                        $formattedCookies[] = new Cookie([
                            'name' => $cookie['name'],
                            'value' => $cookie['value'],
                            'domain' => $cookie['domain'],
                            'path' => $cookie['path'],
                            'httponly' => true,
                            'secure' => true,
                            'samesite' => 'None',
                            'expires' => time() + 600 // expires in 10 minutes
                        ]);
                    }
                }
                if (!empty($formattedCookies)) {
                    $page->setCookies($formattedCookies)->await();
                }
            }


            $timeout = (
                empty(intval($this->params->get('page_load_timeout'))) ||
                intval($this->params->get('page_load_timeout')) < 30000 // in ms
            ) ? 60 // in sec
            : ceil(intval($this->params->get('page_load_timeout'))*2/1000); // in s
            // (twice to be sure that page manages timeout and not php)
            set_time_limit($timeout);

            $page->navigate($sourceUrl)->waitForNavigation(Page::NETWORK_IDLE, intval($this->params->get('page_load_timeout')) > 30000 ? $this->params->get('page_load_timeout') : 30000);
            $this->addValueInSession($uuid, PdfHelper::SESSION_PAGE_STATUS, 2);

            set_time_limit($timeout);

            $value = $page->evaluate('__is_yw_publication_ready()')->getReturnValue($this->params->get('page_load_timeout'));
            $this->addValueInSession($uuid, PdfHelper::SESSION_PAGE_STATUS, 4);

            // reset timer for time limit and give 30 sec more to render pdf
            set_time_limit(30);

            // now generate PDF
            $page->pdf(array(
                'printBackground' => true,
                'displayHeaderFooter' => true,
                'preferCSSPageSize' => true
                ))->saveToFile($fullFilename);
            $this->setValueInSession($uuid, PdfHelper::SESSION_PDF_CREATED, 1);

            $browser->close();
        } catch (Exception $e) {
            // if (($e instanceof OperationTimedOut) === false) {
            //     $html = $page->evaluate('document.documentElement.innerHTML')->getReturnValue();
            // }
            // $browser->close();
            throw new ExceptionWithHtml($e->getMessage(), 0, $e, $html ?? '');
        }
    }

    /**
     * check if chromium can be executed
     * @throws Exception with code = 2
     */
    public function assertCanExecChromium()
    {
        if (!$this->canExecChromium()) {
            throw new Exception("Path '{$this->params->get('htmltopdf_path')}' is not executable", 2);
        }
    }
    /**
     * check if chromium can be executed
     * @return bool
     */
    public function canExecChromium(): bool
    {
        return is_executable($this->params->get('htmltopdf_path'));
    }

    /**
     * check id Domain is authorized
     * @param string $sourceUrl
     * @return bool
     */
    public function checkDomain(string $sourceUrl): bool
    {
        try {
            $currentDomain = parse_url($this->params->get('base_url'), PHP_URL_HOST);
            $sourceDomain = parse_url($sourceUrl, PHP_URL_HOST);
            $authorizedDomains = $this->params->get('htmltopdf_service_authorized_domains');
            $authorizedDomains = is_array($authorizedDomains)
                 ? array_filter(array_map('trim', array_filter($authorizedDomains, 'is_string')))
                 : [];
            return (
                $sourceDomain === $currentDomain ||
                (!empty($authorizedDomains) && in_array($sourceDomain, $authorizedDomains, true))
            );
        } catch (Throwable $th) {
            return false;
        }
    }

    /**
     * prepare session to store things
     * @param string $newUuid
     */
    public function prepareSession(string $newUuid)
    {
        $this->sessionManager->reactivateSession();
        if (isset($_SESSION) && !empty($newUuid)) {
            if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
                $_SESSION[self::SESSION_KEY] = [];
            }
            $limitTime = time() + 3600*2;
            foreach ($_SESSION[self::SESSION_KEY] as $uuid => $data) {
                if (empty($data[self::SESSION_TIME_KEY]) && $data[self::SESSION_TIME_KEY] < $limitTime) {
                    unset($_SESSION[self::SESSION_KEY][$uuid]);
                }
            }
            if (!empty($newUuid)) {
                $_SESSION[self::SESSION_KEY][$newUuid] = [];
                $_SESSION[self::SESSION_KEY][$newUuid][self::SESSION_TIME_KEY] = time();
            }
        }
        $this->sessionManager->safeCloseSession();
    }

    /**
     * set session value
     * @param string $uuid
     * @param $key
     * @param $value
     */
    public function setValueInSession(string $newUuid, $key, $value)
    {
        $this->sessionManager->reactivateSession();
        if (isset($_SESSION) && !empty($newUuid) && !empty($_SESSION[self::SESSION_KEY][$newUuid])) {
            $_SESSION[self::SESSION_KEY][$newUuid][$key] = $value;
        }
        $this->sessionManager->safeCloseSession();
    }


    /**
     * set session value
     * @param string $uuid
     * @return mixed
     */
    public function getValueInSession(string $uuid, $key)
    {
        $this->sessionManager->reactivateSession();
        $val = $_SESSION[self::SESSION_KEY][$uuid][$key] ?? null;
        $this->sessionManager->safeCloseSession(false);
        return $val;
    }

    /**
     * set session values
     * @param string $uuid
     * @return array
     */
    public function getValuesInSession(string $uuid)
    {
        $this->sessionManager->reactivateSession();
        $values = $_SESSION[self::SESSION_KEY][$uuid] ?? [];
        $this->sessionManager->safeCloseSession(false);
        return $values;
    }

    /**
     * set session values
     * @param string $uuid
     * @param $key
     * @param int $value
     */
    public function addValueInSession(string $uuid, $key, int $value)
    {
        $this->sessionManager->reactivateSession();
        if (isset($_SESSION[self::SESSION_KEY][$uuid][$key])) {
            $_SESSION[self::SESSION_KEY][$uuid][$key] = intval($_SESSION[self::SESSION_KEY][$uuid][$key]) + $value;
        }
        $this->sessionManager->safeCloseSession();
    }
}
