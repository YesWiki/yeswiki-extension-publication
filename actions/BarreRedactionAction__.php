<?php

namespace YesWiki\Publication;

use YesWiki\Core\YesWikiAction;

class BarreRedactionAction__ extends YesWikiAction
{
    public function run()
    {
        $this->output = preg_replace(
            '#</div>#',
            $this->render(
                '@bazar/entries/_publication_button.twig',
                [
                    'forPage'=>true
                ]
            )."\n".'</div>',
            $this->output
        );
    }
}
