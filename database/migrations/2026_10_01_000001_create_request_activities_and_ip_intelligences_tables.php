<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_activities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->string('source', 100)->default('local')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('ip_type', 20)->default('invalid')->index();
            $table->string('method', 10);
            $table->string('path', 2048);
            $table->string('route_name')->nullable()->index();
            $table->unsignedSmallInteger('status_code')->index();
            $table->text('user_agent')->nullable();
            $table->text('referer')->nullable();
            $table->boolean('is_authenticated')->default(false)->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedBigInteger('request_size')->nullable();
            $table->unsignedBigInteger('response_size')->nullable();
            $table->string('classification', 80)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['ip_address', 'occurred_at']);
            $table->index(['status_code', 'occurred_at']);
            $table->index(['route_name', 'occurred_at']);
        });

        Schema::create('ip_intelligences', function (Blueprint $table): void {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->string('ip_type', 20)->index();
            $table->string('country')->nullable();
            $table->string('country_code', 8)->nullable()->index();
            $table->string('region')->nullable();
            $table->string('region_code', 32)->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();
            $table->string('postal', 32)->nullable();
            $table->string('timezone')->nullable();
            $table->unsignedBigInteger('asn')->nullable()->index();
            $table->string('isp')->nullable();
            $table->string('organization')->nullable();
            $table->string('provider', 80)->nullable();
            $table->timestamp('last_enriched_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_intelligences');
        Schema::dropIfExists('request_activities');
    }
};
