<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Controller;

use OCA\SfxonTrain\AppInfo\Application;
use OCA\SfxonTrain\Db\Train;
use OCA\SfxonTrain\Db\TrainCategoryMapper;
use OCA\SfxonTrain\Db\TrainMapper;
use OCA\SfxonTrain\Db\TrainVersion;
use OCA\SfxonTrain\Db\TrainVersionMapper;
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
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\Util;


/**
 * @psalm-suppress UnusedClass
 */
class AdminTrainController extends Controller
{
    private const ALLOWED_LIMITS = [10, 20, 50, 100];
    private const DEFAULT_LIMIT = 20;
    private const SORTABLE_KEYS = ['id', 'active', 'title', 'trainCategoryId'];
    private const TITLE_MAX_LENGTH = 300;
    private const INT_MAX = 2147483647;

    public function __construct(
        IRequest $request,
        private IInitialState $initialState,
        private IDBConnection $db,
        private TrainMapper $trainMapper,
        private TrainVersionMapper $trainVersionMapper,
        private TrainCategoryMapper $trainCategoryMapper,)
    {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoCSRFRequired]
    #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
    #[FrontpageRoute(verb: 'GET', url: '/admin/train/')]
    public function adminTrain(
        int $page = 1,
        int $limit = self::DEFAULT_LIMIT,
        string $orderBy = 'id',
        string $orderDirection = 'ASC',
    ): TemplateResponse {
        $limit = in_array($limit, self::ALLOWED_LIMITS, true) ? $limit : self::DEFAULT_LIMIT;
        $orderBy = in_array($orderBy, self::SORTABLE_KEYS, true) ? $orderBy : 'id';
        $orderDirection = strtoupper($orderDirection) === 'DESC' ? 'DESC' : 'ASC';

        $total = $this->trainMapper->countAll();
        $totalPages = max(1, (int)ceil($total / $limit));
        $page = min(max(1, $page), $totalPages);

        $result = $this->trainMapper->findAllPaged(
            $orderBy,
            $orderDirection,
            $limit,
            ($page - 1) * $limit,
            null,
            ['trainCategory' => ['fields' => ['id', 'title']]],
        );

        $trains = $result['mainData'];
        $versions = $this->trainVersionMapper->getCurrentTrainingVersions($trains);

        $rows = [];
        foreach ($trains as $train) {
            $version = $versions[$train->getId()] ?? null;

            $rows[] = [
                'id' => $train->getId(),
                'active' => $train->getActive(),
                'trainCategoryId' => $train->getTrainCategoryId(),
                'currentVersionId' => $train->getCurrentVersionId(),
                'title' => $version?->getTitle(),
                'versionNumber' => $version?->getVersionNumber(),
                'status' => $version?->getStatus(),
                'passPercent' => $version?->getPassPercent(),
                'repeatIntervalMonths' => $version?->getRepeatIntervalMonths(),
            ];
        }

        $categories = [];
        foreach ($result['relations']['trainCategory'] ?? [] as $category) {
            $categories[] = ['id' => (int)$category['id'], 'label' => (string)$category['title']];
        }

        $this->initialState->provideInitialState('adminTrain', [
            'rows' => $rows,
            'relatedEntityData' => ['trainCategory' => $categories],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => $totalPages,
                'allowedLimits' => self::ALLOWED_LIMITS,
            ],
            'listState' => [
                'orderBy' => $orderBy,
                'orderDirection' => $orderDirection,
            ],
        ]);

        Util::addScript(Application::APP_ID, Application::APP_ID . '-adminTrain');

