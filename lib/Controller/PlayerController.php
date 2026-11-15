<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Controller;

use OCA\SfxonTrain\AppInfo\Application;
use OCA\SfxonTrain\Service\PlayerService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\IURLGenerator;

/**
 * @psalm-suppress UnusedClass
 */
class PlayerController extends Controller
{
    public function __construct(
        IRequest $request,
        private ?string $userId,
        private PlayerService $playerService,
        private IInitialState $initialState,
        private IURLGenerator $urlGenerator,)
    {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoCSRFRequired]
    #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
    #[FrontpageRoute(verb: 'GET', url: '/run/{runId}')]
    public function show(int $runId): Response
    {
        try {
            $data = $this->playerService->loadPlayer($this->uid(), $runId);
        } catch (DoesNotExistException) {
            return new NotFoundResponse();
        }

        if ($data['run']['finished']) {
            return new RedirectResponse($this->urlGenerator->linkToRoute(Application::APP_ID . '.page.index'));
        }

        $this->initialState->provideInitialState('player', $data);

        return new TemplateResponse(Application::APP_ID, 'player');
    }

    #[FrontpageRoute(verb: 'POST', url: '/api/trainings/{trainUserId}/run')]
    public function start(int $trainUserId): JSONResponse
    {
        return $this->guard(function () use ($trainUserId): array {
            $result = $this->playerService->startOrResume($this->uid(), $trainUserId);

            return $result + [
                'url' => $this->urlGenerator->linkToRoute(Application::APP_ID . '.player.show', ['runId' => $result['runId']]),
            ];
        });
    }

    #[FrontpageRoute(verb: 'PUT', url: '/api/runs/{runId}/progress')]
    public function saveProgress(int $runId, string $pageId): JSONResponse
    {
        return $this->guard(fn (): array => $this->playerService->saveProgress($this->uid(), $runId, $pageId));
    }

    /**
     * @param list<string> $selected
     */
    #[FrontpageRoute(verb: 'POST', url: '/api/runs/{runId}/answers')]
    public function answer(int $runId, string $questionId, array $selected): JSONResponse
    {
        return $this->guard(fn (): array => $this->playerService->answerQuestion($this->uid(), $runId, $questionId, $selected));
    }

    #[FrontpageRoute(verb: 'POST', url: '/api/runs/{runId}/finish')]
    public function finish(int $runId): JSONResponse {
        return $this->guard(fn (): array => $this->playerService->finish($this->uid(), $runId));
    }

    private function uid(): string
    {
        return $this->userId ?? '';
    }

    /**
     * @param callable(): array<string, mixed> $action
     */
    private function guard(callable $action): JSONResponse
    {
        try {
            return new JSONResponse($action());
        } catch (DoesNotExistException) {
            return new JSONResponse(['message' => 'Nicht gefunden.'], Http::STATUS_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\LogicException $e) {
            return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_CONFLICT);
        }
    }
}