<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int|null getId()
 * @method int|null getAttemptNo()
 * @method void setAttemptNo(int|null $attemptNo)
 * @method int|null getCertificateFileId()
 * @method void setCertificateFileId(int|null $certificateFileId)
 * @method \DateTime|null getFinishedAt()
 * @method void setFinishedAt(\DateTime|null $finishedAt)
 * @method \DateTime|null getPdfGeneratedAt()
 * @method void setPdfGeneratedAt(\DateTime|null $pdfGeneratedAt)
 * @method int|null getPointsAchieved()
 * @method void setPointsAchieved(int|null $pointsAchieved)
 * @method int|null getPointsMax()
 * @method void setPointsMax(int|null $pointsMax)
 * @method string|null getProgressJson()
 * @method void setProgressJson(string|null $progressJson)
 * @method string|null getQuestionAnswersJson()
 * @method void setQuestionAnswersJson(string|null $questionAnswersJson)
 * @method int|null getScorePercent()
 * @method void setScorePercent(int|null $scorePercent)
 * @method \DateTime|null getStartedAt()
 * @method void setStartedAt(\DateTime|null $startedAt)
 * @method int|null getStatus()
 * @method void setStatus(int|null $status)
 * @method int|null getTrainUserId()
 * @method void setTrainUserId(int|null $trainUserId)
 * @method int|null getTrainVersionId()
 * @method void setTrainVersionId(int|null $trainVersionId)
 * @method \DateTime|null getValidUntil()
 * @method void setValidUntil(\DateTime|null $validUntil)
 */
class TrainUserRun extends Entity implements \JsonSerializable {
    use TEntity;

    protected ?int $attemptNo = null;
    protected ?int $certificateFileId = null;
    protected ?\DateTime $finishedAt = null;
    protected ?\DateTime $pdfGeneratedAt = null;
    protected ?int $pointsAchieved = null;
    protected ?int $pointsMax = null;
    protected ?string $progressJson = null;
    protected ?string $questionAnswersJson = null;
    protected ?int $scorePercent = null;
    protected ?\DateTime $startedAt = null;
    protected ?int $status = null;
    protected ?int $trainUserId = null;
    protected ?int $trainVersionId = null;
    protected ?\DateTime $validUntil = null;

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('attemptNo', 'integer');
        $this->addType('certificateFileId', 'integer');
        $this->addType('finishedAt', 'datetime');
        $this->addType('pdfGeneratedAt', 'datetime');
        $this->addType('pointsAchieved', 'integer');
        $this->addType('pointsMax', 'integer');
        $this->addType('scorePercent', 'integer');
        $this->addType('startedAt', 'datetime');
        $this->addType('status', 'integer');
        $this->addType('trainUserId', 'integer');
        $this->addType('trainVersionId', 'integer');
        $this->addType('validUntil', 'datetime');
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
                'defaultValue' => 0,
                'filterType' => 'numericFromTo',
                'foreignEntity' => false,
                'index' => false,
                'label' => 'Attempt No',
                'length' => null,
                'name' => 'attempt_no',
                'propertyName' => 'attemptNo',
                'type' => 'integer',
                'requiredOnCreate' => false,
                'requiredOnUpdate' => false,
                'unique' => false,
            ],
            [
                'dbType' => 'BIGINT',
                'defaultValue' => NULL,
                'filterType' => 'in',
                'foreignEntity' => 'file',
                'index' => true,
                'label' => 'Certificate File',
                'length' => null,
                'name' => 'certificate_file_id',
                'propertyName' => 'certificateFileId',
                'type' => 'foreignKey',
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
                'label' => 'Finished At',
                'length' => null,
                'name' => 'finished_at',
                'propertyName' => 'finishedAt',
                'type' => 'datetime',
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
                'label' => 'PDF Generated At',
                'length' => null,
                'name' => 'pdf_generated_at',
                'propertyName' => 'pdfGeneratedAt',
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
                'label' => 'Points Achieved',
                'length' => null,
                'name' => 'points_achieved',
                'propertyName' => 'pointsAchieved',
                'type' => 'integer',
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
                'label' => 'Points Max',
                'length' => null,
                'name' => 'points_max',
                'propertyName' => 'pointsMax',
                'type' => 'integer',
                'requiredOnCreate' => false,
                'requiredOnUpdate' => false,
                'unique' => false,
            ],
            [
                'dbType' => 'TEXT',
                'defaultValue' => NULL,
                'filterType' => 'none',
                'foreignEntity' => false,
                'index' => false,
                'label' => 'Progress JSON',
                'length' => null,
                'name' => 'progress_json',
                'propertyName' => 'progressJson',
                'type' => 'text',
                'requiredOnCreate' => false,
                'requiredOnUpdate' => false,
                'unique' => false,
            ],
            [
                'dbType' => 'TEXT',
                'defaultValue' => NULL,
                'filterType' => 'none',
                'foreignEntity' => false,
                'index' => false,
                'label' => 'Question Answers JSON',
                'length' => null,
                'name' => 'question_answers_json',
                'propertyName' => 'questionAnswersJson',
                'type' => 'text',
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
                'label' => 'Score Percent',
                'length' => null,
                'name' => 'score_percent',
                'propertyName' => 'scorePercent',
                'type' => 'integer',
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
                'label' => 'Started At',
                'length' => null,
                'name' => 'started_at',
                'propertyName' => 'startedAt',
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
                'dbType' => 'BIGINT',
                'defaultValue' => NULL,
                'filterType' => 'in',
                'foreignEntity' => 'train_user',
                'index' => true,
                'label' => 'Train User',
                'length' => null,
                'name' => 'train_user_id',
                'propertyName' => 'trainUserId',
                'type' => 'foreignKey',
                'requiredOnCreate' => true,
                'requiredOnUpdate' => true,
                'unique' => false,
            ],
            [
                'dbType' => 'BIGINT',
                'defaultValue' => NULL,
                'filterType' => 'in',
                'foreignEntity' => 'train_version',
                'index' => true,
                'label' => 'Train Version',
                'length' => null,
                'name' => 'train_version_id',
                'propertyName' => 'trainVersionId',
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
                'label' => 'Valid Until',
                'length' => null,
                'name' => 'valid_until',
                'propertyName' => 'validUntil',
                'type' => 'datetime',
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