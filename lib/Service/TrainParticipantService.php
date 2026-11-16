<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Service;

use OCA\SfxonTrain\Db\TrainUserMapper;
use OCA\SfxonTrain\Db\TrainUserRunMapper;
use OCA\SfxonTrain\Db\TrainVersionMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserManager;

class TrainParticipantService
{
    public const STATUS_OPEN = 'open';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_COMPLETED = 'completed';

    private const STATUS_RANK = [
        self::STATUS_OVERDUE => 0,
        self::STATUS_OPEN => 1,
        self::STATUS_COMPLETED => 2,
    ];

    public function __construct(
        private TrainUserMapper $trainUserMapper,
        private TrainUserRunMapper $trainUserRunMapper,
        private TrainVersionMapper $trainVersionMapper,
        private TrainService $trainService,
        private PdfCertificateService $certificateService,
        private IUserManager $userManager,
    ) {
    }

    /**
     * Alle Zuweisungen eines Trainings inkl. aktuellem Status.
     *
     * @return list<array<string, mixed>>
     */
    public function loadParticipants(int $trainId): array
    {
        [$entries, $latestPassed] = $this->collect($trainId);

        $now = new \DateTimeImmutable();
        $rows = [];

        foreach ($entries as $entry) {
            $run = $latestPassed[$entry->getId()] ?? null;
            $due = $entry->getNextDueDate();
            $userId = (string) $entry->getUserId();

            $rows[] = [
                'id' => $entry->getId(),
                'userId' => $userId,
                'displayName' => $this->displayName($userId),
                'status' => $this->resolveStatus($run, $due, $now),
                'dueDate' => $due?->format(\DateTimeInterface::ATOM),
                'completedAt' => $run?->getFinishedAt()?->format(\DateTimeInterface::ATOM),
                'validUntil' => $run?->getValidUntil()?->format(\DateTimeInterface::ATOM),
                'scorePercent' => $run?->getScorePercent(),
                'runId' => $run?->getId(),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function sortRows(array $rows, string $orderBy, string $direction): array
    {
        $factor = $direction === 'DESC' ? -1 : 1;

        usort($rows, static function (array $a, array $b) use ($orderBy, $factor): int {
            $va = $a[$orderBy] ?? null;
            $vb = $b[$orderBy] ?? null;

            // Leere Werte stehen immer am Ende, unabhängig von der Richtung.
            if ($va === null || $vb === null) {
                if ($va === $vb) {
                    return strcasecmp((string) $a['displayName'], (string) $b['displayName']);
                }
                return $va === null ? 1 : -1;
            }

            if ($orderBy === 'status') {
                $cmp = self::STATUS_RANK[$va] <=> self::STATUS_RANK[$vb];
            } elseif (is_string($va)) {
                $cmp = strcasecmp($va, (string) $vb);
            } else {
                $cmp = $va <=> $vb;
            }

            if ($cmp === 0) {
                return strcasecmp((string) $a['displayName'], (string) $b['displayName']);
            }

            return $cmp * $factor;
        });

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{open: int, overdue: int, completed: int, total: int}
     */
    public function stats(array $rows): array
    {
        $stats = [
            self::STATUS_OPEN => 0,
            self::STATUS_OVERDUE => 0,
            self::STATUS_COMPLETED => 0,
        ];

        foreach ($rows as $row) {
            $stats[$row['status']]++;
        }

        return $stats + ['total' => count($rows)];
    }

    /**
     * Rendert das Zertifikat für genau einen bestandenen Durchlauf eines Trainings.
     * Gibt null zurück, wenn der Durchlauf nicht existiert, nicht bestanden ist
     * oder nicht zu diesem Training gehört.
     */
    public function renderCertificate(int $trainId, int $runId, string $fallbackTitle): ?string
    {
        try {
            $run = $this->trainUserRunMapper->find($runId);
        } catch (DoesNotExistException) {
            return null;
        }

        if ($run->getFinishedAt() === null || $run->getStatus() !== TrainStatus::RUN_PASSED) {
            return null;
        }

        // Der Durchlauf muss zu einer Zuweisung dieses Trainings gehören.
        $entry = null;
        foreach ($this->trainUserMapper->findByTrainId($trainId) as $candidate) {
            if ((int) $candidate->getId() === (int) $run->getTrainUserId()) {
                $entry = $candidate;
                break;
            }
        }
        if ($entry === null) {
            return null;
        }

        $version = $this->loadVersion((int) $run->getTrainVersionId());
        if ($version === null) {
            return null;
        }

        $userId = (string) $entry->getUserId();

        return $this->certificateService->getOrCreate(
            $userId,
            $trainId,
            (int) $version->getId(),
            [
                'participantName' => $this->displayName($userId),
                'trainingTitle' => $version->getTitle() ?? $fallbackTitle,
                'completedAt' => $run->getFinishedAt(),
                'scorePercent' => $run->getScorePercent(),
                'versionNumber' => $version->getVersionNumber(),
            ],
        );
    }

    /**
     * @return array{0: array<int, object>, 1: array<int, object>} [Zuweisungen, neuester bestandener Durchlauf je Zuweisung-ID]
     */
    private function collect(int $trainId): array
    {
        $entries = $this->trainUserMapper->findByTrainId($trainId);
        if ($entries === []) {
            return [[], []];
        }

        $trainUserIds = array_map(static fn ($entry) => $entry->getId(), $entries);
        $passedRuns = $this->trainUserRunMapper->findPassedByTrainUserIds($trainUserIds);

        $latestPassed = [];
        foreach ($passedRuns as $passedRun) {
            $key = $passedRun->getTrainUserId();
            $current = $latestPassed[$key] ?? null;

            $finished = $passedRun->getFinishedAt()?->getTimestamp() ?? 0;
            $currentFinished = $current?->getFinishedAt()?->getTimestamp() ?? 0;

            if ($current === null || $finished > $currentFinished) {
                $latestPassed[$key] = $passedRun;
            }
        }

        return [$entries, $latestPassed];
    }

    /**
     * Absolviert: gleiche Regel wie in der Benutzeransicht (TrainService::isFulfilled).
     * Überfällig: Fälligkeitsdatum oder Gültigkeit des letzten Abschlusses überschritten.
     */
    private function resolveStatus(?object $run, ?\DateTimeInterface $due, \DateTimeImmutable $now): string
    {
        if ($run !== null && $this->trainService->isFulfilled($run, $now)) {
            return self::STATUS_COMPLETED;
        }

        $validUntil = $run?->getValidUntil();

        if (($due !== null && $due < $now) || ($validUntil !== null && $validUntil < $now)) {
            return self::STATUS_OVERDUE;
        }

        return self::STATUS_OPEN;
    }

    private function loadVersion(int $versionId): ?object
    {
        try {
            return $this->trainVersionMapper->findById($versionId)['mainData'] ?? null;
        } catch (DoesNotExistException) {
            return null;
        }
    }

    private function displayName(string $userId): string
    {
        return $this->userManager->get($userId)?->getDisplayName() ?? $userId;
    }
}