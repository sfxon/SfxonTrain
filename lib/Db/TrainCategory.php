<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int|null getId()
 * @method string|null getTitle()
 * @method void setTitle(string|null $title)
 */
class TrainCategory extends Entity implements \JsonSerializable {
    use TEntity;

    protected ?string $title = null;

    public function __construct() {
        $this->addType('id', 'integer');
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
                'label' => 'Title',
                'length' => 300,
                'name' => 'title',
                'propertyName' => 'title',
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