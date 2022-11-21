<?php

namespace YesWiki\Publication;

use YesWiki\Core\YesWikiHandler;
use YesWiki\Publication\Service\PdfHelper;

class PdfHandler extends YesWikiHandler
{
    public function run()
    {
        // get service
        $pdfHelper = $this->getService(PdfHelper::class);

        if (!empty($_GET['url']) && is_string($_GET['url']) &&
            !empty($_GET['urlPageTag']) && is_string($_GET['urlPageTag']) &&
            !empty($_GET['hash']) && is_string($_GET['hash'])) {
            // redirect to api
            if (!method_exists($this->wiki, 'isCli') || !$this->wiki->isCli()) {
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Expose-Headers: Location, Slug, Accept, Content-Type');
            }
            $this->wiki->redirect($this->wiki->href('', 'api/pdf/getPdf', [
                'url' => $_GET['url'],
                'urlPageTag' => $_GET['urlPageTag'],
                'hash' => $_GET['hash'],
                'fromOldPath' => '1',
            ], false));
        }


        list(
            'pageTag'=>$pageTag,
            'sourceUrl'=>$sourceUrl,
            'hash'=>$hash,
        ) =
            $pdfHelper->getSourceUrl($_GET ?? [], $_SERVER ?? []);

        return $this->renderInSquelette('@publication/handler-pdf.twig', [
            'isAdmin' => $this->wiki->UserIsAdmin(),
            'pageTag' => $pageTag,
            'sourceUrl' => $sourceUrl,
            'hash' => $hash,
            'urls' => [
                'local' => $pdfHelper->canExecChromium() ? $this->wiki->href('', 'api/pdf/getPdf') : '',
                'external' => empty($this->params->get('htmltopdf_service_url')) ? '' : $this->params->get('htmltopdf_service_url'),
            ],
        ]);
    }
}
