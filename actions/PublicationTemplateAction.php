<?php

namespace YesWiki\Publication\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Performable\RegisteredAction;

/** `{{publication-template}}`: where, in a template page, the entries `{{entries2publication}}` prints are put. */
class PublicationTemplateAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public const PLACEHOLDER = '<!--publication-template-placeholder-->';

    public static function performableName(): string
    {
        return 'publication-template';
    }

    public function components(): array
    {
        return [
            Component::for('publication-template')
                ->category(Category::Other)
                ->label(_t('AB_publication_publication_template_label'))
                ->hint(_t('AB_publication_publication_template_hint'))
                ->icon('file-text')
                ->adminOnly(),
        ];
    }

    public function run(): string
    {
        return self::PLACEHOLDER . "\n";
    }
}
