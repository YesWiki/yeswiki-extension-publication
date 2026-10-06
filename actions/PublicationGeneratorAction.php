<?php

namespace YesWiki\Publication\Action;

use Symfony\Component\String\Slugger\AsciiSlugger;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Content\Entity\FieldRole;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FieldRoleResolver;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Publication\Service\Publication;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Search\Service\TagsManager;

/** `{{publicationgenerator}}`: pick pages and entries, order them, and save them as an ebook page or a newsletter entry. */
class PublicationGeneratorAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    private const DEPRECATED = [
        'ebookpagenameprefix' => 'pagenameprefix',
        'fields' => 'readonly',
        'ebookstart' => 'pagestart',
        'ebookend' => 'pageend',
        'publicationstart' => 'pagestart',
        'publicationend' => 'pageend',
    ];

    private const ACCEPTED_TAGS = '<h1><h2><h3><h4><h5><h6><hr><br><span><blockquote><i><u><b><strong>'
        . '<ol><ul><li><small><div><p><a><table><tr><th><td><img><figure><caption><iframe><style>';

    private const BLANK_PAGE = '{{blankpage}}';

    public static function performableName(): string
    {
        return 'publicationgenerator';
    }

    public function components(): array
    {
        $prefix = 'AB_publication_publicationgenerator_';

        return [
            Component::for('publicationgenerator')
                ->category(Category::Other)
                ->label(_t($prefix . 'label'))
                ->hint(_t($prefix . 'hint'))
                ->icon('book')
                ->adminOnly()
                ->settings(
                    Setting::choice('outputformat', [
                        'ebook' => _t($prefix . 'outputformat_ebook'),
                        'newsletter' => _t($prefix . 'outputformat_newsletter'),
                    ])->label(_t($prefix . 'outputformat_label'))->default('ebook'),
                    Setting::form('formid')->label(_t($prefix . 'formid_label'))->showIf(['outputformat' => 'newsletter']),
                    Setting::text('groupselector')->label(_t($prefix . 'groupselector_label'))->hint(_t($prefix . 'groupselector_hint')),
                    Setting::text('titles')->label(_t($prefix . 'titles_label'))->showIf('groupselector'),
                    Setting::page('pagestart')->label(_t($prefix . 'pagestart_label'))->hint(_t($prefix . 'pagestart_hint')),
                    Setting::page('pageend')->label(_t($prefix . 'pageend_label'))->hint(_t($prefix . 'pageend_hint')),
                    Setting::text('pagenameprefix')->label(_t($prefix . 'pagenameprefix_label'))->hint(_t($prefix . 'pagenameprefix_hint'))->showIf(['outputformat' => 'ebook']),
                    Setting::checkbox('readonly')->label(_t($prefix . 'readonly_label'))->hint(_t($prefix . 'readonly_hint'))->showIf(['outputformat' => 'ebook']),
                    Setting::url('coverimage')->label(_t($prefix . 'coverimage_label'))->hint(_t($prefix . 'coverimage_hint')),
                    Setting::text('title')->label(_t($prefix . 'title_label'))->hint(_t($prefix . 'title_hint')),
                    Setting::text('desc')->label(_t($prefix . 'desc_label'))->hint(_t($prefix . 'desc_hint')),
                    Setting::text('author')->label(_t($prefix . 'author_label'))->hint(_t($prefix . 'author_hint')),
                    Setting::text('chapterpages')->label(_t($prefix . 'chapterpages_label'))->hint(_t($prefix . 'chapterpages_hint')),
                ),
        ];
    }

    public function formatArguments($arg): array
    {
        $deprecated = array_intersect_key($arg, self::DEPRECATED);
        $text = static fn (string $key, string $default = ''): string => is_string($arg[$key] ?? null) ? $arg[$key] : $default;
        $formId = is_scalar($arg['formid'] ?? null) ? (string)$arg['formid'] : '';

        return [
            'deprecated' => array_keys($deprecated),
            'outputformat' => strtolower($text('outputformat', 'ebook')),
            'formid' => ctype_digit($formId) && (int)$formId > 0 ? $formId : '',
            'readonly' => in_array($arg['readonly'] ?? null, ['', '1', 'true', true, 1], true),
            'pagestart' => $text('pagestart'),
            'pageend' => $text('pageend'),
            'pagenameprefix' => $text('pagenameprefix', 'Ebook'),
            'coverimage' => $text('coverimage'),
            'title' => $text('title'),
            'desc' => $text('desc'),
            'authors' => $text(isset($arg['author']) ? 'author' : 'authors'),
            'chapterpages' => array_values(array_filter(array_map('trim', $this->formatArray($arg['chapterpages'] ?? [])))),
            'titles' => array_map('trim', $this->formatArray($arg['titles'] ?? [])),
            'groupselector' => $text('groupselector'),
        ];
    }

    public function run(): string
    {
        if ($this->isNewsletter() && $this->arguments['formid'] === '') {
            return '<div class="yw-alert yw-alert--danger">' . _t('PUBLICATION_MISSING_NEWSLETTER_FORM') . '</div>';
        }

        $messages = array_values(array_map(
            fn (string $old): array => [
                'type' => 'warning',
                'message' => _t('PUBLICATION_PARAMETER_DEPRECATED', ['oldName' => $old, 'newName' => self::DEPRECATED[$old]]),
            ],
            $this->arguments['deprecated']
        ));

        $existing = $this->existingPublication();
        $request = $this->getRequest();
        if ($request->isMethod('POST')) {
            $failure = $this->managePost($request->request->all(), $messages, $existing['tag']);
            if ($failure !== null) {
                return $failure;
            }
        }

        $pageManager = $this->getService(PageManager::class);
        $pageContext = $this->getService(PageContext::class);
        $start = $existing['start'] ?? $this->arguments['pagestart'];
        $end = $existing['end'] ?? $this->arguments['pageend'];

        return $this->render('@publication/publicationgenerator.twig', [
            'messages' => $messages,
            'groups' => $this->getGroups(),
            'areParamsReadonly' => $this->arguments['readonly'],
            'publicationStart' => $start === '' ? null : $pageManager->getOne($start),
            'publicationEnd' => $end === '' ? null : $pageManager->getOne($end),
            'metadatas' => $this->getService(Publication::class)->getOptions(
                [
                    'publication-cover-image' => $this->arguments['coverimage'],
                    'publication' => [
                        'title' => $this->arguments['title'],
                        'description' => $this->arguments['desc'],
                        'authors' => $this->arguments['authors'],
                    ],
                ],
                $pageContext->getMetadata(),
                $request->isMethod('POST') ? $request->request->all() : []
            ),
            'selectedPages' => $existing['selected'],
            'chapterCoverPages' => $this->getChapterCoverPages(),
            'url' => $this->getService(UrlFormatter::class)->href('', $pageContext->getTag(), null, false),
            'name' => $this->isNewsletter() ? _t('PUBLICATION_NEWSLETTER') : _t('PUBLICATION_EBOOK'),
            'outputFormat' => $this->arguments['outputformat'],
        ]);
    }

    /**
     * The page being shown, when it already is an ebook this generator made: its tag, its first and last pages and what lies between.
     *
     * @return array{tag: string, start: ?string, end: ?string, selected: list<array{tag: string, label: string, blank: bool}>}
     */
    private function existingPublication(): array
    {
        $pageContext = $this->getService(PageContext::class);
        $none = ['tag' => '', 'start' => null, 'end' => null, 'selected' => []];
        if (!$this->isEbook() || !$this->getService(Publication::class)->isPublication($pageContext->getMetadata())) {
            return $none;
        }
        $content = PageBody::content(($pageContext->getPage() ?? [])['body'] ?? []);
        $found = ['tag' => $pageContext->getTag(), 'start' => null, 'end' => null, 'selected' => []];
        preg_match_all('/\{\{\s*(?:blankpage\s*|include\s+page="([^"]*)"[^}]*?(?:type="([^"]*)")?[^}]*)\}\}/U', $content, $calls, PREG_SET_ORDER);
        foreach ($calls as $call) {
            $tag = $call[1] ?? '';
            $type = $call[2] ?? '';
            if ($tag === '') {
                $found['selected'][] = ['tag' => self::BLANK_PAGE, 'label' => _t('PUBLICATION_BLANK_PAGE'), 'blank' => true];
            } elseif ($type === 'publication-start') {
                $found['start'] = $tag;
            } elseif ($type === 'publication-end') {
                $found['end'] = $tag;
            } else {
                $found['selected'][] = ['tag' => $tag, 'label' => $tag, 'blank' => false];
            }
        }

        return $found;
    }

    /**
     * @return array<string, array<string, mixed>|null>
     */
    private function getChapterCoverPages(): array
    {
        $pages = [];
        foreach ($this->arguments['chapterpages'] as $tag) {
            $pages[$tag] = $this->getService(PageManager::class)->getOne($tag);
        }

        return array_filter($pages);
    }

    /**
     * What may be picked, in groups: pages, possibly by keyword, and the entries of a form, possibly by query.
     *
     * @return list<array{type: string, name: string, items: list<array{tag: string, label: string}>}>
     */
    private function getGroups(): array
    {
        if ($this->arguments['groupselector'] === '') {
            return [
                ['type' => 'pages', 'name' => _t('PUBLICATION_WIKI_PAGES'), 'items' => $this->pages([])],
                ['type' => 'entries', 'name' => _t('PUBLICATION_ENTRIES'), 'items' => $this->entries('', [])],
            ];
        }

        $groups = [];
        preg_match_all('/(\d+|pages)(\(([^()]*)\))?/m', $this->arguments['groupselector'], $matches);
        foreach ($matches[1] as $i => $selector) {
            $title = $this->arguments['titles'][$i] ?? '';
            $filter = $matches[3][$i] ?? '';
            if ($selector === 'pages') {
                $groups[] = [
                    'type' => 'pages',
                    'name' => $title !== '' ? $title : _t('PUBLICATION_WIKI_PAGES'),
                    'items' => $this->pages(array_values(array_filter(array_map('trim', explode(',', $filter))))),
                ];
                continue;
            }
            $form = $this->getService(FormManager::class)->getOne($selector);
            $groups[] = [
                'type' => 'entries',
                'name' => $title !== '' ? $title : (string)($form['label'] ?? $selector),
                'items' => $this->entries($selector, $this->query($filter)),
            ];
        }

        return $groups;
    }

    /**
     * `bf_a=x|bf_b=y` as the query array the entry search takes, repeated fields joined by a comma.
     *
     * @return array<string, string>
     */
    private function query(string $filter): array
    {
        $query = [];
        foreach (array_filter(explode('|', $filter), static fn (string $part): bool => trim($part) !== '') as $condition) {
            [$field, $value] = array_pad(explode('=', $condition, 2), 2, '');
            $field = trim($field);
            $query[$field] = isset($query[$field]) ? $query[$field] . ',' . trim($value) : trim($value);
        }

        return $query;
    }

    /**
     * Readable pages, those carrying every keyword given when there are some.
     *
     * @param list<string> $keywords
     *
     * @return list<array{tag: string, label: string}>
     */
    private function pages(array $keywords): array
    {
        $rows = $this->getService(TagsManager::class)->getPagesByTags(implode(',', $keywords), 'wiki', '', 'alpha');
        $items = [];
        foreach ($rows as $row) {
            if (($row['type'] ?? PageType::PAGE) !== PageType::PAGE || !empty($row['parent'])) {
                continue;
            }
            $tag = (string)$row['tag'];
            $items[$tag] = ['tag' => $tag, 'label' => $tag];
        }
        ksort($items, SORT_NATURAL | SORT_FLAG_CASE);

        return array_values($items);
    }

    /**
     * Readable entries, of one form or of all, in the order of their titles.
     *
     * @param array<string, string> $query
     *
     * @return list<array{tag: string, label: string}>
     */
    private function entries(string $formId, array $query): array
    {
        $params = ['queries' => $query];
        if ($formId !== '') {
            $params['formsIds'] = [$formId];
        }
        $items = array_map(
            static fn (array $entry): array => [
                'tag' => (string)($entry['tag'] ?? ''),
                'label' => (string)($entry['title'] ?? $entry['tag'] ?? ''),
            ],
            array_values($this->getService(EntryManager::class)->search($params, true))
        );
        usort($items, static fn (array $a, array $b): int => strcoll(mb_strtolower($a['label']), mb_strtolower($b['label'])));

        return $items;
    }

    private function isEbook(): bool
    {
        return $this->arguments['outputformat'] === 'ebook';
    }

    private function isNewsletter(): bool
    {
        return $this->arguments['outputformat'] === 'newsletter';
    }

    /**
     * What the form submitted: an ebook page, saved and redirected to, or a newsletter entry.
     *
     * @param array<string, mixed>                       $post
     * @param list<array{type: string, message: string}> $messages
     *
     * @return string|null an error to show instead of the generator
     */
    private function managePost(array $post, array &$messages, string $existingTag): ?string
    {
        if (!$this->checkPostValues($post, $messages)) {
            return null;
        }
        $items = array_values(array_filter($post['page'], 'is_string'));

        if ($this->isNewsletter()) {
            $this->createNewsletter($post, $items, $messages);

            return null;
        }
        if (!$this->isEbook()) {
            return null;
        }

        $cover = $post['publication-cover-image'] ?? '';
        if ($cover !== '' && (!is_string($cover) || preg_match('/\.(jpe?g|png|svg|webp)$/i', $cover) !== 1)) {
            $messages[] = ['type' => 'danger', 'message' => _t('PUBLICATION_NOT_IMAGE_FILE')];

            return null;
        }

        $pageManager = $this->getService(PageManager::class);
        $tag = $existingTag !== '' ? $existingTag : $pageManager->suggestFreeTag(
            (new AsciiSlugger())->slug($this->arguments['pagenameprefix'] . ' ' . $post['publication']['title'])->lower()->toString()
        );
        $content = implode('', array_map(
            fn (string $item): string => preg_match('/\{\{\s*blankpage\s*\}\}/', $item) === 1
                ? self::BLANK_PAGE . "\n"
                : $this->getService(Publication::class)->getIncludeActionFromPageTag($item),
            $items
        ));

        if ($pageManager->save($tag, [PageBody::CONTENT => $content]) !== 0) {
            return '<div class="yw-alert yw-alert--danger">' . _t('PUBLICATION_EBOOK_PAGE_CREATION_FAILED') . '</div>';
        }
        $pageManager->setMetadata($tag, $this->getService(Publication::class)->storable($post));
        Flash::success(_t('PUBLICATION_EBOOK_PAGE_CREATED'));
        $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', $tag, null, false));
    }

    /**
     * @param array<string, mixed>                       $post
     * @param list<string>                               $items
     * @param list<array{type: string, message: string}> $messages
     */
    private function createNewsletter(array $post, array $items, array &$messages): void
    {
        $formatter = $this->getService(MarkdownFormatterService::class);
        $html = implode('', array_map(
            fn (string $item): string => $formatter->format(
                preg_match('/\{\{\s*blankpage\s*\}\}/', $item) === 1 ? self::BLANK_PAGE . "\n" : $this->getService(Publication::class)->getIncludeActionFromPageTag($item)
            ),
            $items
        ));
        $form = $this->getService(FormManager::class)->getOne($this->arguments['formid']);
        $descriptionField = $this->getService(FieldRoleResolver::class)->propertyName($form, FieldRole::DESCRIPTION) ?? 'bf_description';

        $entry = $this->getService(EntryManager::class)->create($this->arguments['formid'], [
            'bf_titre' => trim($this->arguments['outputformat'] . ' ' . $post['publication']['title']),
            $descriptionField => (string)($post['publication']['description'] ?? ''),
            'bf_author' => (string)($post['publication']['authors'] ?? ''),
            'bf_content' => strip_tags($html, self::ACCEPTED_TAGS),
            'antispam' => 1,
        ]);
        $messages[] = empty($entry)
            ? ['type' => 'warning', 'message' => _t('PUBLICATION_NEWSLETTER_NOT_CREATED')]
            : ['type' => 'success', 'message' => _t('PUBLICATION_NEWSLETTER_CREATED')];
    }

    /**
     * @param array<string, mixed>                       $post
     * @param list<array{type: string, message: string}> $messages
     */
    private function checkPostValues(array $post, array &$messages): bool
    {
        if (($post['antispam'] ?? null) != 1) {
            $messages[] = ['type' => 'danger', 'message' => _t('PUBLICATION_SPAM_RISK')];

            return false;
        }
        if (!is_array($post['page'] ?? null) || $post['page'] === []
            || !is_array($post['publication'] ?? null) || !is_string($post['publication']['title'] ?? null) || trim($post['publication']['title']) === '') {
            $messages[] = ['type' => 'danger', 'message' => _t('PUBLICATION_NO_PAGE_FOUND')];

            return false;
        }

        return true;
    }
}
