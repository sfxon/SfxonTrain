<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int|null getId()
 * @method \DateTime|null getEndedAt()
 * @method void setEndedAt(\DateTime|null $endedAt)
 * @method \DateTime|null getNextDueDate()
 * @method void setNextDueDate(\DateTime|null $nextDueDate)
 * @method int|null getStatus()
 * @method void setStatus(int|null $status)
 * @method string|null getSuspendedReason()
 * @method void setSuspendedReason(string|null $suspendedReason)
 * @method \DateTime|null getSuspendedUntil()
 * @method void setSuspendedUntil(\DateTime|null $suspendedUntil)
 * @method int|null getTrainId()
 * @method void setTrainId(int|null $trainId)
 * @method string|null getUserId()
 * @method void setUserId(string|null $userId)
 */
class TrainUser extends Entity implements \JsonSerializable {
    use TEntity;

    protected ?\DateTime $endedAt = null;
    protected ?\DateTime $nextDueDate = null;
    protected ?int $status = null;
    protected ?string $suspendedReason = null;
    protected ?\DateTime $suspendedUntil = null;
    protected ?int $trainId = null;
    protected ?string $userId = null;

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('endedAt', 'datetime');
        $this->addType('nextDueDate', 'datetime');
        $this->addType('status', 'integer');
        $this->addType('suspendedUntil', 'datetime');
        $this->addType('trainId', 'integer');
    }

    /**
     * Field definitions are used to check input and create automatic forms.
     */
    public static function getFieldDefinition() {
        return [
            [
                'dbType' => 'BIGINT',
                'defaultValue' => NULL,
                'filterType' => 'none',
                'foreignEntity' => false,
                'index' => true,
                'label' => 'Identifier',
                'length' => 20,
                'name' => 'id',
                'propertyName' => 'id',
                'type' => 'id',
                'requiredOnCreate' => false,
                'requiredOnUpdate' => true,
                'unique' => true,
            ],
            [
                'dbType' => 'DATETIME',
                'defaultValue' => NULL,
                'filterType' => 'none',
                'foreignEntity' => false,
                'index' => false,
                'label' => 'Ended At',
                'length' => null,
                'name' => 'ended_at',
                'propertyName' => 'endedAt',
                'type' => 'datetime',
                'requiredOnCreate' => false,
                'requiredOnUpdate' => false,
                'unique' => false,
            ],
            [
                'dbType' => 'DATE',
                'defaultValue' => NULL,
                'filterType' => 'none',
                'foreignEntity' => false,
                'index' => false,
                'label' => 'Next Due Date',
                'length' => null,
                'name' => 'next_due_date',
                'propertyName' => 'nextDueDate',
                'type' => 'date',
                'requiredOnCreate' => false,
                'requiredOnUpdate' => false,
                'unique' => false,
            ],
            [
                'dbType' => 'SMALLINT',
                'defaultValue' => 1,
                'filterType' => 'numericFromTo',
                'foreignEntity' => false,
                'index' => false,
                'label' => 'Status',
                'length' => null,
                'name' => 'status',
                'propertyName' => 'status',
                'type' => 'integer',
                'requiredOnCreate' => false,
                'requiredOnUpdate' => false,
                'unique' => false,
            ],
            [
                'dbType' => 'VARCHAR',
                'defaultValue' => NULL,
                'filterType' => 'like',
                'foreignEntity' => false,
                'index' => false,
                'label' => 'Suspended Reason',
                'length' => 255,
                'name' => 'suspended_reason',
                'propertyName' => 'suspendedReason',
                'type' => 'string',
                'requiredOnCreate' => false,
                'requiredOnUpdate' => false,
                'unique' => false,
            ],
            [
                'dbType' => 'DATE',
                'defaultValue' => NULL,
                'filterType' => 'none',
                'foreignEntity' => false,
                'index' => false,
                'label' => 'Suspended Until',
                'length' => null,
                'name' => 'suspended_until',
                'propertyName' => 'suspendedUntil',
                'type' => 'date',
                'requiredOnCreate' => false,
                'requiredOnUpdate' => false,
                'unique' => false,
            ],
            [
                'dbType' => 'BIGINT',
                'defaultValue' => NULL,
                'filterType' => 'in',
                'foreignEntity' => 'train',
                'index' => true,
                'label' => 'Train',
                'length' => null,
                'name' => 'train_id',
                'propertyName' => 'trainId',
                'type' => 'foreignKey',
                'requiredOnCreate' => true,
                'requiredOnUpdate' => true,
                'unique' => false,
            ],
            [
                'dbType' => 'VARCHAR',
                'defaultValue' => NULL,
                'filterType' => 'like',
                'foreignEntity' => false,
                'index' => false,
                'label' => 'User',
                'length' => 64,
                'name' => 'user_id',
                'propertyName' => 'userId',
                'type' => 'string',
                'requiredOnCreate' => true,
                'requiredOnUpdate' => true,
                'unique' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array {
        return $this->jsonSerializeFields();
    }
}