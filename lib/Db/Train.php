<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int|null getId()
 * @method int|null getActive()
 * @method void setActive(int|null $active)
 * @method int|null getCurrentVersionId()
 * @method void setCurrentVersionId(int|null $currentVersionId)
 * @method int|null getTrainCategoryId()
 * @method void setTrainCategoryId(int|null $trainCategoryId)
 */
class Train extends Entity implements \JsonSerializable {
    use TEntity;

    protected ?int $active = null;
    protected ?int $currentVersionId = null;
    protected ?int $trainCategoryId = null;

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('active', 'integer');
        $this->addType('currentVersionId', 'integer');
        $this->addType('trainCategoryId', 'integer');
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
                'dbType' => 'SMALLINT',
                'defaultValue' => 1,
                'filterType' => 'numericFromTo',
                'foreignEntity' => false,
                'index' => false,
                'label' => 'Active',
                'length' => null,
                'name' => 'active',
                'propertyName' => 'active',
                'type' => 'integer',
                'requiredOnCreate' => false,
                'requiredOnUpdate' => false,
                'unique' => false,
            ],
            [
                'dbType' => 'BIGINT',
                'defaultValue' => NULL,
                'filterType' => 'in',
                'foreignEntity' => 'train_version',
                'index' => true,
                'label' => 'Current Version',
                'length' => null,
                'name' => 'current_version_id',
                'propertyName' => 'currentVersionId',
                'type' => 'foreignKey',
                'requiredOnCreate' => false,
                'requiredOnUpdate' => false,
                'unique' => false,
            ],
            [
                'dbType' => 'BIGINT',
                'defaultValue' => NULL,
                'filterType' => 'in',
                'foreignEntity' => 'train_category',
                'index' => true,
                'label' => 'Train Category',
                'length' => null,
                'name' => 'train_category_id',
                'propertyName' => 'trainCategoryId',
                'type' => 'foreignKey',
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