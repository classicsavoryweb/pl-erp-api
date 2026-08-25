<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $foreignKey = $this->foreignKeyName();

        if ($foreignKey) {
            DB::statement("ALTER TABLE uniform_variances DROP FOREIGN KEY `{$foreignKey}`");
        }

        DB::statement('ALTER TABLE uniform_variances MODIFY physical_count_id BIGINT UNSIGNED NULL');

        DB::statement('ALTER TABLE uniform_variances ADD CONSTRAINT uniform_variances_physical_count_id_foreign FOREIGN KEY (physical_count_id) REFERENCES uniform_physical_counts (id) ON DELETE SET NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE uniform_variances DROP FOREIGN KEY uniform_variances_physical_count_id_foreign');

        DB::statement('ALTER TABLE uniform_variances MODIFY physical_count_id BIGINT UNSIGNED NOT NULL');

        DB::statement('ALTER TABLE uniform_variances ADD CONSTRAINT uniform_variances_physical_count_id_foreign FOREIGN KEY (physical_count_id) REFERENCES uniform_physical_counts (id)');
    }

    private function foreignKeyName(): ?string
    {
        $row = DB::selectOne(
            "SELECT CONSTRAINT_NAME as name FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'uniform_variances'
               AND COLUMN_NAME = 'physical_count_id'
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1"
        );

        return $row->name ?? null;
    }
};
