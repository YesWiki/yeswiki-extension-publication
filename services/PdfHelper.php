<?php

namespace YesWiki\Publication\Service;

use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Cookies\Cookie;
use HeadlessChromium\Cookies\CookiesCollection;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Page;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Publication\Exception\ExceptionWithHtml;

/** Where a page's PDF comes from: the preview address, the cache, and the headless browser that prints it. */
class PdfHelper
{
    public const SESSION_KEY = 'pdf';
    public const SESSION_TIME_KEY = 0;
    public const SESSION_FULLFILENAMEREADY = 1;
    public const SESSION_FILE_STATUS = 2;
    public const SESSION_BROWSER_READY = 3;
    public const SESSION_PAGE_STATUS = 4;
    public const SESSION_PDF_CREATED = 5;

    public const CACHE_DIR = 'cache/publication/';

    private const BROWSERS = ['chromium', 'chromium-browser', 'google-chrome', 'google-chrome-stable', 'chrome'];

    private const LIST_ACTIONS = 'entrylist|entrymap|calendar|bazarliste|bazarcarto|calendrier|map|gogomap';

    public function __construct(
        private EntryManager $entryManager,
        private PageManager $pageManager,
        private PageContext $pageContext,
        private UrlFormatter $urlFormatter,
        private AuthenticationService $authenticationService,
        private ParameterBagInterface $params,
        private SessionManager $sessionManager,
        private Storage $storage
    ) {
    }

    /** The browser to print with: the configured one, or the first one found on the PATH; '' when there is none. */
    public function chromiumPath(): string
    {
        $configured = $this->stringParam('htmltopdf_path');
        if ($configured !== '' && is_file($configured) && is_executable($configured)) {
            return $configured;
        }
        foreach (explode(PATH_SEPARATOR, (string)getenv('PATH')) as $directory) {
            foreach (self::BROWSERS as $name) {
                $candidate = rtrim($directory, '/') . '/' . $name;
                if ($directory !== '' && is_file($candidate) && is_executable($candidate)) {
                    return $candidate;
                }
            }
        }

        return '';
    }

    public function canExecChromium(): bool
    {
        return $this->chromiumPath() !== '';
    }

    /** Whether the page asked for belongs to this wiki, or to a domain it prints for. */
    public function checkDomain(string $sourceUrl): bool
    {
        $currentDomain = parse_url($this->stringParam('base_url'), PHP_URL_HOST);
        $sourceDomain = parse_url($sourceUrl, PHP_URL_HOST);
        if (!is_string($sourceDomain) || $sourceDomain === '') {
            return false;
        }
        $authorized = $this->params->has('htmltopdf_service_authorized_domains') ? $this->params->get('htmltopdf_service_authorized_domains') : [];
        $authorized = is_array($authorized) ? array_filter(array_map('trim', array_filter($authorized, 'is_string'))) : [];

        return $sourceDomain === $currentDomain || in_array($sourceDomain, $authorized, true);
    }

    /**
     * The page to print, its preview address and the hash that names its PDF.
     *
     * @param array<string, mixed> $get
     * @param array<string, mixed> $server
     *
     * @return array{pageTag: string, sourceUrl: string, hash: string}
     */
    public function getSourceUrl(array $get, array $server): array
    {
        $assetsHash = $this->assetsHash();
        if (!empty($get['url']) && is_string($get['url'])) {
            $queryString = (string)preg_replace('/(^|&)(uuid|refresh)=[A-Za-z0-9\-]*/', '', (string)($server['QUERY_STRING'] ?? ''));

            return [
                'pageTag' => (isset($get['urlPageTag']) && is_string($get['urlPageTag'])) ? $get['urlPageTag'] : 'publication',
                'sourceUrl' => $get['url'],
                'hash' => substr(sha1($assetsHash . $this->getLoggedUserName() . strtolower($queryString)), 0, 10),
            ];
        }

        $pageTag = $this->pageContext->getTag();
        $query = array_diff_key($this->urlFormatter->currentQuery(), ['refresh' => true, 'uuid' => true]);
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $sourceUrl = $this->urlFormatter->href('preview', $pageTag, $queryString, false);
        $via = isset($get['via']) && is_string($get['via']) ? $get['via'] : null;
        $hash = substr(sha1($assetsHash . json_encode(
            [
                'page' => $this->pageContext->getPage() ?? [],
                'user' => $this->getLoggedUserName(),
                'query_string' => strtolower($queryString),
                'entries' => $this->getPageEntriesContent($pageTag, $via),
            ],
            JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        )), 0, 10);

        $proxyBaseUrl = $this->stringParam('htmltopdf_base_url');
        if ($proxyBaseUrl !== '') {
            $sourceUrl = str_replace($this->stringParam('base_url'), $proxyBaseUrl, $sourceUrl);
        }

        return ['pageTag' => $pageTag, 'sourceUrl' => $sourceUrl, 'hash' => $hash];
    }

