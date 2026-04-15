<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;
use Waaseyaa\Foundation\Migration\TableBuilder;

return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('trace_span', function (TableBuilder $table): void {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('trace_uuid', 36);
            $table->string('parent_span_uuid', 36)->nullable();
            $table->string('kind', 32);
            $table->string('name', 255);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('status', 32);
            $table->text('attributes');
            $table->index('trace_uuid', 'idx_trace_span_trace_uuid');
            $table->index(['trace_uuid', 'kind'], 'idx_trace_span_trace_kind');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('trace_span');
    }
};
