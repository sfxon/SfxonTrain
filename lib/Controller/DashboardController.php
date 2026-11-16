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
use OCP\IGroupManager;
use OCP\IRequest;

/**
 * @psalm-suppress UnusedClass
 */
class DashboardController extends Controller {
    private const ADMIN_GROUP = 'TrainAdmin';

    public function __construct(
        IRequest $request,
        private ?string $userId,
        private TrainService $trainService,
        private IInitialState $initialState,
        private IGroupManager $groupManager,
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
        $this->initialState->provideInitialState('isTrainAdmin', $this->isTrainAdmin());

        return new TemplateResponse(
            Application::APP_ID,
            'index',
        );
    }

    /**
     * Pr�ft, ob der aktuelle Benutzer Mitglied der Gruppe "TrainAdmin" ist.
     * Die Mitgliedschaft in weiteren Gruppen spielt dabei keine Rolle.
     */
    private function isTrainAdmin(): bool {
        if ($this->userId === null || $this->userId === '') {
            return false;
        }

        return $this->groupManager->isInGroup($this->userId, self::ADMIN_GROUP);
    }
}