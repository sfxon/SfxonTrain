<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Controller;

use OCA\SfxonTrain\AppInfo\Application;
use OCA\SfxonTrain\Db\TrainUserMapper;
use OCA\SfxonTrain\Db\TrainUserRunMapper;
use OCA\SfxonTrain\Db\TrainVersionMapper;
use OCA\SfxonTrain\Service\PdfCertificateService;
use OCA\SfxonTrain\Service\TrainStatus;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-suppress UnusedClass
 */
class PdfCertificateController extends Controller
{
    public function __construct(
        IRequest $request,
        private ?string $userId,
        private TrainUserRunMapper $trainUserRunMapper,
        private TrainUserMapper $trainUserMapper,
        private TrainVersionMapper $trainVersionMapper,
        private PdfCertificateService $certificateService,
        private IUserSession $userSession,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
    #[FrontpageRoute(verb: 'GET', url: '/api/runs/{runId}/certificate')]
    public function download(int $runId): Response
    {
        $userId = $this->userId ?? '';

        if ($userId === '') {
            return new NotFoundResponse();
        }

        try {
            // Durchlauf laden.
            $run = $this->trainUserRunMapper->find($runId);

            // Prüfen, ob die Zuweisung dem angemeldeten Benutzer gehört.
            $trainUser = $this->trainUserMapper->findByIdForUser(
                $run->getTrainUserId(),
                $userId,
            );

            // Zertifikat nur für abgeschlossene und bestandene Durchläufe ausstellen.
            if (
                $run->getFinishedAt() === null
                || $run->getStatus() !== TrainStatus::RUN_PASSED
            ) {
                return new NotFoundResponse();
            }

            // Tatsächlich absolvierte Trainingsversion laden.
            $versionData = $this->trainVersionMapper->findById(
                (int) $run->getTrainVersionId()
            );

            $version = $versionData['mainData'] ?? null;

            if ($version === null) {
                return new NotFoundResponse();
            }

            $displayName = $this->userSession->getUser()?->getDisplayName() ?? $userId;

            $pdf = $this->certificateService->getOrCreate(
                $userId,
                (int) $trainUser->getTrainId(),
                (int) $version->getId(),
                [
                    'participantName' => $displayName,
                    'trainingTitle' => $version->getTitle() ?? 'Schulung',
                    'completedAt' => $run->getFinishedAt(),
                    'scorePercent' => $run->getScorePercent(),
                    'versionNumber' => $version->getVersionNumber(),
                ],
            );

            $filename = sprintf(
                'Zertifikat_%d_%d.pdf',
                (int) $trainUser->getTrainId(),
                (int) $version->getId(),
            );

            return new DataDownloadResponse(
                $pdf,
                $filename,
                'application/pdf'
            );
        } catch (DoesNotExistException) {
            // Nicht offenlegen, ob ein Durchlauf eines anderen Benutzers existiert.
            return new NotFoundResponse();
        }
    }
}
