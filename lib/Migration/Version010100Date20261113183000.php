<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010100Date20261113183000 extends SimpleMigrationStep {
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

        if (!$schema->hasTable('sfxon_train_user')) {
            $table = $schema->createTable('sfxon_train_user');
            
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

            $table->addColumn('user_id', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);

            // 1 = active, 2 = suspended, 3 = ended
            $table->addColumn('status', Types::SMALLINT, [
                'notnull' => true,
                'default' => 1,
            ]);

            $table->addColumn('next_due_date', Types::DATE_IMMUTABLE, [
                'notnull' => false,
            ]);

            $table->addColumn('suspended_until', Types::DATE_IMMUTABLE, [
                'notnull' => false,
            ]);

            $table->addColumn('suspended_reason', Types::STRING, [
                'notnull' => false,
                'length' => 255,
            ]);

            $table->addColumn('ended_at', Types::DATETIME_IMMUTABLE, [
                'notnull' => false,
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