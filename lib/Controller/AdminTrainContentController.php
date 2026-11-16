<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Controller;

use OCA\SfxonTrain\AppInfo\Application;
use OCA\SfxonTrain\Db\Train;
use OCA\SfxonTrain\Db\TrainMapper;
use OCA\SfxonTrain\Db\TrainVersion;
use OCA\SfxonTrain\Db\TrainVersionMapper;
use OCA\SfxonTrain\Service\TrainContentService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;

/**
 * Editor for the JSON content (pages, sections, quizzes) of the current version of a train.
 * Admin only (no #[NoAdminRequired]).
 *
 * @psalm-suppress UnusedClass
 */
class AdminTrainContentController extends Controller
{
    public function __construct(
        IRequest $request,
        private IInitialState $initialState,
        private TrainMapper $trainMapper,
        private TrainVersionMapper $trainVersionMapper,
        private TrainContentService $contentService,)
    {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoCSRFRequired]
    #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
    #[FrontpageRoute(verb: 'GET', url: '/admin/train/{trainId}/content', requirements: ['trainId' => '\d+'])]
    public function adminTrainContent(int $trainId): Response
    {
        $train = $this->findTrain($trainId);
        if ($train === null) {
            return new NotFoundResponse();
        }

        $version = $this->findCurrentVersion($train);

        $state = [
            'train' => [
                'id' => $train->getId(),
                'title' => $version?->getTitle(),
                'versionNumber' => $version?->getVersionNumber(),
            ],
            'hasVersion' => $version !== null,
            'content' => null,
            'isNew' => false,
            'loadError' => null,
        ];

        if ($version !== null) {
            try {
                if ($this->contentService->exists($version)) {
                    $state['content'] = $this->contentService->load($version);
                } else {
                    $state['content'] = $this->contentService->emptyContent();
                    $state['isNew'] = true;
                }
            } catch (\Throwable $e) {
                // An existing but broken file must never be replaced by an empty start content.
                $state['loadError'] = $e->getMessage();
            }
        }

        $this->initialState->provideInitialState('adminTrainContent', $state);

        return new TemplateResponse(Application::APP_ID, 'admin-train-content');
    }

    /**
     * Saves the content into the file of the current version (same place TrainContentService::load() reads from).
     * Body: JSON {"content": {schemaVersion, meta, pages}}.
     */
    #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
    #[FrontpageRoute(verb: 'PUT', url: '/api/admin/train/{trainId}/content', requirements: ['trainId' => '\d+'])]
    public function saveTrainContent(int $trainId): JSONResponse
    {
        $train = $this->findTrain($trainId);
        if ($train === null) {
            return new JSONResponse(['status' => 'error', 'message' => 'Training not found.'], Http::STATUS_NOT_FOUND);
        }

        $version = $this->findCurrentVersion($train);
        if ($version === null) {
            return new JSONResponse(
                ['status' => 'error', 'errors' => ['Das Training hat keine aktuelle Version.']],
                Http::STATUS_UNPROCESSABLE_ENTITY,
            );
        }

        $content = $this->request->getParam('content');
        $errors = $this->contentService->validate($content);

        if ($errors !== []) {
            return new JSONResponse(['status' => 'error', 'errors' => $errors], Http::STATUS_UNPROCESSABLE_ENTITY);
        }

        $this->contentService->save($version, $content);

        return new JSONResponse(['status' => 'success']);
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

    private function findCurrentVersion(Train $train): ?TrainVersion
    {
        $versionId = $train->getCurrentVersionId();
        if ($versionId === null) {
            return null;
        }

        try {
            /** @var TrainVersion $version */
            $version = $this->trainVersionMapper->findById($versionId)['mainData'];
            return $version;
        } catch (DoesNotExistException) {
            return null;
        }
    }
}