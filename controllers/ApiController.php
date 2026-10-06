<?php

namespace YesWiki\Publication\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Publication\Exception\ExceptionWithHtml;
use YesWiki\Publication\Service\PdfHelper;
use YesWiki\Publication\Service\SessionManager;
use YesWiki\Render\Service\TemplateEngine;

/** The PDF service: prints a page of this wiki, or of a wiki it serves, and says how far a print job got. */
class ApiController extends YesWikiController
{
    private const CORS = [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Credentials' => 'true',
        'Access-Control-Allow-Headers' => 'X-Requested-With, Location, Slug, Accept, Content-Type',
        'Access-Control-Expose-Headers' => 'Location, Slug, Accept, Content-Type',
        'Access-Control-Allow-Methods' => 'GET',
    ];

    #[Route('/api/pdf/getStatus/{uuid}', methods: ['GET'], options: ['acl' => ['public']], priority: 2)]
    public function getStatus(string $uuid): ApiResponse
    {
        return new ApiResponse($this->getService(PdfHelper::class)->getValuesInSession($uuid), Response::HTTP_OK);
    }

    #[Route('/api/pdf/getTmpCookie', methods: ['GET'], options: ['acl' => ['public', '+']], priority: 2)]
    public function getTmpCookie(): ApiResponse
    {
        return new ApiResponse([], Response::HTTP_OK);
    }

    #[Route('/api/pdf/getPdf', methods: ['GET'], options: ['acl' => ['public']], priority: 2)]
    public function getPdf(): Response
    {
        $pdfHelper = $this->getService(PdfHelper::class);
        $query = $this->getRequest()->query->all();
        $oldMode = self::isTrue($query['fromOldPath'] ?? null) && !self::isTrue($query['forceNewFormat'] ?? null);
        $uuid = is_string($query['uuid'] ?? null) ? $query['uuid'] : '';
        $url = is_string($query['url'] ?? null) ? $query['url'] : '';
        $cause = ['missingUrl' => $url === ''];

        try {
            if ($url === '') {
                throw new \RuntimeException('missing url', 3);
            }
            $cause['canExecChromium'] = $pdfHelper->canExecChromium();
            if (!$cause['canExecChromium']) {
                throw new \RuntimeException('cannot exec Chromium', 3);
            }
            $cause['domainAuthorized'] = $pdfHelper->checkDomain($url);
            if (!$cause['domainAuthorized']) {
                throw new \RuntimeException('domain not authorized', 3);
            }

            $pdfHelper->prepareSession($uuid);
            $file = $pdfHelper->getFullFileName($query, $this->getRequest()->server->all());
            $cookies = $pdfHelper->getAuthenticationCookies($file['sourceUrl']);
            $pdfHelper->setValueInSession($uuid, PdfHelper::SESSION_FULLFILENAMEREADY, 1);

            $refresh = self::isTrue($query['refresh'] ?? null) && $this->getService(AclService::class)->isAdmin();
            $pdf = ($cookies === [] && !$refresh) ? $pdfHelper->cached($file['cachePath']) : null;
            $pdfHelper->setValueInSession($uuid, PdfHelper::SESSION_FILE_STATUS, $pdf === null ? 0 : 1);
            if ($pdf === null) {
                $pdf = $pdfHelper->printToPdf($file['sourceUrl'], $uuid, $cookies);
                if ($cookies === []) {
                    $pdfHelper->cache($file['cachePath'], $pdf);
                }
            }
            $pdfHelper->addValueInSession($uuid, PdfHelper::SESSION_FILE_STATUS, 2);
            $this->getService(SessionManager::class)->reactivateSession();

            return $this->pdfResponse($pdf, $file['dlFilename'], $oldMode);
        } catch (ExceptionWithHtml $exception) {
            $cause['pdfCreationError'] = true;
            if ($this->getService(AclService::class)->isAdmin()) {
                $cause['pdfCreationErrorHTML'] = $exception->getHtml();
                $cause['pdfCreationErrorMessage'] = $exception->getMessage();
            }

            return $this->failure($cause, $oldMode, $url, $exception->getMessage());
        } catch (\RuntimeException $exception) {
            if (!in_array($exception->getCode(), [2, 3], true)) {
                throw $exception;
            }
            if ($exception->getCode() === 2) {
                $cause['canExecChromium'] = false;
            }

            return $this->failure($cause, $oldMode, $url, $exception->getMessage());
        }
    }

    private function pdfResponse(string $pdf, string $filename, bool $oldMode): Response
    {
        $headers = self::CORS + [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Content-Type' => $oldMode ? 'application/force-download' : 'application/octet-stream',
            'Content-Description' => 'File Transfer',
            'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $filename) . '"',
            'Content-Length' => (string)strlen($pdf),
        ];

        return new Response($pdf, Response::HTTP_OK, $headers);
    }

    /**
     * Why the PDF could not be made: JSON for the PDF page, or a page of its own for a wiki calling the old way.
     *
     * @param array<string, mixed> $cause
     */
    private function failure(array $cause, bool $oldMode, string $url, string $message): Response
    {
        if ($oldMode) {
            $alert = null;
            if (($cause['canExecChromium'] ?? true) === false) {
                $alert = _t('PUBLICATION_NO_EXECUTABLE_FILE_FOUND_ON_PATH') . ' : ' . htmlspecialchars($this->getService(PdfHelper::class)->configuredPath())
                    . '<br />' . _t('PUBLICATION_DID_YOU_INSTALL_CHROMIUM_OR_SET_UP_PATH');
            } elseif (($cause['domainAuthorized'] ?? true) === false) {
                $alert = _t('PUBLICATION_DOMAIN_NOT_AUTORIZED') . ' : ' . htmlspecialchars((string)parse_url($url, PHP_URL_HOST));
            } elseif (($cause['pdfCreationError'] ?? false) === true) {
                $alert = htmlspecialchars($url) . '<br />' . htmlspecialchars($message);
            }
            if ($alert !== null) {
                return new Response($this->getService(TemplateEngine::class)->renderFullPage('@core/alert-message.twig', ['type' => 'danger', 'message' => $alert]));
            }
        }

        return new ApiResponse(['error' => true, 'cause' => $cause], Response::HTTP_INTERNAL_SERVER_ERROR, self::CORS);
    }

    private static function isTrue(mixed $value): bool
    {
        return in_array($value, [1, '1', true, 'true'], true);
    }
}
