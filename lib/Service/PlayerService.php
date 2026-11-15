<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Service;

use OCA\SfxonTrain\Db\TrainMapper;
use OCA\SfxonTrain\Db\TrainUser;
use OCA\SfxonTrain\Db\TrainUserMapper;
use OCA\SfxonTrain\Db\TrainUserRun;
use OCA\SfxonTrain\Db\TrainUserRunMapper;
use OCA\SfxonTrain\Db\TrainVersion;
use OCA\SfxonTrain\Db\TrainVersionMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class PlayerService
{
    public function __construct(
        private TrainMapper $trainMapper,
        private TrainUserMapper $trainUserMapper,
        private TrainUserRunMapper $trainUserRunMapper,
        private TrainVersionMapper $trainVersionMapper,
        private TrainContentService $contentService,
    ) { }

    /**
     * @return array{runId: int, resumed: bool}
     */
    public function startOrResume(string $userId, int $trainUserId): array
    {
        $trainUser = $this->trainUserMapper->findByIdForUser($trainUserId, $userId);
        $run = $this->trainUserRunMapper->findActiveByTrainUser($trainUser->getId());

        if ($run !== null) {
            return ['runId' => $run->getId(), 'resumed' => true];
        }

        $version = $this->currentVersionFor($trainUser);
        $content = $this->contentService->load($version);
        $pageIds = $this->contentService->pageIds($content);

        $run = new TrainUserRun();
        $run->setTrainUserId($trainUser->getId());
        $run->setTrainVersionId($version->getId());
        $run->setAttemptNo($this->trainUserRunMapper->countByTrainUser($trainUser->getId()) + 1);
        $run->setStatus(TrainStatus::RUN_IN_PROGRESS);
        $run->setStartedAt(new \DateTime());
        $run->setProgressJson($this->encode([
            'currentPageId' => $pageIds[0],
            'visitedPageIds' => [$pageIds[0]],
            'pageCount' => count($pageIds),
        ]));
        $run->setQuestionAnswersJson('{}');
        $run = $this->trainUserRunMapper->insert($run);

        $trainUser->setStatus(TrainStatus::USER_IN_PROGRESS);

        $this->trainUserMapper->update($trainUser);

        return ['runId' => $run->getId(), 'resumed' => false];
    }

    /**
     * @return array<string, mixed>
     */
    public function loadPlayer(string $userId, int $runId): array
    {
        [$run] = $this->loadOwned($userId, $runId);
        $version = $this->versionFor($run);
        $content = $this->contentService->load($version);

        $pageIds = $this->contentService->pageIds($content);
        $progress = $this->decode($run->getProgressJson());
        $currentPageId = $progress['currentPageId'] ?? $pageIds[0];

        if (!in_array($currentPageId, $pageIds, true)) {
            $currentPageId = $pageIds[0];
        }

        return [
            'run' => [
                'id' => $run->getId(),
                'trainUserId' => $run->getTrainUserId(),
                'attemptNo' => $run->getAttemptNo(),
                'currentPageId' => $currentPageId,
                'visitedPageIds' => array_values($progress['visitedPageIds'] ?? [$currentPageId]),
                'finished' => $run->getFinishedAt() !== null,
            ],
            'training' => [
                'title' => $version->getTitle() ?? '',
                'description' => $version->getDescription() ?? '',
                'passPercent' => $version->getPassPercent(),
            ],
            'content' => $this->contentService->toClient($content),
            'answers' => (object) $this->buildAnswerResults($content, $this->decode($run->getQuestionAnswersJson())),
        ];
    }

    /**
     * @return array{progress: int|null}
     */
    public function saveProgress(string $userId, int $runId, string $pageId): array
    {
        [$run] = $this->loadOwned($userId, $runId);
        $this->requireActive($run);

        $pageIds = $this->contentService->pageIds($this->contentFor($run));
        if (!in_array($pageId, $pageIds, true)) {
            throw new \InvalidArgumentException('Unbekannte Seite.');
        }

        $visited = $this->decode($run->getProgressJson())['visitedPageIds'] ?? [];
        if (!in_array($pageId, $visited, true)) {
            $visited[] = $pageId;
        }

        $run->setProgressJson($this->encode([
            'currentPageId' => $pageId,
            'visitedPageIds' => array_values($visited),
            'pageCount' => count($pageIds),
        ]));
        $this->trainUserRunMapper->update($run);

        return ['progress' => self::progressPercent($run->getProgressJson())];
    }

    /**
     * @param list<mixed> $selected
     * @return array<string, mixed>
     */
    public function answerQuestion(string $userId, int $runId, string $questionId, array $selected): array
    {
        [$run] = $this->loadOwned($userId, $runId);
        $this->requireActive($run);

        $entry = $this->contentService->indexQuestions($this->contentFor($run))[$questionId] ?? null;
        if ($entry === null) {
            throw new \InvalidArgumentException('Unbekannte Frage.');
        }
        $question = $entry['question'];

        $selected = array_values(array_unique(array_filter($selected, 'is_string')));
        $optionIds = array_map('strval', array_column($question['options'], 'id'));
        if ($selected === [] || array_diff($selected, $optionIds) !== []) {
            throw new \InvalidArgumentException('Ungültige Auswahl.');
        }
        if (($question['type'] ?? 'single') === 'single' && count($selected) !== 1) {
            throw new \InvalidArgumentException('Bei dieser Frage ist genau eine Antwort erlaubt.');
        }

        $stored = $this->decode($run->getQuestionAnswersJson());
        if ($entry['scored'] && isset($stored[$questionId])) {
            return $this->evaluate($question, $stored[$questionId]['selected'] ?? []);
        }

        $result = $this->evaluate($question, $selected);
        $stored[$questionId] = [
            'selected' => $result['selected'],
            'correct' => $result['correct'],
            'points' => $result['points'],
        ];
        $run->setQuestionAnswersJson($this->encode($stored));
        $this->trainUserRunMapper->update($run);

        return $result;
    }

    /**
     * @return array{passed: bool, scorePercent: int, pointsAchieved: int, pointsMax: int, passPercent: int|null}
     */
    public function finish(string $userId, int $runId): array
    {
        [$run, $trainUser] = $this->loadOwned($userId, $runId);
        $this->requireActive($run);

        $version = $this->versionFor($run);
        $content = $this->contentService->load($version);
        $stored = $this->decode($run->getQuestionAnswersJson());

        $pointsMax = 0;
        $pointsAchieved = 0;
        $unanswered = 0;

        foreach ($this->contentService->indexQuestions($content) as $questionId => $entry) {
            if (!$entry['scored']) {
                continue;
            }
            $pointsMax += (int) ($entry['question']['points'] ?? 1);
            if (!isset($stored[$questionId])) {
                $unanswered++;
                continue;
            }
            $pointsAchieved += (int) ($stored[$questionId]['points'] ?? 0);
        }

        if ($unanswered > 0) {
            throw new \LogicException(sprintf('Es sind noch %d zählende Fragen offen.', $unanswered));
        }

        $scorePercent = $pointsMax > 0 ? (int) round($pointsAchieved / $pointsMax * 100) : 100;
        $passPercent = $version->getPassPercent();
        $passed = $scorePercent >= ($passPercent ?? 0);
        $now = new \DateTime();

        $run->setFinishedAt($now);
        $run->setPointsAchieved($pointsAchieved);
        $run->setPointsMax($pointsMax);
        $run->setScorePercent($scorePercent);
        $run->setStatus($passed ? TrainStatus::RUN_PASSED : TrainStatus::RUN_FAILED);

        if ($passed) {
            $months = $version->getRepeatIntervalMonths();
            $validUntil = $months ? (clone $now)->modify('+' . $months . ' months') : null;
            $run->setValidUntil($validUntil);

            if ($validUntil !== null) {
                $trainUser->setStatus(TrainStatus::USER_ASSIGNED);
                $trainUser->setNextDueDate($validUntil);
            } else {
                $trainUser->setStatus(TrainStatus::USER_COMPLETED);
                $trainUser->setNextDueDate(null);
                $trainUser->setEndedAt($now);
            }
            $this->trainUserMapper->update($trainUser);
        }

        $this->trainUserRunMapper->update($run);

        return [
            'passed' => $passed,
            'scorePercent' => $scorePercent,
            'pointsAchieved' => $pointsAchieved,
            'pointsMax' => $pointsMax,
            'passPercent' => $passPercent,
        ];
    }

    public static function progressPercent(?string $progressJson): ?int
    {
        $progress = json_decode($progressJson ?? '', true);
        $pageCount = (int) ($progress['pageCount'] ?? 0);
        if (!is_array($progress) || $pageCount <= 0) {
            return null;
        }

        return min(100, (int) round(count($progress['visitedPageIds'] ?? []) / $pageCount * 100));
    }

    /**
     * @param array<string, mixed> $question
     * @param list<string> $selected
     * @return array<string, mixed>
     */
    private function evaluate(array $question, array $selected): array
    {
        $correctIds = [];
        foreach ($question['options'] as $option) {
            if (!empty($option['correct'])) {
                $correctIds[] = (string) $option['id'];
            }
        }

        $a = $selected;
        $b = $correctIds;
        sort($a);
        sort($b);

        // Auch bei "multiple" gilt: alles oder nichts.
        $isCorrect = $a === $b;
        $pointsMax = (int) ($question['points'] ?? 1);

        return [
            'questionId' => (string) $question['id'],
            'selected' => array_values($selected),
            'correct' => $isCorrect,
            'points' => $isCorrect ? $pointsMax : 0,
            'pointsMax' => $pointsMax,
            'correctOptionIds' => $correctIds,
            'explanation' => $question['explanation'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $content
     * @param array<string, mixed> $stored
     * @return array<string, array<string, mixed>>
     */
    private function buildAnswerResults(array $content, array $stored): array
    {
        $index = $this->contentService->indexQuestions($content);
        $results = [];

        foreach ($stored as $questionId => $answer) {
            if (isset($index[$questionId])) {
                $results[$questionId] = $this->evaluate($index[$questionId]['question'], $answer['selected'] ?? []);
            }
        }

        return $results;
    }

    /**
     * @return array{0: TrainUserRun, 1: TrainUser}
     */
    private function loadOwned(string $userId, int $runId): array
    {
        $run = $this->trainUserRunMapper->find($runId);
        // Wirft DoesNotExistException, wenn die Zuweisung nicht dem User gehört.
        $trainUser = $this->trainUserMapper->findByIdForUser($run->getTrainUserId(), $userId);

        return [$run, $trainUser];
    }

    private function requireActive(TrainUserRun $run): void
    {
        if ($run->getFinishedAt() !== null) {
            throw new \LogicException('Dieser Durchlauf ist bereits abgeschlossen.');
        }
    }

    private function currentVersionFor(TrainUser $trainUser): TrainVersion
    {
        $trains = $this->trainMapper->getTrainingsByTrainingIds([$trainUser->getTrainId()]);
        $version = $this->trainVersionMapper->getCurrentTrainingVersions($trains)[$trainUser->getTrainId()] ?? null;

        if ($version === null) {
            throw new DoesNotExistException('Keine aktuelle Version gefunden.');
        }

        return $version;
    }

    /** Ein Durchlauf bleibt immer an der Version, mit der er gestartet wurde. */
    private function versionFor(TrainUserRun $run): TrainVersion
    {
        return $this->trainVersionMapper->findById($run->getTrainVersionId())['mainData'];
    }

    /** @return array<string, mixed> */
    private function contentFor(TrainUserRun $run): array
    {
        return $this->contentService->load($this->versionFor($run));
    }

    /** @return array<string, mixed> */
    private function decode(?string $json): array
    {
        $data = json_decode($json ?? '', true);

        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $data */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}