<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int|null getId()
 * @method string|null getAction()
 * @method void setAction(string|null $action)
 * @method \DateTime|null getCreatedAt()
 * @method void setCreatedAt(\DateTime|null $createdAt)
 * @method string|null getDetailsJson()
 * @method void setDetailsJson(string|null $detailsJson)
 * @method string|null getForeignEntity()
 * @method void setForeignEntity(string|null $foreignEntity)
 * @method int|null getForeignKey()
 * @method void setForeignKey(int|null $foreignKey)
 * @method string|null getUserId()
 * @method void setUserId(string|null $userId)
 */
class TrainLog extends Entity implements \JsonSerializable {
    use TEntity;

    protected ?string $action = null;
    protected ?\DateTime $createdAt = null;
    protected ?string $detailsJson = null;
    protected ?string $foreignEntity = null;
    protected ?int $foreignKey = null;
    protected ?string $userId = null;

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('createdAt', 'datetime');
        $this->addType('foreignKey', 'integer');
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
                'dbType' => 'VARCHAR',
                'defaultValue' => NULL,
                'filterType' => 'like',
                'foreignEntity' => false,
                'index' => false,
                'label' => 'Action',
                'length' => 64,
                'name' => 'action',
                'propertyName' => 'action',
                'type' => 'string',
                'requiredOnCreate' => false,
                'requiredOnUpdate' => false,
                'unique' => false,
            ],
            [
                'dbType' => 'DATETIME',
                'defaultValue' => NULL,
                'filterType' => 'none',
                'foreignEntity' => false,
                'index' => false,
                'label' => 'Created At',
                'length' => null,
                'name' => 'created_at',
                'propertyName' => 'createdAt',
                'type' => 'datetime',
                'requiredOnCreate' => true,
                'requiredOnUpdate' => true,
                'unique' => false,
            ],
            [
                'dbType' => 'TEXT',
                'defaultValue' => NULL,
                'filterType' => 'none',
                'foreignEntity' => false,
                'index' => false,
                'label' => 'Details JSON',
                'length' => null,
                'name' => 'details_json',
                'propertyName' => 'detailsJson',
                'type' => 'text',
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
                'label' => 'Foreign Entity',
                'length' => 256,
                'name' => 'foreign_entity',
                'propertyName' => 'foreignEntity',
                'type' => 'string',
                'requiredOnCreate' => false,
                'requiredOnUpdate' => false,
                'unique' => false,
            ],
            [
                'dbType' => 'BIGINT',
                'defaultValue' => NULL,
                'filterType' => 'numericFromTo',
                'foreignEntity' => false,
                'index' => false,
                'label' => 'Foreign Key',
                'length' => null,
                'name' => 'foreign_key',
                'propertyName' => 'foreignKey',
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
                'label' => 'User',
                'length' => 64,
                'name' => 'user_id',
                'propertyName' => 'userId',
                'type' => 'string',
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