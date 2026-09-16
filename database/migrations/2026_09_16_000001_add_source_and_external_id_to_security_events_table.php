<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_events', function (Blueprint $table): void {
            $table->string('source', 100)->nullable()->after('title')->index();
            $table->uuid('external_event_id')->nullable()->after('source')->unique();
        });
    }

    public function down(): void
    {
        Schema::table('security_events', function (Blueprint $table): void {
            $table->dropUnique(['external_event_id']);
            $table->dropColumn(['source', 'external_event_id']);
        });
    }
};
