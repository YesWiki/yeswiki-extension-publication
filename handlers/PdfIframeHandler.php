<?php

namespace YesWiki\Publication;

use YesWiki\Core\YesWikiHandler;
use YesWiki\Publication\Controller\PdfController;

class PdfIframeHandler extends YesWikiHandler
{
    public function run()
    {
        if ($this->wiki->UserIsAdmin() &&
            !in_array(
                'pdfiframe',
                is_array($this->params->get('allowed_methods_in_iframe')) ? $this->params->get('allowed_methods_in_iframe') : []
            )
        ) {
            // allow local ('self') and everyone (*) to allow display error message
            if (!$this->wiki->isCli() && !headers_sent()) {
                header("Content-Security-Policy: frame-ancestors 'self' *;");
            }

            return $this->displayInIframe($this->render('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('PUBLICATION_IFRAME_NOT_SET', [
                    'gererConfigLink' => "<a href=\"{$this->wiki->href('iframe', 'GererConfig')}\">GererConfig</a>"
                ])
            ]));
        }
        return $this->displayInIframe($this->getService(PdfController::class)->run(true));
    }

    protected function displayInIframe(string $content)
    {
        $output = <<<HTML
        <body class="yeswiki-iframe-body login-body">
            <div class="container">
                <div class="yeswiki-page-widget page-widget page" {$this->wiki->Format('{{doubleclic iframe="1"}}')}>
                    $content
                </div><!-- end .page-widget -->
            </div><!-- end .container -->
        HTML;
        // common footer for all iframe page

        $this->wiki->AddJavascriptFile('tools/templates/libs/vendor/iframeResizer.contentWindow.min.js');

        // on recupere les entetes html mais pas ce qu'il y a dans le body
        $header = explode('<body', $this->wiki->Header());
        $output = $header[0].$output;
        // on recupere juste les javascripts et la fin des balises body et html
        $output .= preg_replace('/^.+<script/Us', '<script', $this->wiki->Footer());

        return $output;
    }
}
