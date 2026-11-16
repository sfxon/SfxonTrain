<?php

declare(strict_types=1);

namespace OCA\SfxonTrain\Service;

use OCA\SfxonTrain\Db\TrainCategoryMapper;
use OCA\SfxonTrain\Db\TrainMapper;
use OCA\SfxonTrain\Db\TrainUserMapper;
use OCA\SfxonTrain\Db\TrainVersionMapper;
use OCA\SfxonTrain\Db\TrainUserRunMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class TrainService
{
    private const USER_STATUS_IN_PROGRESS = TrainStatus::USER_IN_PROGRESS;
    private const DUE_SOON_DAYS = 14;

    public function __construct(
        private TrainCategoryMapper $trainCategoryMapper,
        private TrainMapper $trainMapper,
        private TrainUserMapper $trainUserMapper,
        private TrainUserRunMapper $trainUserRunMapper,
        private TrainVersionMapper $trainVersionMapper,
    ) { }

    public function loadOpenTrainingsByUser(string $userId): array
    {
        $trainUserEntries = $this->trainUserMapper->getTrainingsByUser($userId);

        // Trainings gesammelt über alle IDs laden (WHERE IN).
        $trainingIds = $this->collectTrainingIds($trainUserEntries);
        $trainings = $this->trainMapper->getTrainingsByTrainingIds($trainingIds);

        $trainingsById = [];
        foreach ($trainings as $training) {
            $trainingsById[$training->getId()] = $training;
        }

        // Aktuelle Versionen (Rückgabe ist nach trainId indiziert).
        $trainVersions = $this->trainVersionMapper->getCurrentTrainingVersions($trainings);

        // Kategorien.
        $trainCategoryIds = $this->collectTrainCategoryIds($trainings);
        $categoriesById = [];
        foreach ($this->trainCategoryMapper->getTrainCategoriesByIds($trainCategoryIds) as $category) {
            $categoriesById[$category->getId()] = $category;
        }

        $trainUserIds = array_map(
            static fn ($entry) => $entry->getId(),
            $trainUserEntries,
        );

        // Offene Durchläufe (nach trainUserId indiziert) -> Start oder Weitermachen.
        $activeRuns = $this->trainUserRunMapper->findActiveByTrainUserIds($trainUserIds);

        $passedRuns = $this->trainUserRunMapper->findPassedByTrainUserIds($trainUserIds);

        $latestPassedByEntry = [];
        foreach ($passedRuns as $passedRun) {
            $key = $passedRun->getTrainUserId();
            $current = $latestPassedByEntry[$key] ?? null;

            $finished = $passedRun->getFinishedAt()?->getTimestamp() ?? 0;
            $currentFinished = $current?->getFinishedAt()?->getTimestamp() ?? 0;

            if ($current === null || $finished > $currentFinished) {
                $latestPassedByEntry[$key] = $passedRun;
            }
        }

        // Zusammenführen: ein flacher Eintrag pro Zuweisung.
        $now = new \DateTimeImmutable();
        $open = [];

        foreach ($trainUserEntries as $entry) {
            $training = $trainingsById[$entry->getTrainId()] ?? null;
            if ($training === null) {
                continue;
            }

            if ($this->isFulfilled($latestPassedByEntry[$entry->getId()] ?? null, $now)) {
                continue;
            }

            $version = $trainVersions[$training->getId()] ?? null;
            $category = $categoriesById[$training->getTrainCategoryId()] ?? null;
            $due = $entry->getNextDueDate();
            $run = $activeRuns[$entry->getId()] ?? null;

            $open[] = [
                'id' => $entry->getId(),
                'trainId' => $training->getId(),
                'category' => $category?->getTitle() ?? '',
                'title' => $version?->getTitle() ?? '',
                'description' => $version?->getDescription() ?? '',
                'status' => $this->resolveStatus($entry->getStatus(), $due, $now),
                'dueDate' => $due?->format(\DateTimeInterface::ATOM),
                'daysUntilDue' => $due === null ? null : (int) $now->diff($due)->format('%r%a'),
                'passPercent' => $version?->getPassPercent(),
                'repeatIntervalMonths' => $version?->getRepeatIntervalMonths(),
                'runId' => $run?->getId(),
                'progress' => $run === null ? null : PlayerService::progressPercent($run->getProgressJson()),
            ];
        }

        // Überfällige zuerst, dann nach Fälligkeit (ohne Datum zuletzt).
        usort($open, static function (array $a, array $b): int {
            return [$a['dueDate'] === null, $a['dueDate']] <=> [$b['dueDate'] === null, $b['dueDate']];
        });

        // Trainingszuweisungen nach ID indizieren.
        $trainUserEntriesById = [];
        foreach ($trainUserEntries as $entry) {
            $trainUserEntriesById[$entry->getId()] = $entry;
        }

        $completed = [];

        foreach ($passedRuns as $run) {
            $entry = $trainUserEntriesById[$run->getTrainUserId()] ?? null;

            if ($entry === null) {
                continue;
            }

            try {
                // Die tatsächlich absolvierte Version laden.
                $versionData = $this->trainVersionMapper->findById(
                    $run->getTrainVersionId()
                );
            } catch (DoesNotExistException) {
                continue;
            }

            $version = $versionData['mainData'] ?? null;

            if ($version === null) {
                continue;
            }

            $training = $trainingsById[$version->getTrainId()] ?? null;

            if ($training === null) {
                continue;
            }

            $category = $categoriesById[$training->getTrainCategoryId()] ?? null;

            $completed[] = [
                'id' => $run->getId(),
                'runId' => $run->getId(),
                'category' => $category?->getTitle() ?? '',
                'title' => $version->getTitle() ?? '',
                'statusLabel' => 'Bestanden',
                'result' => (string) ($run->getScorePercent() ?? 0) . ' %',
                'resultLabel' => 'Ergebnis',
                'completedAt' => $run->getFinishedAt()?->format(\DateTimeInterface::ATOM),
                'validUntil' => $run->getValidUntil()?->format(\DateTimeInterface::ATOM),
            ];
        }

        // Neueste Abschlüsse zuerst.
        usort(
            $completed,
            static fn (array $a, array $b): int =>
                ($b['completedAt'] ?? '') <=> ($a['completedAt'] ?? '')
        );

        return [
            'open' => $open,
            'completed' => $completed,
            'stats' => [
                'open' => count($open),
                'overdue' => count(array_filter(
                    $open,
                    static fn (array $t): bool => $t['status'] === 'overdue'
                )),
                'completed' => count($completed),
            ],
        ];
    }

    /**
     * Eine Zuweisung gilt als erfüllt, wenn der neueste bestandene Durchlauf
     * endlos gültig ist (valid_until = NULL) oder das Ablaufdatum außerhalb
     * des "Bald fällig"-Fensters liegt.
     */
    public function isFulfilled(?object $passedRun, \DateTimeImmutable $now): bool
    {
        if ($passedRun === null) {
            return false;
        }

        $validUntil = $passedRun->getValidUntil();

        // Kein Ablaufdatum = endlos gültig.
        if ($validUntil === null) {
            return true;
        }

        // Gültig, solange das Ablaufdatum außerhalb des "Bald fällig"-Fensters liegt.
        return $validUntil >= $now->modify('+' . self::DUE_SOON_DAYS . ' days');
    }

    private function resolveStatus(
        int $userStatus,
        ?\DateTimeInterface $due,
        \DateTimeImmutable $now
    ): string {
        if ($due !== null && $due < $now) {
            return 'overdue';
        }

        if ($due !== null && $due < $now->modify('+' . self::DUE_SOON_DAYS . ' days')) {
            return 'dueSoon';
        }

        if ($userStatus === self::USER_STATUS_IN_PROGRESS) {
            return 'inProgress';
        }

        return 'new';
    }

    private function collectTrainCategoryIds(array $trainings): array
    {
        $retval = [];

        foreach ($trainings as $training) {
            $trainCategoryId = $training->getTrainCategoryId();
            $retval[$trainCategoryId] = $trainCategoryId;
        }

        return $retval;
    }

    private function collectTrainingIds(array $trainUserEntries): array
    {
        $retval = [];

        foreach ($trainUserEntries as $trainUserEntry) {
            $trainId = $trainUserEntry->getTrainId();
            $retval[$trainId] = $trainId;
        }

        return $retval;
    }
}