<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int|null getId()
 * @method int|null getChannel()
 * @method void setChannel(int|null $channel)
 * @method int|null getRunId()
 * @method void setRunId(int|null $runId)
 * @method \DateTime|null getSentAt()
 * @method void setSentAt(\DateTime|null $sentAt)
 * @method int|null getType()
 * @method void setType(int|null $type)
 */
class TrainUserNotice extends Entity implements \JsonSerializable {
    use TEntity;

    protected ?int $channel = null;
    protected ?int $runId = null;
    protected ?\DateTime $sentAt = null;
    protected ?int $type = null;

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('channel', 'integer');
        $this->addType('runId', 'integer');
        $this->addType('sentAt', 'datetime');
        $this->addType('type', 'integer');
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
                'dbType' => 'INT',
                'defaultValue' => 0,
                'filterType' => 'numericFromTo',
                'foreignEntity' => false,
                'index' => false,
                'label' => 'Channel',
                'length' => null,
                'name' => 'channel',
                'propertyName' => 'channel',
                'type' => 'integer',
                'requiredOnCreate' => false,
                'requiredOnUpdate' => false,
                'unique' => false,
            ],
            [
                'dbType' => 'BIGINT',
                'defaultValue' => NULL,
                'filterType' => 'in',
                'foreignEntity' => 'train_user_run',
                'index' => true,
                'label' => 'Run',
                'length' => null,
                'name' => 'run_id',
                'propertyName' => 'runId',
                'type' => 'foreignKey',
                'requiredOnCreate' => true,
                'requiredOnUpdate' => true,
                'unique' => false,
            ],
            [
                'dbType' => 'DATETIME',
                'defaultValue' => NULL,
                'filterType' => 'none',
                'foreignEntity' => false,
                'index' => false,
                'label' => 'Sent At',
                'length' => null,
                'name' => 'sent_at',
                'propertyName' => 'sentAt',
                'type' => 'datetime',
                'requiredOnCreate' => false,
                'requiredOnUpdate' => false,
                'unique' => false,
            ],
            [
                'dbType' => 'INT',
                'defaultValue' => 0,
                'filterType' => 'numericFromTo',
                'foreignEntity' => false,
                'index' => false,
                'label' => 'Type',
                'length' => null,
                'name' => 'type',
                'propertyName' => 'type',
                'type' => 'integer',
                'requiredOnCreate' => false,
                'requiredOnUpdate' => false,
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