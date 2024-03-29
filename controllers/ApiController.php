<?php

namespace YesWiki\Publication\Controller;

use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Publication\Exception\ExceptionWithHtml;
use YesWiki\Publication\Service\PdfHelper;
use YesWiki\Publication\Service\SessionManager;

class ApiController extends YesWikiController
{
    protected $pdfHelper;
    /**
     * @Route("/api/pdf/getStatus/{uuid}",methods={"GET"}, options={"acl":{"public"}},priority=2)
     */
    public function getStatus($uuid)
    {
        $this->pdfHelper = $this->getService(PdfHelper::class);
        $status = $this->pdfHelper->getValuesInSession($uuid);
        return new ApiResponse($status, Response::HTTP_OK);
    }

    /**
     * @Route("/api/pdf/getTmpCookie",methods={"GET"}, options={"acl":{"public","+"}},priority=2)
     */
    public function getTmpCookie()
    {
        return new ApiResponse([], Response::HTTP_OK);
    }

    /**
     * @Route("/api/pdf/getPdf",methods={"GET"}, options={"acl":{"public"}},priority=2)
     */
    public function getPdf()
    {
        $this->pdfHelper = $this->getService(PdfHelper::class);
        $this->sessionManager = $this->getService(SessionManager::class);
        ob_start();

        $cause = [];
        try {
            $isOld = !empty($_GET['fromOldPath']) && in_array($_GET['fromOldPath'], [1,"1",true,"true"], true);
            $forceNewFormat = $isOld && !empty($_GET['forceNewFormat']) && in_array($_GET['forceNewFormat'], [1,"1",true,"true"], true);
            $uuid = (!empty($_GET['uuid']) && is_string($_GET['uuid'])) ? $_GET['uuid'] : '';

            // check params

            if (empty($_GET['url']) || !is_string($_GET['url'])) {
                $cause['missingUrl'] = true;
                throw new Exception("missing url", 3);
            }

            $cause['missingUrl'] = false;
            if (!$this->pdfHelper->canExecChromium()) {
                $cause['canExecChromium'] = false;
                throw new Exception("cannot Exec Chromium", 3);
            }
            $cause['canExecChromium'] = true;

            if (!$this->pdfHelper->checkDomain($_GET['url'])) {
                $cause['domainAuthorized'] = false;
                throw new Exception("domain not authorized", 3);
            }
            $cause['domainAuthorized'] = true;

            $cookies = (!empty($_POST['coookiesToUse']) && is_array($_POST['coookiesToUse']))
                ? array_filter($_POST['coookiesToUse'], 'is_string')
                : [];

            $this->pdfHelper->prepareSession($uuid);

            list(
                'pageTag' => $pageTag,
                'sourceUrl' => $sourceUrl,
                'hash' => $hash,
                'dlFilename' => $dlFilename,
                'fullFilename' => $fullFilename
            ) =
                $this->pdfHelper->getFullFileName(
                    $_GET ?? [],
                    $_SERVER ?? []
                );

            $this->pdfHelper->setValueInSession($uuid, PdfHelper::SESSION_FULLFILENAMEREADY, 1);


            $file_exists = file_exists($fullFilename);
            $this->pdfHelper->setValueInSession($uuid, PdfHelper::SESSION_FILE_STATUS, $file_exists ? 1 : 0);
            if (!$file_exists
            || ($file_exists && $this->wiki->UserIsAdmin() && isset($_GET['refresh']) && $_GET['refresh'] == 1)
            ) {
                $this->pdfHelper->useBrowserToCreatePdfFromPage($sourceUrl, $fullFilename, $uuid, $cookies);
            }
            $response = $this->returnFile($fullFilename, $dlFilename, $isOld && !$forceNewFormat, $uuid, !empty($cookies));
            $this->sessionManager->reactivateSession();
            return $response;
        } catch (Exception $ex) {
            if (in_array($ex->getCode(), [2,3], true) || $ex instanceof ExceptionWithHtml) {
                if ($ex instanceof ExceptionWithHtml) {
                    $cause['pdfCreationError'] = true;
                    if ($this->wiki->userIsAdmin()) {
                        $cause['pdfCreationErrorHTML'] = $ex->getHtml();
                        $cause['pdfCreationErrorMessage'] = $ex->getMessage();
                    }
                }
                if ($ex->getCode() == 2) {
                    $cause['canExecChromium'] = false;
                }
                if ($isOld && !$forceNewFormat) {
                    if (isset($cause['canExecChromium']) && $cause['canExecChromium'] === false) {
                        $output = $this->renderInSquelette('@templates/alert-message.twig', [
                            'type' => 'danger',
                            'message' => _t('PUBLICATION_NO_EXECUTABLE_FILE_FOUND_ON_PATH').' : '
                                .$this->params->get('htmltopdf_path').'<br />'
                                ._t('PUBLICATION_DID_YOU_INSTALL_CHROMIUM_OR_SET_UP_PATH')
                        ]);
                        return new Response($output, Response::HTTP_OK);
                    }
                    if (isset($cause['domainAuthorized']) && $cause['domainAuthorized'] === false) {
                        $output = $this->renderInSquelette('@templates/alert-message.twig', [
                            'type' => 'danger',
                            'message' => _t('PUBLICATION_DOMAIN_NOT_AUTORIZED').' : '.parse_url($_GET['url'] ?? '', PHP_URL_HOST),
                        ]);
                        return new Response($output, Response::HTTP_OK);
                    }
                    if (isset($cause['pdfCreationError']) && $cause['pdfCreationError'] === true) {
                        $output = $this->renderInSquelette('@publication/old-error-message.twig', [
                            'sourceUrl' => $_GET['url'] ?? '',
                            'message' => $ex->getMessage(),
                            'html' => $cause['pdfCreationErrorHTML'] ?? ''
                        ]);
                        return new Response($output, Response::HTTP_OK);
                    }
                }
                return new ApiResponse([
                    'error' => true,
                    'cause' => $cause
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            } else {
                throw $ex;
            }
        }
    }

    /**
     * return fileContent if exists
     * @param string $fullFilename,
     * @param string $dlFilename
     * @param bool $oldMode
     * @param string $uuid
     * @param bool $deleteFileAfterSend
     * @throws Exception
     * @return Response
     */
    protected function returnFile(string $fullFilename, string $dlFilename, bool $oldMode, string $uuid, bool $deleteFileAfterSend): Response
    {
        if (!file_exists($fullFilename)) {
            ob_end_flush();
            throw new Exception(_t('PUBLICATION_NO_GENERATED_PDF_FILE_FOUND'), 1);
        }

        $this->pdfHelper->addValueInSession($uuid, PdfHelper::SESSION_FILE_STATUS, 2);
        if ($oldMode) {
            $response = $this->returnFileOldMode($fullFilename, $dlFilename, $deleteFileAfterSend);
        } else {
            ob_end_clean();
            $headers = [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Allow-Headers' => 'X-Requested-With, Location, Slug, Accept, Content-Type',
                'Access-Control-Expose-Headers' => 'Location, Slug, Accept, Content-Type',
                'Access-Control-Allow-Methods' => 'GET',
                'Cache-Control' => 'no-store, no-cache, must-revalidate', // HTTP/1.1
                'Content-Type' => 'application/octet-stream',
                'Content-Description' => 'File Transfer',
            ];
            $response =  new BinaryFileResponse(
                $fullFilename,
                Response::HTTP_OK,
                $headers,
                true, //public
                null, //contentDisposition
                false, // autoEtag
                true // autoLastModified
            );
            try {
                $response->setContentDisposition('attachment', $dlFilename);
            } catch (Throwable $th) {
                // fallback
                $response->headers->set('Content-Disposition', 'attachment; filename="publication.pdf"');
            }
        }

        if ($deleteFileAfterSend) {
            $response->deleteFileAfterSend(true);
        }
        return $response;
    }

    protected function returnFileOldMode(string $fullFilename, string $dlFilename): Response
    {
        ob_end_clean();
        $size = filesize($fullFilename);
        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Headers' => 'X-Requested-With, Location, Slug, Accept, Content-Type',
            'Access-Control-Expose-Headers' => 'Location, Slug, Accept, Content-Type',
            'Access-Control-Allow-Methods' => 'GET',
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Pragma' => [
                'public',
                'no-cache',// HTTP/1.0
            ],
            'Last-Modified' => gmdate('D, d M Y H:i:s') . ' GMT',
            'Cache-Control' => [
                'no-store, no-cache, must-revalidate', // HTTP/1.1
                'pre-check=0, post-check=0, max-age=0', // HTTP/1.1
            ],
            'Content-Transfer-Encoding' => 'none',
            'Content-Type' => [
                "application/force-download",
                "application/octet-stream; name=\"$dlFilename\"", //This should work for the rest
                "application/octetstream; name=\"$dlFilename\"", //This should work for IE & Opera
                "application/download; name=\"$dlFilename\"", //This should work for IE & Opera
            ],
            'Content-Disposition' => "attachment; filename=\"$dlFilename\"",
            'Content-Description' => 'File Transfer',
            'Content-length' => $size
        ];
        return new BinaryFileResponse($fullFilename, Response::HTTP_OK, $headers);
    }
}
