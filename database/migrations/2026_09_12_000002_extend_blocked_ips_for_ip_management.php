<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend the existing blocked_ips table into the full IP Management
     * rule store (ALLOW / BLOCK, CIDR, source tracking, match stats).
     *
     * No data is destroyed. All new columns are nullable or have defaults
     * so existing block records keep working unchanged.
     */
    public function up(): void
    {
        Schema::table('blocked_ips', function (Blueprint $table) {
            // Widen to fit IPv6 CIDR notation (e.g. 2001:db8::/32).
            $table->string('ip_address', 64)->change();

            // ALLOW / BLOCK decision. Existing rows default to block.
            $table->string('action', 10)->default('block')->after('ip_address')->index();
            // Enabled flag, separate from status/expires so admins can
            // temporarily switch a rule off without losing history.
            $table->boolean('is_enabled')->default(true)->after('action')->index();
            // manual | automatic | alert | incident | system
            $table->string('source', 20)->default('manual')->after('is_enabled')->index();
            // Optional human-readable reference/name for the rule.
            $table->string('name', 120)->nullable()->after('source');
            // Longer notes, separate from the short reason.
            $table->text('description')->nullable()->after('reason');

            // Traceability back to the alert/incident that caused the rule.
            $table->foreignId('alert_id')->nullable()->after('administrator_id')
                ->constrained('security_alerts')->nullOnDelete();
            $table->foreignId('incident_id')->nullable()->after('alert_id')
                ->constrained('incidents')->nullOnDelete();

            // Enforcement telemetry (does NOT affect decisions).
            $table->unsignedBigInteger('match_count')->default(0)->after('incident_id');
            $table->timestamp('last_matched_at')->nullable()->after('match_count')->index();
        });
    }

    public function down(): void
    {
        Schema::table('blocked_ips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('alert_id');
            $table->dropConstrainedForeignId('incident_id');
            $table->dropColumn([
                'action',
                'is_enabled',
                'source',
                'name',
                'description',
                'match_count',
                'last_matched_at',
            ]);
            $table->string('ip_address', 45)->change();
        });
    }
};
