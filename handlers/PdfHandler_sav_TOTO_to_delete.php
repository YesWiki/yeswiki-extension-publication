<?php

namespace YesWiki\Publication;

use Exception;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Publication\Exception\ExceptionWithHtml;
use YesWiki\Publication\Service\PdfHelper;

class PdfHandler extends YesWikiHandler
{
    protected $pdfHelper ;

    public function run()
    {
        ob_start(); // to catch all message that could be put in first lines of pdf file

        // get Services
        $this->pdfHelper = $this->getService(PdfHelper::class);

        try {
            $this->checkPdfPath();
            $this->checkDomain($_GET ?? [], $_SERVER ?? []);

            list(
                'pageTag'=>$pageTag,
                'sourceUrl'=>$sourceUrl,
                'hash'=>$hash,
                'dlFilename'=>$dlFilename,
                'fullFilename'=>$fullFilename
            ) =
                $this->pdfHelper->getFullFileName($_GET ?? [], $_SERVER ?? []);
            $file_exists = file_exists($fullFilename);
            if (($this->wiki->UserIsAdmin() && isset($_GET['print-debug']))
                    || !$file_exists
                    || ($file_exists && isset($_GET['refresh']) && $_GET['refresh']==1)
            ) {
                $this->redirectToPdfServiceIfNeeded($sourceUrl, $hash);
                $this->pdfHelper->useBrowserToCreatePdfFromPage($sourceUrl, $fullFilename);
            }
            $this->returnFile($fullFilename, $dlFilename);
        } catch (ExceptionWithHtml $ex) {
            $output = $this->renderExceptionForBrowser($ex, $sourceUrl);
            return $this->Header().$output.$this->Footer()."\n";
        } catch (Exception $th) {
            if ($th->getCode() == 1) {
                return $this->renderInSquelette('@templates/alert-message.twig', [
                    'type' => 'danger',
                    'message' => $th->getMessage()
                ]);
            }
            throw $th;
        }
    }

    /**
     * @throws Exception with code 1 if output content in exception Message
     */
    protected function checkPdfPath()
    {
        if (!$this->pdfHelper->canExecChromium() && empty($this->params->get('htmltopdf_service_url'))) {
            throw new Exception(
                _t('PUBLICATION_NO_EXECUTABLE_FILE_FOUND_ON_PATH').' : '
                  .$this->params->get('htmltopdf_path').'<br />'
                  ._t('PUBLICATION_DID_YOU_INSTALL_CHROMIUM_OR_SET_UP_PATH'),
                1
            );
        }
    }

    /**
     * @throws Exception with code 1 if output content in exception Message
     */
    protected function checkDomain(array $get, array $server)
    {
        if (!empty($get['url']) &&
            !in_array(parse_url($server['HTTP_REFERER']??'', PHP_URL_HOST), $this->params->get('htmltopdf_service_authorized_domains'))) {
            throw new Exception(
                _t('PUBLICATION_DOMAIN_NOT_AUTORIZED').' : '.parse_url($server['HTTP_REFERER']??'', PHP_URL_HOST),
                1
            );
        }
    }

    protected function getBaseUrl(): string
    {
        return str_replace(array('/wakka.php?wiki=', '/?'), '', $this->params->get('base_url'));
    }

    protected function redirectToPdfServiceIfNeeded(string $sourceUrl, string $hash)
    {
        $serviceUrl = $this->params->get('htmltopdf_service_url');
        if (!empty($serviceUrl)) {
            $url = $serviceUrl.(strpos($serviceUrl, '?') === false ? '?' : '&').'urlPageTag='.$this->GetPageTag().'&url='.urlencode($sourceUrl).'&hash='.$hash;
            $this->wiki->Redirect($url);
        }
    }

    protected function renderExceptionForBrowser(ExceptionWithHtml $e, string $sourceUrl): string
    {
        ob_end_clean();
        $output = $this->render('@templates/alert-message.twig', [
            'type' => 'info',
            'message' => $sourceUrl
        ]);
        $output .= "\n".$this->render('@templates/alert-message.twig', [
            'type' => 'danger',
            'message' => $e->getMessage()
        ]);
        $html = $e->getHtml();
        $output .= "\n<pre><code lang=\"html\">". (empty($html) ? '' : htmlentities($html)) ."</code></pre>\n";

        return $output ;
    }

    protected function returnFile(string $fullFilename, string $dlFilename)
    {
        if (!file_exists($fullFilename)) {
            ob_end_flush();
            throw new Exception(_t('PUBLICATION_NO_GENERATED_PDF_FILE_FOUND'), 1);
        }

        ob_end_clean();
        $size = filesize($fullFilename);
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Content-type: application/force-download");
        header('Pragma: public');
        header("Pragma: no-cache");// HTTP/1.0
        header('Last-Modified: '.gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate'); // HTTP/1.1
        header('Cache-Control: pre-check=0, post-check=0, max-age=0'); // HTTP/1.1
        header('Content-Transfer-Encoding: none');
        header('Content-Type: application/octet-stream; name="' . $dlFilename . '"'); //This should work for the rest
        header('Content-Type: application/octetstream; name="' . $dlFilename . '"'); //This should work for IE & Opera
        header('Content-Type: application/download; name="' . $dlFilename . '"'); //This should work for IE & Opera
        header('Content-Disposition: attachment; filename="'.$dlFilename.'"');
        header("Content-Description: File Transfer");
        header("Content-length: $size");
        readfile($fullFilename);
        $this->wiki->exit();
    }
}
