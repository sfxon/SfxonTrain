<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class TrainUserRunMapper extends EntityMapperAbstract {
    use TSfxonEntityMapper;

    private const TABLE_NAME = 'sfxon_train_user_run';
    private const TABLE_ALIAS = 'tur';

    /** @var string[] Columns that may be checked by isEntityValueInUse(). */
    private array $allowedEntityIdFields = [
        'train_user_id',
        'train_version_id',
        'certificate_file_id',
    ];

    private const JOIN_FILTERS = [];

    public function __construct(IDBConnection $db) {
        parent::__construct($db, self::TABLE_NAME, TrainUserRun::class);
    }

    public function find(int $id): TrainUserRun {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

        return $this->findEntity($qb);
    }

    /** Offener Durchlauf = finished_at ist NULL (unabhängig von den Status-Werten). */
    public function findActiveByTrainUser(int $trainUserId): ?TrainUserRun {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('train_user_id', $qb->createNamedParameter($trainUserId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->isNull('finished_at'))
            ->orderBy('id', 'DESC')
            ->setMaxResults(1);

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException) {
            return null;
        }
    }

    /**
     * @param list<int> $trainUserIds
     * @return array<int, TrainUserRun> nach train_user_id indiziert
     */
    public function findActiveByTrainUserIds(array $trainUserIds): array {
        if ($trainUserIds === []) {
            return [];
        }

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->in('train_user_id', $qb->createNamedParameter($trainUserIds, IQueryBuilder::PARAM_INT_ARRAY)))
            ->andWhere($qb->expr()->isNull('finished_at'))
            ->orderBy('id', 'ASC');

        $byTrainUser = [];
        foreach ($this->findEntities($qb) as $run) {
            $byTrainUser[$run->getTrainUserId()] = $run;
        }

        return $byTrainUser;
    }

    public function countByTrainUser(int $trainUserId): int {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->count('*', 'cnt'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('train_user_id', $qb->createNamedParameter($trainUserId, IQueryBuilder::PARAM_INT)));

        $result = $qb->executeQuery();
        $count = (int) $result->fetchOne();
        $result->closeCursor();

        return $count;
    }

    protected function getDefaultSortField(): string {
        return 'id';
    }
}