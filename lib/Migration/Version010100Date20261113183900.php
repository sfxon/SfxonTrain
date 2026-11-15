<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010100Date20261113183900 extends SimpleMigrationStep {
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

        if (!$schema->hasTable('sfxon_train_user_run')) {
            $table = $schema->createTable('sfxon_train_user_run');
            
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 20,
                'unsigned' => true,
            ]);

            $table->addColumn('train_user_id', Types::BIGINT, [
                'notnull' => true,
                'unsigned' => true,
            ]);

            $table->addColumn('train_version_id', Types::BIGINT, [
                'notnull' => true,
                'unsigned' => true,
            ]);

            // How often the user started this run.
            $table->addColumn('attempt_no', Types::SMALLINT, [
                'notnull' => true,
                'default' => 0,
            ]);

            // 0 = open, 1 = in_progress, 2 = passed, 3 = failed, 4 = expired, 5 = cancelled.
            $table->addColumn('status', Types::INTEGER, [
                'notnull' => true,
                'default' => 0,
            ]);

            $table->addColumn('started_at', Types::DATETIME_IMMUTABLE, [
                'notnull' => false,
            ]);

            $table->addColumn('finished_at', Types::DATETIME_IMMUTABLE, [
                'notnull' => false,
            ]);

            $table->addColumn('points_achieved', Types::INTEGER, [
                'notnull' => true,
                'default' => 0,
            ]);

            $table->addColumn('points_max', Types::INTEGER, [
                'notnull' => true,
                'default' => 0,
            ]);

            $table->addColumn('score_percent', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
            ]);

            $table->addColumn('valid_until', Types::DATETIME_IMMUTABLE, [
                'notnull' => false,
            ]);

            $table->addColumn('question_answers_json', Types::TEXT, [
                'notnull' => false,
            ]);

            $table->addColumn('progress_json', Types::TEXT, [
                'notnull' => false,
            ]);

            $table->addColumn('certificate_file_id', Types::BIGINT, [
                'notnull' => false,
                'default' => null,
                'unsigned' => true,
            ]);

            $table->addColumn('pdf_generated_at', Types::DATETIME_IMMUTABLE, [
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