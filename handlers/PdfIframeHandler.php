<?php

namespace YesWiki\Publication\Handler;

use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateEngine;

/** `/PageName/pdfiframe`: the PDF page, inside an iframe embedding the wiki. */
class PdfIframeHandler extends PdfHandler
{
    public static function performableName(): string
    {
        return 'pdfiframe';
    }

    public function run(): string
    {
        $this->denyAccessUnlessGranted('read');
        $allowed = $this->getService(RuntimeConfig::class)->getValue('allowed_methods_in_iframe', []);
        if ($this->getService(AclService::class)->isAdmin() && is_array($allowed) && !in_array('pdfiframe', $allowed, true)) {
            if (\PHP_SAPI !== 'cli' && !headers_sent()) {
                header("Content-Security-Policy: frame-ancestors 'self' *;");
            }
            $content = $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('PUBLICATION_IFRAME_NOT_SET', [
                    'gererConfigLink' => '<a href="' . $this->getService(UrlFormatter::class)->href('', 'admin/config') . '">' . _t('PUBLICATION_CONFIGURATION') . '</a>',
                ]),
            ]);
        } else {
            $content = $this->render('@publication/handler-pdf.twig', $this->pdfPage(true));
        }
        $this->getService(AssetRegistry::class)->addJsFile('javascripts/vendor/iframe-resizer/iframeResizer.contentWindow.min.js');

        return $this->getService(TemplateEngine::class)->renderHead()
            . "<body class=\"yeswiki-iframe-body\">\n<div class=\"yw-container\">\n" . $content . "\n</div>\n</body>\n</html>";
    }
}