    /**
     * getSourceUrl(), plus the name the PDF is downloaded under and where it is cached.
     *
     * @param array<string, mixed> $get
     * @param array<string, mixed> $server
     *
     * @return array{pageTag: string, sourceUrl: string, hash: string, dlFilename: string, cachePath: string}
     */
    public function getFullFileName(array $get, array $server): array
    {
        $source = $this->getSourceUrl($get, $server);
        $safeTag = (string)preg_replace('/[^A-Za-z0-9_-]+/', '-', $source['pageTag']);

        return $source + [
            'dlFilename' => "{$safeTag}-{$source['hash']}.pdf",
            'cachePath' => self::CACHE_DIR . "{$safeTag}-publication-{$source['hash']}.pdf",
        ];
    }

    /**
     * What a PDF's content depends on besides the page itself: the form templates of the entries it shows, and when they last changed.
     *
     * @return array<string, string>
     */
    public function getPageEntriesContent(string $pageTag, ?string $via = null): array
    {
        $content = [];
        if ($this->entryManager->isEntry($pageTag)) {
            $entry = $this->entryManager->getOne($pageTag);
            $formId = is_scalar($entry['form_id'] ?? null) ? (string)$entry['form_id'] : '';
            if ($formId !== '') {
                $content['template content'] = $this->formTemplate($formId);
            }

            return array_filter($content);
        }
        if (!in_array($via, ['entrylist', 'bazarliste'], true)) {
            return [];
        }
        $page = $this->pageManager->getOne($pageTag);
        $formIds = $this->listedFormIds(PageBody::content(is_array($page['body'] ?? null) ? $page['body'] : []));
        if ($formIds === []) {
            return [];
        }
        $dates = array_map(
            static fn (array $entry): string => (string)($entry['updated_at'] ?? ''),
            $this->entryManager->search(['formsIds' => $formIds])
        );
        $content['entries last-date'] = $dates === [] ? '' : max($dates);
        foreach ($formIds as $formId) {
            $content['template fiche-' . $formId] = $this->formTemplate($formId);
        }

        return array_filter($content);
    }

    /**
     * The local form ids of the first list on a page.
     *
     * @return list<string>
     */
    public function listedFormIds(string $content): array
    {
        $arguments = $this->firstListArguments($content);
        $ids = array_map('trim', explode(',', (string)($arguments['id'] ?? '')));

        return array_values(array_filter($ids, static fn (string $id): bool => $id !== '' && ctype_digit($id)));
    }

    /**
     * The parameters of the first list action written on a page.
     *
     * @return array<string, string>
     */
    public function firstListArguments(string $content): array
    {
        if (!preg_match('/\{\{\s*(?:' . self::LIST_ACTIONS . ')(\s[^}]*)?\}\}/i', $content, $match)) {
            return [];
        }
        $arguments = [];
        if (preg_match_all('/([a-zA-Z0-9_]+)="(.*)"/U', $match[1] ?? '', $pairs, PREG_SET_ORDER)) {
            foreach ($pairs as $pair) {
                $arguments[$pair[1]] = $pair[2];
            }
        }

        return $arguments;
    }

    /** The signed-in user's name, '' for an anonymous visitor. */
    public function getLoggedUserName(): string
    {
        $user = $this->authenticationService->getLoggedUser();

        return is_array($user) && is_string($user['name'] ?? null) ? $user['name'] : '';
    }

