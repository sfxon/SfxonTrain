<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class TrainCategoryMapper extends EntityMapperAbstract {
    use TSfxonEntityMapper;

    private const TABLE_NAME = 'sfxon_train_category';
    private const TABLE_ALIAS = 'tc';

    /** @var string[] Columns that may be checked by isEntityValueInUse(). */
    private array $allowedEntityIdFields = [    ];

    private const JOIN_FILTERS = [];

    public function __construct(IDBConnection $db) {
        parent::__construct($db, self::TABLE_NAME, TrainCategory::class);
    }

    public function getTrainCategoriesByIds(array $trainCategoryIds): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select(self::TABLE_ALIAS . '.*');
        $qb->from($this->getTableName(), self::TABLE_ALIAS);
        $qb->where(
            $qb->expr()->in(
                self::TABLE_ALIAS . '.id',
                $qb->createNamedParameter($trainCategoryIds, IQueryBuilder::PARAM_INT_ARRAY)
            )
        );

        //return $qb->executeQuery()->fetchAll();
        return $this->findEntities($qb);
    }

    protected function getDefaultSortField(): string {
        return 'title';
    }
}