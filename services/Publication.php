<?php

namespace YesWiki\Publication\Service;

/** A publication's options: what the generator saves in a page's Metadata, merged over the defaults. */
class Publication
{
    public const LAYOUT_BOOK = 'book';
    public const LAYOUT_FANZINE = 'fanzine';
    public const LAYOUT_PAGE = 'page';

    private const MODES = [self::LAYOUT_BOOK, self::LAYOUT_FANZINE, self::LAYOUT_PAGE];

    private const PAGED_LAYOUTS = [self::LAYOUT_BOOK, self::LAYOUT_PAGE];

    public const FANZINE_LAYOUTS = ['single-page', 'recto-folio'];

    private const NESTED = ['publication', 'publication-book', 'publication-fanzine'];

    private const DEFAULTS = [
        'publication' => [
            'title' => '',
            'description' => '',
            'authors' => '',
        ],
        'publication-hide-links-url' => '1',
        'publication-cover-image' => '',
        'publication-cover-page' => '0',
        'publication-mode' => self::LAYOUT_BOOK,
        'publication-book' => [
            'print-fold' => '0',
            'print-marks' => '0',
            'pagination' => 'bottom-center',
            'page-format' => 'A4',
            'page-orientation' => 'portrait',
        ],
        'publication-fanzine' => [
            'layout' => 'single-page',
        ],
    ];

    private const LEGACY_KEYS = [
        'publication-title' => ['publication', 'title'],
        'publication-description' => ['publication', 'description'],
        'publication-author' => ['publication', 'authors'],
        'publication-page-orientation' => ['publication-book', 'page-orientation'],
        'publication-page-format' => ['publication-book', 'page-format'],
        'publication-book-fold' => ['publication-book', 'print-fold'],
        'publication-print-marks' => ['publication-book', 'print-marks'],
        'publication-pagination' => ['publication-book', 'pagination'],
    ];

    public function isMode(mixed $mode): bool
    {
        return in_array($mode, self::MODES, true);
    }

    /**
     * Whether a page's Metadata holds a publication the generator saved.
     *
     * @param array<string, mixed> $metadata
     */
    public function isPublication(array $metadata): bool
    {
        if (isset($metadata['publication-title'])) {
            return true;
        }

        return is_array($metadata['publication'] ?? null) && trim((string)($metadata['publication']['title'] ?? '')) !== '';
    }

    public function isPaged(string $layout): bool
    {
        return in_array($layout, self::PAGED_LAYOUTS, true);
    }

    /** The `{{include}}` call a selected item stands for: `PageTag` or `PageTag?type=publication-end`. */
    public function getIncludeActionFromPageTag(string $tag): string
    {
        [$page, $queryString] = array_pad(explode('?', $tag, 2), 2, '');
        parse_str($queryString, $params);
        $params = array_filter($params, 'is_string');

        return sprintf(
            '{{include page="%s" class="%s"%s}}' . "\n",
            str_replace('"', '', trim($page)),
            str_replace('"', '', trim(implode(' ', $params))),
            isset($params['type']) ? ' type="' . str_replace('"', '', $params['type']) . '"' : ''
        );
    }

    /**
     * The defaults, overridden by each array given in turn, with options saved in the old flat format moved to their place.
     *
     * @return array<string, mixed>
     */
    public function getOptions(mixed ...$sources): array
    {
        $options = self::DEFAULTS;
        foreach ($sources as $source) {
            if (is_array($source)) {
                $options = array_replace_recursive($options, array_intersect_key($this->fromLegacyKeys($source), self::DEFAULTS));
            }
        }
        foreach (self::NESTED as $key) {
            if (!is_array($options[$key] ?? null)) {
                $options[$key] = self::DEFAULTS[$key];
            }
            $options[$key] = array_map(static fn ($value) => is_scalar($value) ? (string)$value : '', $options[$key]);
        }
        foreach ($options as $key => $value) {
            if (!in_array($key, self::NESTED, true)) {
                $options[$key] = is_scalar($value) ? (string)$value : '';
            }
        }

        return $options;
    }

    /**
     * The options kept in a page's Metadata, and nothing else a form post carried.
     *
     * @param array<string, mixed> $post
     *
     * @return array<string, mixed>
     */
    public function storable(array $post): array
    {
        return $this->getOptions($post);
    }

    /**
     * The classes the print layout's body takes from the options.
     *
     * @param array<string, mixed> $options as getOptions() returns them
     *
     * @return list<string>
     */
    public function getStyles(array $options, bool $debug = false): array
    {
        $mode = (string)$options['publication-mode'];
        $book = $options['publication-book'];
        $classes = [
            'yeswiki-publication',
            'publication--' . $mode,
            $debug ? 'debug' : '',
            $options['publication-cover-page'] === '1' ? 'publication--has-cover' : '',
            $options['publication-hide-links-url'] === '1' ? 'hide-links-url' : '',
        ];
        if ($mode === self::LAYOUT_BOOK) {
            $classes[] = 'page-format--' . $book['page-format'];
            $classes[] = 'page-orientation--' . $book['page-orientation'];
            $classes[] = $book['print-fold'] === '1' ? 'book-fold' : '';
            $classes[] = $book['print-marks'] === '1' ? 'show-print-marks' : '';
            $classes[] = 'page-number-position--' . $book['pagination'];
        }
        if ($mode === self::LAYOUT_FANZINE) {
            $classes[] = 'fanzine-' . $options['publication-fanzine']['layout'];
        }

        return array_values(array_filter($classes, static fn (string $class): bool => $class !== ''));
    }

    /**
     * @param array<mixed> $options
     *
     * @return array<mixed>
     */
    private function fromLegacyKeys(array $options): array
    {
        foreach (self::LEGACY_KEYS as $legacy => [$group, $key]) {
            if (array_key_exists($legacy, $options)) {
                if (!is_array($options[$group] ?? null)) {
                    $options[$group] = [];
                }
                $options[$group][$key] = $options[$legacy];
                unset($options[$legacy]);
            }
        }

        return $options;
    }
}
