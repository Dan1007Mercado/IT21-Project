<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('authentication_logs', function (Blueprint $table) {
            $table->string('country')->nullable()->after('ip_address');
            $table->string('country_code')->nullable()->after('country');
            $table->string('region')->nullable()->after('country_code');
            $table->string('region_code')->nullable()->after('region');
            $table->string('city')->nullable()->after('region_code');
            $table->decimal('latitude', 10, 6)->nullable()->after('city');
            $table->decimal('longitude', 10, 6)->nullable()->after('latitude');
            $table->string('postal')->nullable()->after('longitude');
            $table->string('isp')->nullable()->after('postal');
            $table->string('organization')->nullable()->after('isp');
            $table->unsignedBigInteger('asn')->nullable()->after('organization');
            $table->string('timezone')->nullable()->after('asn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('authentication_logs', function (Blueprint $table) {
            $table->dropColumn([
                'country',
                'country_code',
                'region',
                'region_code',
                'city',
                'latitude',
                'longitude',
                'postal',
                'isp',
                'organization',
                'asn',
                'timezone',
            ]);
        });
    }
};
