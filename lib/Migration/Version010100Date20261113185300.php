<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

// The table is a pure append-only-memory. There are no updates or deletes, except by the retention period.
class Version010100Date20261113185300 extends SimpleMigrationStep {
    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array $options
     */
    public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
    }

    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array $options
     * @return null|ISchemaWrapper
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('sfxon_train_log')) {
            $table = $schema->createTable('sfxon_train_log');
            
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 20,
                'unsigned' => true,
            ]);

            $table->addColumn('foreign_key', Types::BIGINT, [
                'notnull' => false,
                'default' => null,
                'unsigned' => true,
            ]);

            $table->addColumn('foreign_entity', Types::STRING, [
                'notnull' => false,
                'default' => null,
                'length' => 256,
            ]);

            $table->addColumn('user_id', Types::STRING, [
                'notnull' => false,
                'length' => 64,
            ]);

             $table->addColumn('action', Types::STRING, [
                'notnull' => false,
                'length' => 64,
            ]);

            $table->addColumn('details_json', Types::TEXT, [
                'notnull' => false,
            ]);

            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, [
                'notnull' => true,
            ]);

            $table->setPrimaryKey(['id']);
        }

        return $schema;
    }

    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array $options
     */
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
}
}