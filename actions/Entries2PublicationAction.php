<?php

namespace YesWiki\Publication\Action;

use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\AliasesPerformable;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;

/** `{{entries2publication}}`: a button printing the entries the page's list shows, with the facets the reader checked. */
class Entries2PublicationAction extends YesWikiAction implements RegisteredAction, AliasesPerformable, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'entries2publication';
    }

    public static function performableAliases(): array
    {
        return ['bazar2publication' => []];
    }

    public function components(): array
    {
        return [
            Component::for('entries2publication')
                ->category(Category::Lists)
                ->label(_t('AB_publication_entries2publication_label'))
                ->hint(_t('AB_publication_entries2publication_hint'))
                ->icon('printer')
                ->adminOnly()
                ->settings(
                    Setting::text('title')->label(_t('AB_publication_entries2publication_title_label')),
                    Setting::icon('icon')->label(_t('AB_publication_entries2publication_icon_label')),
                    Setting::cssClass('class')->label(_t('AB_publication_entries2publication_class_label')),
                    Setting::page('templatepage')->label(_t('AB_publication_entries2publication_templatepage_label')),
                    Setting::text('excludedfields')
                        ->label(_t('AB_publication_entries2publication_exludedfields_label'))
                        ->hint(_t('AB_publication_entries2publication_exludedfields_hint')),
                ),
        ];
    }

    public function formatArguments($arg): array
    {
        return [
            'title' => is_string($arg['title'] ?? null) && $arg['title'] !== '' ? $arg['title'] : _t('PUBLICATION_CREATE_FROM_BAZAR_RESULTS'),
            'icon' => is_string($arg['icon'] ?? null) && $arg['icon'] !== '' ? $arg['icon'] : 'printer',
            'class' => is_string($arg['class'] ?? null) ? $arg['class'] : '',
            'templatepage' => is_string($arg['templatepage'] ?? null) ? trim($arg['templatepage']) : '',
            'excludedfields' => array_values(array_filter(array_map('trim', $this->formatArray($arg['excludedfields'] ?? [])))),
        ];
    }

    public function run(): string
    {
        $templatePage = $this->arguments['templatepage'] === ''
            ? null
            : $this->getService(PageManager::class)->getOne($this->arguments['templatepage']);

        $query = $this->getService(UrlFormatter::class)->currentQuery();
        unset($query['pageID']);
        $query = array_merge($query, array_filter([
            'via' => 'entrylist',
            'template-page' => $this->arguments['templatepage'],
            'excludeFields' => implode(',', $this->arguments['excludedfields']),
            'browserPrintAfterRendered' => '1',
        ]));

        return $this->render('@publication/entries2publication.twig', [
            'href' => $this->getService(UrlFormatter::class)->href(
                'preview',
                $this->getService(PageContext::class)->getTag(),
                http_build_query($query, '', '&', PHP_QUERY_RFC3986),
                false
            ),
            'options' => $this->arguments,
            'templatePageMissing' => $this->arguments['templatepage'] !== '' && $templatePage === null,
        ]);
    }
}
