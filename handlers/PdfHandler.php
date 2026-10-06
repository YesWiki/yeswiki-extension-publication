<?php

namespace YesWiki\Publication\Handler;

use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Publication\Service\PdfHelper;

/** `/PageName/pdf`: the page that asks the PDF service for this page's PDF and saves it, or falls back to the browser's own printing. */
class PdfHandler extends YesWikiHandler implements RegisteredHandler
{
    private const OLD_SERVICE_PARAMETERS = ['url', 'urlPageTag', 'hash', 'refresh', 'forceNewFormat', 'via', 'template-page'];

    public static function performableName(): string
    {
        return 'pdf';
    }

    public function run(): string
    {
        $this->forwardOldServiceCall();
        $this->denyAccessUnlessGranted('read');

        return $this->renderFullPage('@publication/handler-pdf.twig', $this->pdfPage(false));
    }

    /**
     * What the PDF page shows; redirects to the browser's printing when no PDF service is reachable.
     *
     * @return array<string, mixed>
     */
    protected function pdfPage(bool $inIframe): array
    {
        $pdfHelper = $this->getService(PdfHelper::class);
        $request = $this->getRequest();
        ['pageTag' => $pageTag, 'sourceUrl' => $sourceUrl, 'hash' => $hash] = $pdfHelper->getSourceUrl($request->query->all(), $request->server->all());
        $external = $pdfHelper->externalServiceUrl();
        $local = $pdfHelper->canExecChromium() ? $this->getService(UrlFormatter::class)->href('', 'api/pdf/getPdf', null, false) : '';
        if ($local === '' && $external === '') {
            $this->getService(Redirector::class)->redirect($sourceUrl . (str_contains($sourceUrl, '?') ? '&' : '?') . 'browserPrintAfterRendered=1');
        }

        return [
            'isAdmin' => $this->getService(AclService::class)->isAdmin(),
            'isIframe' => $inIframe,
            'pageTag' => $pageTag,
            'sourceUrl' => $sourceUrl,
            'hash' => $hash,
            'urls' => ['local' => $local, 'external' => $external],
            'refresh' => in_array($request->query->get('refresh'), ['1', 'true'], true),
            'pageUrl' => $this->getService(UrlFormatter::class)->href($inIframe ? 'iframe' : '', $pageTag, null, false),
        ];
    }

    /** Another wiki asking this one, the old way, to print one of its pages: the API answers that now. */
    private function forwardOldServiceCall(): void
    {
        $query = $this->getRequest()->query->all();
        foreach (['url', 'urlPageTag', 'hash'] as $required) {
            if (empty($query[$required]) || !is_string($query[$required])) {
                return;
            }
        }
        if (\PHP_SAPI !== 'cli' && !headers_sent()) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Expose-Headers: Location, Slug, Accept, Content-Type');
        }
        $kept = array_filter(
            array_intersect_key($query, array_flip(self::OLD_SERVICE_PARAMETERS)),
            'is_scalar'
        );
        $this->getService(Redirector::class)->redirect(
            $this->getService(UrlFormatter::class)->href('', 'api/pdf/getPdf', $kept + ['fromOldPath' => '1'], false)
        );
    }
}