    /**
     * The login cookies of this visitor, for the browser to print what they see; none for an anonymous visitor, whose PDF stays cacheable.
     *
     * @return list<array{name: string, value: string, domain: string, path: string, secure: bool, samesite: string}>
     */
    public function getAuthenticationCookies(string $sourceUrl): array
    {
        if (\PHP_SAPI === 'cli' || $this->getLoggedUserName() === '') {
            return [];
        }
        $domain = parse_url($sourceUrl, PHP_URL_HOST);
        if (!is_string($domain) || $domain === '') {
            return [];
        }
        $cookies = [];
        foreach (array_unique(array_filter([session_name(), 'name', 'token'])) as $name) {
            if (empty($_COOKIE[$name]) || !is_string($_COOKIE[$name])) {
                continue;
            }
            $cookies[] = [
                'name' => $name,
                'value' => $_COOKIE[$name],
                'domain' => $domain,
                'path' => '/',
                'secure' => parse_url($sourceUrl, PHP_URL_SCHEME) === 'https',
                'samesite' => 'Lax',
            ];
        }

        return $cookies;
    }

    /** The PDF cached for an anonymous visitor, or null. */
    public function cached(string $cachePath): ?string
    {
        return $this->storage->fileExists($cachePath) ? $this->storage->read($cachePath) : null;
    }

    /** Keep an anonymous visitor's PDF for the next one; failing to is no error, it is printed again. */
    public function cache(string $cachePath, string $pdf): bool
    {
        return $this->storage->storeDerived($cachePath, $pdf);
    }

    /**
     * The page at $sourceUrl, printed by the headless browser once its layout says it is ready.
     *
     * @param list<array<string, mixed>> $cookies
     *
     * @throws ExceptionWithHtml when the browser fails, with what it had loaded
     * @throws \RuntimeException when there is no browser to print with
     */
    public function printToPdf(string $sourceUrl, string $uuid = '', array $cookies = []): string
    {
        $chromium = $this->chromiumPath();
        if ($chromium === '') {
            throw new \RuntimeException("Path '{$this->configuredPath()}' is not executable", 2);
        }

        return $this->storage->withTemporaryFile('pdf', function (string $file) use ($chromium, $sourceUrl, $uuid, $cookies): string {
            $this->printWith($chromium, $sourceUrl, $file, $uuid, $cookies);

            return (string)file_get_contents($file);
        });
    }

    /**
     * @param list<array<string, mixed>> $cookies
     */
    private function printWith(string $chromium, string $sourceUrl, string $file, string $uuid, array $cookies): void
    {
        $options = $this->params->has('htmltopdf_options') ? $this->params->get('htmltopdf_options') : [];
        $options = is_array($options) ? $options : [];
        $page = null;
        $browser = null;
        try {
            $browser = (new BrowserFactory($chromium))->createBrowser($options);
            $this->setValueInSession($uuid, self::SESSION_BROWSER_READY, 1);
            $page = $browser->createPage();
            $this->setValueInSession($uuid, self::SESSION_PAGE_STATUS, 1);

            $browserCookies = $this->browserCookies($cookies);
            if ($browserCookies !== []) {
                $page->setCookies(new CookiesCollection($browserCookies))->await();
            }

            $budget = max(30000, (int)$this->stringParam('page_load_timeout'));
            $deadline = microtime(true) + $budget / 1000;
            set_time_limit((int)ceil($budget / 1000) + 30);

            $page->navigate($sourceUrl)->waitForNavigation(Page::LOAD, $budget);
            $this->addValueInSession($uuid, self::SESSION_PAGE_STATUS, 2);

            $page->evaluate('__is_yw_publication_ready()')->getReturnValue(max(5000, (int)(($deadline - microtime(true)) * 1000)));
            $this->addValueInSession($uuid, self::SESSION_PAGE_STATUS, 4);

            set_time_limit(30);
            $page->pdf([
                'printBackground' => true,
                'displayHeaderFooter' => true,
                'preferCSSPageSize' => true,
            ])->saveToFile($file);
            $this->setValueInSession($uuid, self::SESSION_PDF_CREATED, 1);
            $browser->close();
        } catch (\Throwable $e) {
            $html = '';
            if ($page !== null && !$e instanceof OperationTimedOut) {
                try {
                    $html = (string)$page->evaluate('document.documentElement.innerHTML')->getReturnValue(5000);
                } catch (\Throwable) {
                    $html = '';
                }
            }
            if ($browser !== null) {
                try {
                    $browser->close();
                } catch (\Throwable) {
                }
            }

            throw new ExceptionWithHtml($e->getMessage(), 0, $e, $html);
        }
    }

