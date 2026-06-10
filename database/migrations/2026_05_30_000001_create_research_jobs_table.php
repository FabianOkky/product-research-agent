<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');   // pending | processing | done | failed
            $table->string('current_step')->nullable();      // teks langkah utk progress tracker
            $table->unsignedTinyInteger('progress')->default(0); // 0-100 (opsional)
            $table->text('user_input');                       // cerita kebutuhan user
            $table->json('extracted_params')->nullable();     // {category, use_case, budget, priorities}
            $table->json('queries')->nullable();              // ["q1","q2","q3"]
            $table->json('sources')->nullable();              // [{query, url, title}]
            $table->longText('report')->nullable();           // laporan akhir (markdown)
            $table->text('error')->nullable();                // pesan error bila failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_jobs');
    }
};
