<?php

namespace YesWiki\Publication\Controller;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\YesWikiController;
use YesWiki\Publication\Service\PdfHelper;
use YesWiki\Publication\Service\SessionManager;
use YesWiki\Wiki;

class PdfController extends YesWikiController
{
    protected $params;
    protected $pdfHelper;

    public function __construct(
        ParameterBagInterface $params,
        PdfHelper $pdfHelper,
        Wiki $wiki
    ) {
        $this->params = $params;
        $this->pdfHelper = $pdfHelper;
        $this->wiki = $wiki;
    }

    public function run(bool $inIframe = false)
    {
        if (!empty($_GET['url']) && is_string($_GET['url']) &&
            !empty($_GET['urlPageTag']) && is_string($_GET['urlPageTag']) &&
            !empty($_GET['hash']) && is_string($_GET['hash'])) {
            // redirect to api
            if (!method_exists($this->wiki, 'isCli') || !$this->wiki->isCli()) {
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Expose-Headers: Location, Slug, Accept, Content-Type');
            }
            $filteredParams = array_filter($_GET, function ($v, $k) {
                return in_array($k, ['url','urlPageTag','hash','refresh','forceNewFormat','via','template-page'], true) && is_scalar($v);
            }, ARRAY_FILTER_USE_BOTH);
            $this->wiki->redirect($this->wiki->href('', 'api/pdf/getPdf', $filteredParams + [
                'fromOldPath' => '1'
            ], false));
        }

        list(
            'pageTag'=>$pageTag,
            'sourceUrl'=>$sourceUrl,
            'hash'=>$hash,
        ) =
            $this->pdfHelper->getSourceUrl($_GET ?? [], $_SERVER ?? []);

        $method = $inIframe ? 'render' : 'renderInSquelette';

        return $this->$method('@publication/handler-pdf.twig', [
            'isAdmin' => $this->wiki->UserIsAdmin(),
            'isIframe' => $inIframe,
            'pageTag' => $pageTag,
            'sourceUrl' => $sourceUrl,
            'hash' => $hash,
            'urls' => [
                'local' => $this->pdfHelper->canExecChromium() ? $this->wiki->href('', 'api/pdf/getPdf') : '',
                'external' => empty($this->params->get('htmltopdf_service_url')) ? '' : $this->params->get('htmltopdf_service_url'),
            ],
            'refresh' => in_array($_GET['refresh'] ?? false, [1,"1",true,"true"], true),
        ]);
    }
}
