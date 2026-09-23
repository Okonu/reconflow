<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->timestampTz('occurred_at', 6)->index();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_label', 254);
            $table->string('action', 100)->index();
            $table->string('entity_type', 64)->nullable();
            $table->string('entity_id', 128)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->jsonb('payload');
            $table->char('prev_hash', 64);
            $table->char('hash', 64)->unique();
            $table->index(['entity_type', 'entity_id']);
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_events_guard() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND current_setting('reconflow.audit_archive', true) = 'on' THEN
                    RETURN OLD;
                END IF;
                RAISE EXCEPTION 'audit_events is append-only: % is not allowed', TG_OP;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER audit_events_row_guard BEFORE UPDATE OR DELETE ON audit_events
                FOR EACH ROW EXECUTE FUNCTION audit_events_guard();

            CREATE TRIGGER audit_events_truncate_guard BEFORE TRUNCATE ON audit_events
                FOR EACH STATEMENT EXECUTE FUNCTION audit_events_guard();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_events_truncate_guard ON audit_events');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_events_row_guard ON audit_events');
        Schema::dropIfExists('audit_events');
        DB::unprepared('DROP FUNCTION IF EXISTS audit_events_guard()');
    }
};
