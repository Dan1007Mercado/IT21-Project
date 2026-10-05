<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'totp_secret')) {
            Schema::table('users', fn (Blueprint $table) => $table->text('totp_secret')->nullable()->after('remember_token'));
        }
        if (! Schema::hasColumn('users', 'pending_totp_secret')) {
            Schema::table('users', fn (Blueprint $table) => $table->text('pending_totp_secret')->nullable()->after('totp_secret'));
        }
        if (! Schema::hasColumn('users', 'totp_confirmed_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->timestamp('totp_confirmed_at')->nullable()->after('pending_totp_secret'));
        }
        if (! Schema::hasColumn('users', 'mfa_enabled_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->timestamp('mfa_enabled_at')->nullable()->after('totp_confirmed_at')->index());
        }
        if (! Schema::hasColumn('users', 'recovery_codes_confirmed_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->timestamp('recovery_codes_confirmed_at')->nullable()->after('mfa_enabled_at'));
        }
        if (! Schema::hasColumn('users', 'last_totp_timestep')) {
            Schema::table('users', fn (Blueprint $table) => $table->unsignedBigInteger('last_totp_timestep')->nullable()->after('recovery_codes_confirmed_at'));
        }

        if (! Schema::hasTable('mfa_otp_challenges')) {
            Schema::create('mfa_otp_challenges', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('purpose', 40);
                $table->string('otp_hash');
                $table->unsignedTinyInteger('attempt_count')->default(0);
                $table->dateTime('expires_at');
                $table->dateTime('last_sent_at');
                $table->dateTime('used_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'purpose', 'used_at']);
                $table->index('expires_at');
            });
        }

        if (! Schema::hasTable('user_recovery_codes')) {
            Schema::create('user_recovery_codes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->uuid('generation_id')->index();
                $table->string('code_hash');
                $table->dateTime('consumed_at')->nullable()->index();
                $table->timestamps();

                $table->index(['user_id', 'generation_id', 'consumed_at'], 'recovery_user_generation_consumed');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_recovery_codes');
        Schema::dropIfExists('mfa_otp_challenges');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'totp_secret',
                'pending_totp_secret',
                'totp_confirmed_at',
                'mfa_enabled_at',
                'recovery_codes_confirmed_at',
                'last_totp_timestep',
            ]);
        });
    }
};
