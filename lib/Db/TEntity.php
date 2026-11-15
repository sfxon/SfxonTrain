<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Db;

/**
 * Serializes an entity based on its static getFieldDefinition().
 *
 * Must be used in classes that extend \OCP\AppFramework\Db\Entity and
 * provide a static getFieldDefinition() method.
 */
trait TEntity {
    /**
     * @return array<string, mixed>
     */
    protected function jsonSerializeFields(): array {
        $retval = [];

        foreach (static::getFieldDefinition() as $definition) {
            $property = $definition['propertyName'];
            $value = $this->getter($property);

            if ($value instanceof \DateTimeInterface) {
                $value = $value->format(
                    ($definition['type'] ?? null) === 'date' ? 'Y-m-d' : \DATE_ATOM
                );
            }

            $retval[$property] = $value;
        }

        return $retval;
    }
}