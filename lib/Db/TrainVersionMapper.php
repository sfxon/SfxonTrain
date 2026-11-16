<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class TrainVersionMapper extends EntityMapperAbstract {
    use TSfxonEntityMapper;

    private const TABLE_NAME = 'sfxon_train_version';
    private const TABLE_ALIAS = 'tv';

    /** @var string[] Columns that may be checked by isEntityValueInUse(). */
    private array $allowedEntityIdFields = [
        'train_id',
    ];

    private const JOIN_FILTERS = [];

    public function __construct(IDBConnection $db) {
        parent::__construct($db, self::TABLE_NAME, TrainVersion::class);
    }

    /**
     * Loads the current version of the given trains.
     *
     * @param Train[] $trainings
     * @return array<int, TrainVersion> indiziert nach train_id
     */
    public function getCurrentTrainingVersions(array $trainings): array {
        $versionIds = [];

        foreach ($trainings as $training) {
            $versionId = $training->getCurrentVersionId();

            if ($versionId !== null) {
                $versionIds[] = (int) $versionId;
            }
        }

        $versionIds = array_values(array_unique($versionIds));

        if ($versionIds === []) {
            return [];
        }

        $result = [];

        foreach (array_chunk($versionIds, 1000) as $chunk) {
            $qb = $this->db->getQueryBuilder();
            $qb->select('tv.*')
                ->from(self::TABLE_NAME, self::TABLE_ALIAS)
                ->where($qb->expr()->in(
                    'tv.id',
                    $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)
                ));

            foreach ($this->findEntities($qb) as $version) {
                $result[$version->getTrainId()] = $version;
            }
        }

        return $result;
    }

    protected function getDefaultSortField(): string {
        return 'title';
    }
}