<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_alerts', function (Blueprint $table): void {
            $table->foreignId('assigned_to')->nullable()->after('acknowledged_by')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_to');
        });

        Schema::create('security_alert_remarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('security_alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remark');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_alert_remarks');

        Schema::table('security_alerts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn('assigned_at');
        });
    }
};
