<?php

namespace YesWiki\Publication;

use Exception;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Page;
use YesWiki\Core\YesWikiHandler;
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

            $pagedjs_hash = sha1(json_encode(array_merge([
                file_get_contents('tools/publication/javascripts/browser/print.js'),
                file_get_contents('tools/publication/javascripts/vendor/pagedjs/paged.esm.js')
              ])));

            list('pageTag'=>$pageTag, 'sourceUrl'=>$sourceUrl, 'hash'=>$hash) =
                $this->getData($_GET ?? [], $_SERVER ?? [], $pagedjs_hash);

            $dlFilename = sprintf(
                '%s-%s.pdf',
                $pageTag,
                $hash
            );
            $dirname = sys_get_temp_dir()."/yeswiki/";
            if (!file_exists($dirname)) {
                mkdir($dirname);
            }
            $fullFilename = sprintf(
                '%s/yeswiki/%s-%s-%s.pdf',
                sys_get_temp_dir(),
                $pageTag,
                'publication',
                $hash
            );
            $file_exists = file_exists($fullFilename);
            if (($this->wiki->UserIsAdmin() && isset($_GET['print-debug']))
                    || !$file_exists
                    || ($file_exists && isset($_GET['refresh']) && $_GET['refresh']==1)
            ) {
                $this->redirectToPdfServiceIfNeeded($sourceUrl, $hash);
                $this->useBrowserToCreatePdfFromPage($sourceUrl, $fullFilename);
            }
            $this->returnFile($fullFilename, $dlFilename);
        } catch (Exception $th) {
            header('Access-Control-Allow-Origin: *');
            if ($th->getCode() == 1) {
                return $this->renderInSquelette('@templates/alert-message.twig', [
                    'type' => 'danger',
                    'message' => $th->getMessage()
                ]);
            } elseif ($th->getCode() == 2) {
                return $this->Header().$th->getMessage().$this->Footer()."\n";
            }
            throw $th;
        }
    }

    /**
     * @throws Exception with code 1 if output content in exception Message
     */
    protected function checkPdfPath()
    {
        if (!is_executable($this->params->get('htmltopdf_path')) && empty($this->params->get('htmltopdf_service_url'))) {
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

    protected function getData(array $get, array $server, string $pagedjs_hash): array
    {
        $pageTag = '';
        $sourceUrl = '';
        $hash = '';
        if (!empty($get['url'])) {
            $pageTag = isset($get['urlPageTag']) ? $get['urlPageTag'] : 'publication';
            $sourceUrl = $get['url'];
            $hash = substr(sha1($pagedjs_hash . strtolower($server['QUERY_STRING'])), 0, 10);
        } else {
            $pageTag = $this->wiki->GetPageTag();
            $pdfTag = $this->wiki->MiniHref('pdf', $pageTag);
            $sourceUrl = $this->wiki->href('preview', $pageTag, preg_replace('#^'. $pdfTag .'&?#', '', $server['QUERY_STRING']), false);


            $hash = substr(sha1($pagedjs_hash . json_encode(array_merge(
                $this->wiki->page,
                ['query_string' => strtolower($server['QUERY_STRING']),
                $this->pdfHelper->getPageEntriesContent(
                    $pageTag,
                    $get['via'] ?? null
                ) ?? []]
            ))), 0, 10);

            // In case we are behind a proxy (like a Docker container)
            // It allows us to properly load the document from within the container itself
            if ($this->params->get('htmltopdf_base_url')) {
                $sourceUrl = str_replace($this->params->get('base_url'), $this->params->get('htmltopdf_base_url'), $sourceUrl);
            }
        }
        return compact(['pageTag','sourceUrl','hash']);
    }

    protected function redirectToPdfServiceIfNeeded(string $sourceUrl, string $hash)
    {
        $serviceUrl = $this->params->get('htmltopdf_service_url');
        if (!empty($serviceUrl)) {
            $url = $serviceUrl.(strpos($serviceUrl, '?') === false ? '?' : '&').'urlPageTag='.$this->GetPageTag().'&url='.urlencode($sourceUrl).'&hash='.$hash;
            $this->wiki->Redirect($url);
        }
    }

    protected function useBrowserToCreatePdfFromPage(string $sourceUrl, string $fullFilename)
    {
        try {
            $browserFactory = new BrowserFactory($this->params->get('htmltopdf_path'));
            $browser = $browserFactory->createBrowser($this->params->get('htmltopdf_options'));

            $page = $browser->createPage();
            $page->navigate($sourceUrl)->waitForNavigation(Page::NETWORK_IDLE);

            $value = $page->evaluate('__is_yw_publication_ready()')->getReturnValue($this->params->get('page_load_timeout'));

            // now generate PDF
            $page->pdf(array(
              'printBackground' => true,
              'displayHeaderFooter' => true,
              'preferCSSPageSize' => true
            ))->saveToFile($fullFilename);

            $browser->close();
        } catch (Exception $e) {
            throw new Exception($this->renderExceptionForBrowser($e, $sourceUrl, $browser, $page), 2);
        }
    }

    protected function renderExceptionForBrowser(Exception $e, string $sourceUrl, $browser, $page): string
    {
        ob_end_clean();

        if (($e instanceof OperationTimedOut) === false) {
            $html = $page->evaluate('document.documentElement.innerHTML')->getReturnValue();
        }

        $browser->close();
        $output = $this->render('@templates/alert-message.twig', [
            'type' => 'info',
            'message' => $sourceUrl
        ]);
        $output .= "\n".$this->render('@templates/alert-message.twig', [
            'type' => 'danger',
            'message' => $e->getMessage()
        ]);
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
        header('Access-Control-Allow-Origin: *');
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
