<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Db;

use OCP\IDBConnection;

class TrainUserNoticeMapper extends EntityMapperAbstract {
    use TSfxonEntityMapper;

    private const TABLE_NAME = 'sfxon_train_user_notice';
    private const TABLE_ALIAS = 'tun';

    /** @var string[] Columns that may be checked by isEntityValueInUse(). */
    private array $allowedEntityIdFields = [
        'run_id',
    ];

    private const JOIN_FILTERS = [];

    public function __construct(IDBConnection $db) {
        parent::__construct($db, self::TABLE_NAME, TrainUserNotice::class);
    }

    protected function getDefaultSortField(): string {
        return 'id';
    }
}