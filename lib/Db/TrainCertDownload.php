<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int|null getId()
 * @method \DateTime|null getDownloadedAt()
 * @method void setDownloadedAt(\DateTime|null $downloadedAt)
 * @method string|null getDownloadedBy()
 * @method void setDownloadedBy(string|null $downloadedBy)
 * @method string|null getRole()
 * @method void setRole(string|null $role)
 * @method int|null getRunId()
 * @method void setRunId(int|null $runId)
 */
class TrainCertDownload extends Entity implements \JsonSerializable {
    use TEntity;

    protected ?\DateTime $downloadedAt = null;
    protected ?string $downloadedBy = null;
    protected ?string $role = null;
    protected ?int $runId = null;

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('downloadedAt', 'datetime');
        $this->addType('runId', 'integer');
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
                'label' => 'Downloaded At',
                'length' => null,
                'name' => 'downloaded_at',
                'propertyName' => 'downloadedAt',
                'type' => 'datetime',
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
                'label' => 'Downloaded By',
                'length' => 64,
                'name' => 'downloaded_by',
                'propertyName' => 'downloadedBy',
                'type' => 'string',
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
                'label' => 'Role',
                'length' => 16,
                'name' => 'role',
                'propertyName' => 'role',
                'type' => 'string',
                'requiredOnCreate' => true,
                'requiredOnUpdate' => true,
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array {
        return $this->jsonSerializeFields();
    }
}