        return new TemplateResponse(Application::APP_ID, 'admin-train');
    }

    #[NoCSRFRequired]
    #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
    #[FrontpageRoute(verb: 'GET', url: '/admin/train/{trainId}/edit', requirements: ['trainId' => '\d+'])]
    public function adminTrainEdit(int $trainId): Response
    {
        try {
            /** @var Train $train */
            $train = $this->trainMapper->findById($trainId)['mainData'];
        } catch (DoesNotExistException) {
            return new NotFoundResponse();
        }

        $version = $this->findCurrentVersion($train);

        $categories = [];
        $categoryResult = $this->trainCategoryMapper->findAllPaged('title', 'ASC', 1000, 0);
        foreach ($categoryResult['mainData'] as $category) {
            $categories[] = ['id' => $category->getId(), 'label' => (string)$category->getTitle()];
        }

        $this->initialState->provideInitialState('adminTrainEdit', [
            'train' => [
                'id' => $train->getId(),
                'active' => $train->getActive(),
                'trainCategoryId' => $train->getTrainCategoryId(),
                'currentVersionId' => $train->getCurrentVersionId(),
                'title' => $version?->getTitle(),
                'description' => $version?->getDescription(),
                'passPercent' => $version?->getPassPercent(),
                'repeatIntervalMonths' => $version?->getRepeatIntervalMonths(),
                'versionNumber' => $version?->getVersionNumber(),
                'status' => $version?->getStatus(),
            ],
            'categories' => $categories,
        ]);

        return new TemplateResponse(Application::APP_ID, 'admin-train-edit');
    }

    #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
    #[FrontpageRoute(verb: 'PUT', url: '/api/admin/train/{trainId}', requirements: ['trainId' => '\d+'])]
    public function updateTrain(int $trainId): JSONResponse
    {
        try {
            /** @var Train $train */
            $train = $this->trainMapper->findById($trainId)['mainData'];
        } catch (DoesNotExistException) {
            return new JSONResponse(
                ['status' => 'error', 'message' => 'Training not found.'],
                Http::STATUS_NOT_FOUND,
            );
        }

        $version = $this->findCurrentVersion($train);

        [$values, $errors] = $this->validateUpdateInput($this->request->getParams(), $version !== null);

        if ($errors !== []) {
            return new JSONResponse(
                ['status' => 'error', 'errors' => $errors],
                Http::STATUS_UNPROCESSABLE_ENTITY,
            );
        }

        $train->setActive($values['active']);
        $train->setTrainCategoryId($values['trainCategoryId']);

        if ($version !== null) {
            // Content only. versionNumber, status and jsonSchemaVersion stay untouched.
            $version->setTitle($values['title']);
            $version->setDescription($values['description']);
            $version->setPassPercent($values['passPercent']);
            $version->setRepeatIntervalMonths($values['repeatIntervalMonths']);
        }

        $this->db->beginTransaction();
        try {
            $this->trainMapper->update($train);

            if ($version !== null) {
                $this->trainVersionMapper->update($version);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return new JSONResponse(['status' => 'success', 'id' => $train->getId()]);
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

    /**
     * @return array{0: array<string, mixed>, 1: array<string, string>} [validated values, errors by field name]
     */
    private function validateUpdateInput(array $input, bool $hasVersion): array
    {
        $values = [];
        $errors = [];

        $active = $this->parseNullableInt($input['active'] ?? null, 0, 1);
        if ($active === null || $active === false) {
            $errors['active'] = 'Invalid value.';
        } else {
            $values['active'] = $active;
        }

        $categoryId = $this->parseNullableInt($input['trainCategoryId'] ?? null, 1, self::INT_MAX);
        if ($categoryId === false) {
            $errors['trainCategoryId'] = 'Invalid value.';
        } elseif ($categoryId !== null && $this->trainCategoryMapper->getTrainCategoriesByIds([$categoryId]) === []) {
            $errors['trainCategoryId'] = 'The category does not exist.';
        } else {
            $values['trainCategoryId'] = $categoryId;
        }

        if (!$hasVersion) {
            return [$values, $errors];
        }

        $title = $input['title'] ?? null;
        if ($title !== null && !is_string($title)) {
            $errors['title'] = 'Invalid value.';
        } else {
            $title = trim((string)$title);
            if (mb_strlen($title) > self::TITLE_MAX_LENGTH) {
                $errors['title'] = 'The title must not be longer than ' . self::TITLE_MAX_LENGTH . ' characters.';
            } else {
                $values['title'] = $title === '' ? null : $title;
            }
        }

        $description = $input['description'] ?? null;
        if ($description !== null && !is_string($description)) {
            $errors['description'] = 'Invalid value.';
        } else {
            $description = trim((string)$description);
            $values['description'] = $description === '' ? null : $description;
        }

        $passPercent = $this->parseNullableInt($input['passPercent'] ?? null, 0, 100);
        if ($passPercent === false) {
            $errors['passPercent'] = 'The value must be a whole number between 0 and 100.';
        } else {
            $values['passPercent'] = $passPercent;
        }

        $repeatIntervalMonths = $this->parseNullableInt($input['repeatIntervalMonths'] ?? null, 0, self::INT_MAX);
        if ($repeatIntervalMonths === false) {
            $errors['repeatIntervalMonths'] = 'The value must be a whole number of 0 or more.';
        } else {
            $values['repeatIntervalMonths'] = $repeatIntervalMonths;
        }

        return [$values, $errors];
    }

    /**
     * @return int|null|false null for empty input, false if the value is not a valid integer in the range
     */
    private function parseNullableInt(mixed $value, int $min, int $max): int|null|false
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $min, 'max_range' => $max],
        ]);
    }
}