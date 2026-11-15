<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class TrainUserMapper extends EntityMapperAbstract {
    use TSfxonEntityMapper;

    private const TABLE_NAME = 'sfxon_train_user';
    private const TABLE_ALIAS = 'tu';

    /** @var string[] Columns that may be checked by isEntityValueInUse(). */
    private array $allowedEntityIdFields = [
        'train_id',
    ];

    private const JOIN_FILTERS = [];

    public function __construct(IDBConnection $db) {
        parent::__construct($db, self::TABLE_NAME, TrainUser::class);
    }

    public function getTrainingsByUser(string $userId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select(self::TABLE_ALIAS . '.*');
        $qb->from($this->getTableName(), self::TABLE_ALIAS);
        $qb->where(
            $qb->expr()->eq(
                self::TABLE_ALIAS . '.user_id',
                $qb->createNamedParameter($userId)
            )
        );

        return $this->findEntities($qb);
    }

    public function findByIdForUser(int $id, string $userId): TrainUser {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        return $this->findEntity($qb);
    }

    protected function getDefaultSortField(): string {
        return 'user_id';
    }
}