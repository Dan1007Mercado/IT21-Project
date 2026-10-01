<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_events', function (Blueprint $table): void {
            $table->foreignId('request_activity_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->foreignId('authentication_log_id')->nullable()->after('request_activity_id')->constrained()->nullOnDelete();
            $table->string('rule_key', 100)->nullable()->after('event_type')->index();
            $table->unsignedTinyInteger('risk_score')->nullable()->after('severity');
            $table->decimal('confidence', 5, 2)->nullable()->after('risk_score');
        });

        Schema::table('security_alerts', function (Blueprint $table): void {
            $table->string('rule_key', 100)->nullable()->after('alert_type')->index();
            $table->string('deduplication_key', 64)->nullable()->after('rule_key')->index();
            $table->unsignedInteger('occurrence_count')->default(1)->after('status');
            $table->timestamp('first_detected_at')->nullable()->after('occurred_at')->index();
            $table->timestamp('last_detected_at')->nullable()->after('first_detected_at')->index();
        });

        Schema::table('security_events', function (Blueprint $table): void {
            $table->dropUnique(['external_event_id']);
            $table->unique(['source', 'external_event_id']);
        });
    }

    public function down(): void
    {
        Schema::table('security_events', function (Blueprint $table): void {
            $table->dropUnique(['source', 'external_event_id']);
            $table->unique('external_event_id');
            $table->dropConstrainedForeignId('request_activity_id');
            $table->dropConstrainedForeignId('authentication_log_id');
            $table->dropColumn(['rule_key', 'risk_score', 'confidence']);
        });

        Schema::table('security_alerts', function (Blueprint $table): void {
            $table->dropColumn(['rule_key', 'deduplication_key', 'occurrence_count', 'first_detected_at', 'last_detected_at']);
        });
    }
};
