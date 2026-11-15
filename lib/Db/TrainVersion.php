<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int|null getId()
 * @method string|null getDescription()
 * @method void setDescription(string|null $description)
 * @method int|null getJsonSchemaVersion()
 * @method void setJsonSchemaVersion(int|null $jsonSchemaVersion)
 * @method int|null getPassPercent()
 * @method void setPassPercent(int|null $passPercent)
 * @method int|null getRepeatIntervalMonths()
 * @method void setRepeatIntervalMonths(int|null $repeatIntervalMonths)
 * @method int|null getStatus()
 * @method void setStatus(int|null $status)
 * @method string|null getTitle()
 * @method void setTitle(string|null $title)
 * @method int|null getTrainId()
 * @method void setTrainId(int|null $trainId)
 * @method int|null getVersionNumber()
 * @method void setVersionNumber(int|null $versionNumber)
 */
class TrainVersion extends Entity implements \JsonSerializable {
    use TEntity;

    protected ?string $description = null;
    protected ?int $jsonSchemaVersion = null;
    protected ?int $passPercent = null;
    protected ?int $repeatIntervalMonths = null;
    protected ?int $status = null;
    protected ?string $title = null;
    protected ?int $trainId = null;
    protected ?int $versionNumber = null;

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('jsonSchemaVersion', 'integer');
        $this->addType('passPercent', 'integer');
        $this->addType('repeatIntervalMonths', 'integer');
        $this->addType('status', 'integer');
        $this->addType('trainId', 'integer');
        $this->addType('versionNumber', 'integer');
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
                'dbType' => 'TEXT',
                'defaultValue' => NULL,
                'filterType' => 'none',
                'foreignEntity' => false,
                'index' => false,
                'label' => 'Description',
                'length' => null,
                'name' => 'description',
                'propertyName' => 'description',
                'type' => 'text',
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
                'label' => 'JSON Schema Version',
                'length' => null,
                'name' => 'json_schema_version',
                'propertyName' => 'jsonSchemaVersion',
                'type' => 'integer',
                'requiredOnCreate' => false,
                'requiredOnUpdate' => false,
                'unique' => false,
            ],
            [
                'dbType' => 'INT',
                'defaultValue' => NULL,
                'filterType' => 'numericFromTo',
                'foreignEntity' => false,
                'index' => false,
                'label' => 'Pass Percent',
                'length' => null,
                'name' => 'pass_percent',
                'propertyName' => 'passPercent',
                'type' => 'integer',
                'requiredOnCreate' => false,
                'requiredOnUpdate' => false,
                'unique' => false,
            ],
            [
                'dbType' => 'INT',
                'defaultValue' => NULL,
                'filterType' => 'numericFromTo',
                'foreignEntity' => false,
                'index' => false,
                'label' => 'Repeat Interval Months',
                'length' => null,
                'name' => 'repeat_interval_months',
                'propertyName' => 'repeatIntervalMonths',
                'type' => 'integer',
                'requiredOnCreate' => false,
                'requiredOnUpdate' => false,
                'unique' => false,
            ],
            [
                'dbType' => 'INT',
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
                'label' => 'Title',
                'length' => 300,
                'name' => 'title',
                'propertyName' => 'title',
                'type' => 'string',
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
                'dbType' => 'INT',
                'defaultValue' => 0,
                'filterType' => 'numericFromTo',
                'foreignEntity' => false,
                'index' => false,
                'label' => 'Version Number',
                'length' => null,
                'name' => 'version_number',
                'propertyName' => 'versionNumber',
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