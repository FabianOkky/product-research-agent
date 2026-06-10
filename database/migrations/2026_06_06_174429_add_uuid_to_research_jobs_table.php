<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds an opaque UUID used as the public route key, so research URLs are not
     * sequential/guessable integers that leak how many jobs exist or their order.
     */
    public function up(): void
    {
        Schema::table('research_jobs', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        // Backfill any pre-existing rows so every job has a UUID before the index.
        foreach (DB::table('research_jobs')->whereNull('uuid')->pluck('id') as $id) {
            DB::table('research_jobs')->where('id', $id)->update(['uuid' => (string) Str::uuid()]);
        }

        Schema::table('research_jobs', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('research_jobs', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
