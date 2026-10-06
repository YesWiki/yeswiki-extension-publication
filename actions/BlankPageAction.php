<?php

namespace YesWiki\Publication\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Performable\RegisteredAction;

/** `{{blankpage}}`: an empty page in a printed publication. */
class BlankPageAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'blankpage';
    }

    public function components(): array
    {
        return [
            Component::for('blankpage')
                ->category(Category::Writing)
                ->label(_t('AB_publication_blanckpage_label'))
                ->hint(_t('AB_publication_blanckpage_hint'))
                ->icon('file')
                ->adminOnly(),
        ];
    }

    public function run(): string
    {
        return "\n<div class=\"blank-page\" aria-label=\"" . htmlspecialchars(_t('PUBLICATION_BLANK_PAGE')) . "\"></div>\n";
    }
}
