<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010100Date20261113182200 extends SimpleMigrationStep {
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

        if (!$schema->hasTable('sfxon_train_version')) {
            $table = $schema->createTable('sfxon_train_version');
            
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 20,
                'unsigned' => true,
            ]);

            $table->addColumn('train_id', Types::BIGINT, [
                'notnull' => true,
                'unsigned' => true,
            ]);

            $table->addColumn('version_number', Types::INTEGER, [
                'notnull' => true,
                'default' => 0,
            ]);

            // 1 = Draft, 2 = published, 3 = archived
            $table->addColumn('status', Types::INTEGER, [
                'notnull' => true,
                'default' => 1,
            ]);

            $table->addColumn('title', Types::STRING, [
                'notnull' => false,
                'length' => 300,
            ]);

            $table->addColumn('description', Types::TEXT, [
                'notnull' => false,
            ]);

            // Version of the JSON format.
            $table->addColumn('json_schema_version', Types::INTEGER, [
                'notnull' => true,
                'default' => 0,
            ]);

            // null = no repeating
            $table->addColumn('repeat_interval_months', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
            ]);

            // null = no test. Percent a user has to reach in the questions, to successfully pass.
            $table->addColumn('pass_percent', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
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