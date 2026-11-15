<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Db;

use OCP\IDBConnection;

class TrainLogMapper extends EntityMapperAbstract {
    use TSfxonEntityMapper;

    private const TABLE_NAME = 'sfxon_train_log';
    private const TABLE_ALIAS = 'tl';

    /** @var string[] Columns that may be checked by isEntityValueInUse(). */
    private array $allowedEntityIdFields = [    ];

    private const JOIN_FILTERS = [];

    public function __construct(IDBConnection $db) {
        parent::__construct($db, self::TABLE_NAME, TrainLog::class);
    }

    protected function getDefaultSortField(): string {
        return 'created_at';
    }
}