    /**
     * @param list<array<string, mixed>> $cookies
     *
     * @return list<Cookie>
     */
    private function browserCookies(array $cookies): array
    {
        $formatted = [];
        foreach ($cookies as $cookie) {
            foreach (['name', 'value', 'domain', 'path'] as $key) {
                if (empty($cookie[$key]) || !is_string($cookie[$key])) {
                    continue 2;
                }
            }
            $formatted[] = new Cookie([
                'name' => $cookie['name'],
                'value' => $cookie['value'],
                'domain' => $cookie['domain'],
                'path' => $cookie['path'],
                'httpOnly' => true,
                'secure' => !empty($cookie['secure']),
                'sameSite' => is_string($cookie['samesite'] ?? null) ? $cookie['samesite'] : 'Lax',
                'expires' => time() + 600,
            ]);
        }

        return $formatted;
    }

    /** The other wiki this one asks for PDFs when it cannot print them itself, '' when there is none. */
    public function externalServiceUrl(): string
    {
        return $this->stringParam('htmltopdf_service_url');
    }

    public function configuredPath(): string
    {
        return $this->stringParam('htmltopdf_path');
    }

    private function stringParam(string $name): string
    {
        $value = $this->params->has($name) ? $this->params->get($name) : '';

        return is_scalar($value) ? (string)$value : '';
    }

    /** The custom fiche template of a form, where the form designer writes it. */
    private function formTemplate(string $formId): string
    {
        $path = 'custom/templates/core/fiche-' . $formId . '.twig';

        return $this->storage->fileExists($path) ? $this->storage->read($path) : '';
    }

    /** What the printed layout is made of, so that a PDF printed before it changed is not served after. */
    private function assetsHash(): string
    {
        $extension = dirname(__DIR__);
        $parts = [];
        foreach (['javascripts/browser/print.js', 'javascripts/vendor/pagedjs/paged.esm.js', 'composer.json'] as $file) {
            if (is_file("$extension/$file")) {
                $parts[] = (string)file_get_contents("$extension/$file");
            }
        }
        foreach (array_merge(['custom/templates/publication/print-layouts/base.twig'], $this->storage->glob('custom/publication/*.css'), $this->storage->glob('custom/publication/print-layouts/*.css')) as $path) {
            if ($this->storage->fileExists($path)) {
                $parts[] = $this->storage->read($path);
            }
        }

        return sha1((string)json_encode($parts, JSON_INVALID_UTF8_SUBSTITUTE));
    }

    /** Start following a print job, forgetting the ones over two hours old. */
    public function prepareSession(string $uuid): void
    {
        if ($uuid === '') {
            return;
        }
        $this->sessionManager->reactivateSession();
        if (isset($_SESSION)) {
            $jobs = is_array($_SESSION[self::SESSION_KEY] ?? null) ? $_SESSION[self::SESSION_KEY] : [];
            $limit = time() - 7200;
            $jobs = array_filter($jobs, static fn ($job): bool => is_array($job) && (int)($job[self::SESSION_TIME_KEY] ?? 0) >= $limit);
            $jobs[$uuid] = [self::SESSION_TIME_KEY => time()];
            $_SESSION[self::SESSION_KEY] = $jobs;
        }
        $this->sessionManager->safeCloseSession();
    }

    public function setValueInSession(string $uuid, int $key, int $value): void
    {
        if ($uuid === '') {
            return;
        }
        $this->sessionManager->reactivateSession();
        if (isset($_SESSION[self::SESSION_KEY][$uuid]) && is_array($_SESSION[self::SESSION_KEY][$uuid])) {
            $_SESSION[self::SESSION_KEY][$uuid][$key] = $value;
        }
        $this->sessionManager->safeCloseSession();
    }

    public function addValueInSession(string $uuid, int $key, int $value): void
    {
        if ($uuid === '') {
            return;
        }
        $this->sessionManager->reactivateSession();
        if (isset($_SESSION[self::SESSION_KEY][$uuid][$key])) {
            $_SESSION[self::SESSION_KEY][$uuid][$key] = (int)$_SESSION[self::SESSION_KEY][$uuid][$key] + $value;
        }
        $this->sessionManager->safeCloseSession();
    }

    /**
     * Where a print job is at, for the status the PDF page polls.
     *
     * @return array<int, int>
     */
    public function getValuesInSession(string $uuid): array
    {
        $this->sessionManager->reactivateSession();
        $values = $_SESSION[self::SESSION_KEY][$uuid] ?? [];
        $this->sessionManager->safeCloseSession(false);

        return is_array($values) ? array_map('intval', $values) : [];
    }
}
