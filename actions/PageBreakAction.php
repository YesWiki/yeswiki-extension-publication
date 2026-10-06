<?php

namespace YesWiki\Publication\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Performable\RegisteredAction;

/** `{{pagebreak}}`: what follows starts on a new page once printed. */
class PageBreakAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'pagebreak';
    }

    public function components(): array
    {
        return [
            Component::for('pagebreak')
                ->category(Category::Writing)
                ->label(_t('AB_publication_pagebreak_label'))
                ->hint(_t('AB_publication_pagebreak_hint'))
                ->icon('separator-horizontal')
                ->adminOnly(),
        ];
    }

    public function run(): string
    {
        return "\n<hr class=\"pagebreak\" aria-hidden=\"true\">\n";
    }
}
