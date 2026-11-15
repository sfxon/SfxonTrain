<?php

declare(strict_types=1);

namespace OCA\SfxonTrain\Controller;

use OCA\SfxonTrain\AppInfo\Application;
use OCA\SfxonTrain\Service\TrainService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;

/**
 * @psalm-suppress UnusedClass
 */
class PageController extends Controller {
    public function __construct(
        IRequest $request,
        private ?string $userId,
        private TrainService $trainService,
        private IInitialState $initialState,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoCSRFRequired]
    #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
    #[FrontpageRoute(verb: 'GET', url: '/')]
    public function index(): TemplateResponse {
        $data = $this->trainService->loadOpenTrainingsByUser($this->userId ?? '');

        $this->initialState->provideInitialState('trainings', $data['open']);
        $this->initialState->provideInitialState('completed', $data['completed']);
        $this->initialState->provideInitialState('stats', $data['stats']);

        return new TemplateResponse(
            Application::APP_ID,
            'index',
        );
    }
}