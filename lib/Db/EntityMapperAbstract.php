<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Db;

use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;

/**
 * Base class for all mappers of this app.
 *
 * @template-extends QBMapper<Entity>
 */
abstract class EntityMapperAbstract extends QBMapper
{
    /** @var array<string, array{type: string, entityClass: ?string}> column name => custom field info (type and optional sort definition) */
    protected array $customColumns = [];

    /**
     * @param array<string, array{type: string, entityClass: ?string}> $columns column name => custom field info (type and optional sort definition)
     */
    public function setCustomColumns(array $columns): void
    {
        $this->customColumns = $columns;
    }

    abstract public function countAll(?array $filters = null): int;

    abstract public function findAllPaged(
        string $orderBy = 'name',
        string $direction = 'ASC',
        int $limit = 20,
        int $offset = 0,
        ?array $filters = null,
        ?array $include = null ): array;

    abstract public function findById(int $id, ?array $include = null): array;
}