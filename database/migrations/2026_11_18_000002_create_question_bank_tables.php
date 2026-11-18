<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Bank soal (sama dengan KITAQ offline). answer_key TIDAK PERNAH dikirim ke siswa. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_banks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // pemilik (guru)
            $table->string('name', 150);
            $table->string('grade', 10)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('stimuli', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_bank_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150)->nullable();
            $table->longText('content');               // HTML tersanitasi
            $table->string('image')->nullable();
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_bank_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stimulus_id')->nullable()->constrained('stimuli')->nullOnDelete();
            $table->string('type', 10);                // pg | pgk | bs | jodoh | isian | uraian
            $table->longText('content');               // HTML tersanitasi
            $table->string('image')->nullable();
            $table->json('answer_key')->nullable();    // lihat App\Models\Question
            $table->decimal('weight', 6, 2)->default(1);
            $table->string('difficulty', 10)->nullable();   // mudah | sedang | sulit
            $table->string('cognitive_level', 10)->nullable(); // C1..C6 / L1..L3
            $table->longText('explanation')->nullable();
            $table->boolean('numeric')->default(false);     // isian angka → papan angka
            $table->unsignedSmallInteger('max_length')->nullable();
            $table->string('audio')->nullable();
            $table->unsignedTinyInteger('audio_limit')->nullable();
            $table->string('package', 2)->nullable();       // paket A/B/C
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['question_bank_id', 'position']);
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('key', 4);                  // A..E (pg/pgk), 1..n (bs/jodoh kiri), a..n (jodoh kanan)
            $table->string('side', 5)->nullable();     // null | left | right (menjodohkan)
            $table->text('content')->nullable();
            $table->string('image')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->index(['question_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('stimuli');
        Schema::dropIfExists('question_banks');
    }
};
