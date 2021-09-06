<?php

namespace YesWiki\Publication\Service;

define('PUBLICATION_LAYOUT_BOOK', 'book');
define('PUBLICATION_LAYOUT_FANZINE', 'fanzine');

class Publication {
  private $modes = [
    PUBLICATION_LAYOUT_BOOK,
    PUBLICATION_LAYOUT_FANZINE
  ];

  // Publication modes that require to load Paged.js to assemble the paged content
  private $pagedLayouts = [
    PUBLICATION_LAYOUT_BOOK
  ];

  private $fanzineLayouts = [
    // one page, folded in 4 (8 pages per sheet)
    // see https://en.wikibooks.org/wiki/Zine_Making/Putting_pages_together#Single-page_options
    'single-page',
    // many pages, folded in 2 (2 pages per sheet)
    // see https://support.epson.net/fun/articles/86
    'recto-folio',
    // many pages, folded in 2, recto-verso (4 pages per sheet)
    // see https://en.wikibooks.org/wiki/Zine_Making/Putting_pages_together#Folio_(folding_in_half)
    // 'recto-verso-folio',
    // many pages, folded in 4, recto-verso (8 pages per sheet)
    // see https://en.wikibooks.org/wiki/Zine_Making/Putting_pages_together#Quarto_(folding_in_quarter)
    // 'recto-verso-quarto',
    // many pages, folded in 6, recto verso (12 pages per sheet)
    // see https://en.wikibooks.org/wiki/Zine_Making/Putting_pages_together#Folding_into_six
    // 'recto-verso-sexto'
  ];

  private $defaultOptions = [
    // Publication options
    "publication" => [
      "title" => '',
      "description" => '',
      "authors" => ''
    ],
    "publication-hide-links-url" => '1',
    "publication-cover-image" => '',
    "publication-cover-page" => '0',
    "publication-mode" => 'book',
    // Book options
    "publication-book" => [
      "print-fold" => '0',
      "print-marks" => '0',
      "pagination" => 'bottom-center',
      "page-format" => 'A4',
      "page-orientation" => 'portrait',
    ],
    // Fanzine options
    "publication-fanzine" => [
      "layout" => 'single-page'
    ],
  ];

  public function isMode($mode) {
    return in_array($mode, $this->modes);
  }

  public function isPaged($layout) {
    return in_array($layout, $this->pagedLayouts);
  }

  private function convertToNewOptionsFormat($options) {
    // Publication Options
    if (isset($options['publication-title'])) {
      $options['publication']['title'] = $options['publication-title'];
      unset($options['publication-title']);
    }

    if (isset($options['publication-description'])) {
      $options['publication']['description'] = $options['publication-description'];
      unset($options['publication-description']);
    }

    if (isset($options['publication-author'])) {
      $options['publication']['authors'] = $options['publication-author'];
      unset($options['publication-author']);
    }

    // Book Options
    if (isset($options['publication-page-orientation'])) {
      $options['publication-book']['page-orientation'] = $options['publication-page-orientation'];
      unset($options['publication-page-orientation']);
    }

    if (isset($options['publication-page-format'])) {
      $options['publication-book']['page-format'] = $options['publication-page-format'];
      unset($options['publication-page-format']);
    }

    if (isset($options['publication-book-fold'])) {
      $options['publication-book']['print-fold'] = $options['publication-book-fold'];
      unset($options['publication-book-fold']);
    }

    if (isset($options['publication-print-marks'])) {
      $options['publication-book']['print-marks'] = $options['publication-print-marks'];
      unset($options['publication-print-marks']);
    }

    if (isset($options['publication-pagination'])) {
      $options['publication-book']['pagination'] = $options['publication-pagination'];
      unset($options['publication-pagination']);
    }

    return $options;
  }

  /**
   * @example PageTag?type=publication-end
   */
  public function getIncludeActionFromPageTag ($tag) {
    [$page, $qs] = array_pad(explode('?', $tag), 2, '');
    parse_str($qs, $params);

    return sprintf(
      '{{include page="%s" class="%s"%s}}' . "\n",
      $page,
      trim(implode(' ', $params)),
      isset($params['type']) ? ' type="'.$params['type'].'"' : ''
    );
  }

  public function getOptions(...$args) {
    $options = array_replace_recursive($this->defaultOptions, ...$args);
    return $this->convertToNewOptionsFormat($options);
  }

  public function getStyles($metadatas, $options = []) {
    $isDebug = $options['debug'] === 'yes';
    $mode = $metadatas['publication-mode'];

    return array_merge(
      // Common styles
      [
        "yeswiki-publication",
        "publication--" . $metadatas['publication-mode'],
        $isDebug ? 'debug' : '',
        // OPTION book-cover
        $metadatas['publication-cover-page'] === '1' ? "publication--has-cover" : '',
        // OPTION hide-links-from-print
        $metadatas['publication-hide-links-url'] === '1' ? "hide-links-url" : '',
      ],
      /* BOOK Styles */
      $mode === 'book' ? [
        // could be chosen, when creating an eBook
        "page-format--" . $metadatas['publication-book']['page-format'],
        // could be chosen when creating an eBook
        "page-orientation--" . $metadatas['publication-book']['page-orientation'],
        // OPTION book-fold
        $metadatas['publication-book']['print-fold'] === '1' ? "book-fold" : '',
        // OPTION show-print-marks
        $metadatas['publication-book']['print-marks'] === '1' ? "show-print-marks" : '',
        // OPTION show-print-marks
        "page-number-position--" . $metadatas['publication-book']['pagination'],

      ] : [],
      /* FANZINE Styles */
      $mode === 'fanzine' ? [
        "fanzine-" . $metadatas['publication-fanzine']['layout']
      ] : [],
    );
  }
}
