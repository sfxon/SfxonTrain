<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Controller;

use OCA\SfxonTrain\AppInfo\Application;
use OCA\SfxonTrain\Db\Train;
use OCA\SfxonTrain\Db\TrainMapper;
use OCA\SfxonTrain\Db\TrainVersionMapper;
use OCA\SfxonTrain\Service\TrainParticipantService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\Util;

/**
 * Admin-Ansicht: Teilnehmer eines Trainings inkl. Status und Zertifikat-Export.
 * Ohne #[NoAdminRequired] ist der Zugriff auf Administratoren beschränkt.
 *
 * @psalm-suppress UnusedClass
 */
class AdminTrainParticipantController extends Controller
{
    private const SORTABLE_KEYS = ['displayName', 'userId', 'status', 'dueDate', 'completedAt', 'validUntil', 'scorePercent'];

    public function __construct(
        IRequest $request,
        private IInitialState $initialState,
        private TrainMapper $trainMapper,
        private TrainVersionMapper $trainVersionMapper,
        private TrainParticipantService $participantService,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoCSRFRequired]
    #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
    #[FrontpageRoute(verb: 'GET', url: '/admin/train/{trainId}/participants', requirements: ['trainId' => '\d+'])]
    public function participants(
        int $trainId,
        string $orderBy = 'displayName',
        string $orderDirection = 'ASC',
    ): Response {
        $train = $this->findTrain($trainId);
        if ($train === null) {
            return new NotFoundResponse();
        }

        $orderBy = in_array($orderBy, self::SORTABLE_KEYS, true) ? $orderBy : 'displayName';
        $orderDirection = strtoupper($orderDirection) === 'DESC' ? 'DESC' : 'ASC';

        $version = $this->trainVersionMapper->getCurrentTrainingVersions([$train])[$train->getId()] ?? null;

        $rows = $this->participantService->loadParticipants($trainId);
        $stats = $this->participantService->stats($rows);
        $rows = $this->participantService->sortRows($rows, $orderBy, $orderDirection);

        $this->initialState->provideInitialState('adminTrainParticipants', [
            'train' => [
                'id' => $train->getId(),
                'title' => $version?->getTitle(),
                'versionNumber' => $version?->getVersionNumber(),
            ],
            'rows' => $rows,
            'stats' => $stats,
            'certificateCount' => count(array_filter(
                $rows,
                static fn (array $row): bool => $row['runId'] !== null,
            )),
            'listState' => [
                'orderBy' => $orderBy,
                'orderDirection' => $orderDirection,
            ],
        ]);

        Util::addScript(Application::APP_ID, Application::APP_ID . '-adminTrainParticipants');

        return new TemplateResponse(Application::APP_ID, 'admin-train-participants');
    }

    /**
     * Liefert das Zertifikat EINES bestandenen Durchlaufs. Das ZIP für den
     * Sammel-Export setzt die Vue-Seite aus diesen Einzelabrufen zusammen.
     */
    #[NoCSRFRequired]
    #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
    #[FrontpageRoute(
        verb: 'GET',
        url: '/api/admin/train/{trainId}/runs/{runId}/certificate',
        requirements: ['trainId' => '\d+', 'runId' => '\d+'],
    )]
    public function certificate(int $trainId, int $runId): Response
    {
        $train = $this->findTrain($trainId);
        if ($train === null) {
            return new NotFoundResponse();
        }

        $version = $this->trainVersionMapper->getCurrentTrainingVersions([$train])[$train->getId()] ?? null;

        $pdf = $this->participantService->renderCertificate(
            $trainId,
            $runId,
            $version?->getTitle() ?? 'Schulung',
        );
        if ($pdf === null) {
            return new NotFoundResponse();
        }

        return new DataDownloadResponse(
            $pdf,
            sprintf('Zertifikat_%d_%d.pdf', $trainId, $runId),
            'application/pdf',
        );
    }

    private function findTrain(int $trainId): ?Train
    {
        try {
            /** @var Train $train */
            $train = $this->trainMapper->findById($trainId)['mainData'];
            return $train;
        } catch (DoesNotExistException) {
            return null;
        }
    }
}