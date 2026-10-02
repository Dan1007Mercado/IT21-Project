<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authentication_logs', function (Blueprint $table): void {
            $table->string('source', 100)->default('intsec')->after('id');
            $table->index(['source', 'occurred_at']);
            $table->index(['source', 'status', 'occurred_at']);
        });

        Schema::table('security_alerts', function (Blueprint $table): void {
            $table->string('source', 100)->default('intsec')->after('alert_id');
            $table->index(['source', 'occurred_at']);
            $table->index(['source', 'status', 'occurred_at']);
        });

        Schema::table('incidents', function (Blueprint $table): void {
            $table->string('source', 100)->default('intsec')->after('incident_id');
            $table->index(['source', 'last_detected_at']);
            $table->index(['source', 'status']);
        });

        DB::table('request_activities')->whereIn('source', ['local', 'demo'])->update(['source' => 'intsec']);
        DB::table('security_events')->whereNull('source')->orWhere('source', 'local')->update(['source' => 'intsec']);

        Schema::table('request_activities', function (Blueprint $table): void {
            $table->string('source', 100)->default('intsec')->change();
        });
        Schema::table('security_events', function (Blueprint $table): void {
            $table->string('source', 100)->default('intsec')->nullable(false)->change();
        });

        Schema::table('request_activities', function (Blueprint $table): void {
            $table->dropUnique(['request_id']);
            $table->unique(['source', 'request_id']);
            $table->index(['source', 'occurred_at']);
            $table->index(['source', 'ip_address', 'occurred_at']);
            $table->index(['source', 'status_code', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::table('security_events', function (Blueprint $table): void {
            $table->string('source', 100)->nullable()->default(null)->change();
        });
        Schema::table('request_activities', function (Blueprint $table): void {
            $table->string('source', 100)->default('local')->change();
        });

        Schema::table('request_activities', function (Blueprint $table): void {
            $table->dropUnique(['source', 'request_id']);
            $table->unique('request_id');
            $table->dropIndex(['source', 'occurred_at']);
            $table->dropIndex(['source', 'ip_address', 'occurred_at']);
            $table->dropIndex(['source', 'status_code', 'occurred_at']);
        });

        Schema::table('incidents', function (Blueprint $table): void {
            $table->dropIndex(['source', 'last_detected_at']);
            $table->dropIndex(['source', 'status']);
            $table->dropColumn('source');
        });

        Schema::table('security_alerts', function (Blueprint $table): void {
            $table->dropIndex(['source', 'occurred_at']);
            $table->dropIndex(['source', 'status', 'occurred_at']);
            $table->dropColumn('source');
        });

        Schema::table('authentication_logs', function (Blueprint $table): void {
            $table->dropIndex(['source', 'occurred_at']);
            $table->dropIndex(['source', 'status', 'occurred_at']);
            $table->dropColumn('source');
        });
    }
};
