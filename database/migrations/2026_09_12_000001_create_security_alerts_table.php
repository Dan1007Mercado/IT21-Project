<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_id')->unique();
            $table->string('title');
            $table->string('alert_type')->nullable()->index();
            $table->string('severity')->index();
            $table->text('description')->nullable();
            $table->foreignId('security_event_id')->nullable()->constrained('security_events')->nullOnDelete();
            $table->string('source_ip', 45)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->string('status')->default('new')->index();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable()->index();
            $table->foreignId('incident_id')->nullable()->constrained('incidents')->nullOnDelete();
            $table->timestamp('occurred_at')->index()->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_alerts');
    }
};
