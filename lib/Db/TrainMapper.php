<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class TrainMapper extends EntityMapperAbstract {
    use TSfxonEntityMapper;

    private const TABLE_NAME = 'sfxon_train';
    private const TABLE_ALIAS = 't';

    /** @var string[] Columns that may be checked by isEntityValueInUse(). */
    private array $allowedEntityIdFields = [
        'train_category_id',
        'current_version_id',
    ];

    private const JOIN_FILTERS = [];

    public function __construct(IDBConnection $db) {
        parent::__construct($db, self::TABLE_NAME, Train::class);
    }

    public function getTrainingsByTrainingIds(array $trainIds): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select(self::TABLE_ALIAS . '.*');
        $qb->from($this->getTableName(), self::TABLE_ALIAS);
        $qb->where(
            $qb->expr()->in(
                self::TABLE_ALIAS . '.id',
                $qb->createNamedParameter($trainIds, IQueryBuilder::PARAM_INT_ARRAY)
            )
        );

        return $this->findEntities($qb);
    }

    protected function getDefaultSortField(): string {
        return 'id';
    }
}