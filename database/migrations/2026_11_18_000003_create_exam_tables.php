<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ujian, jadwal, percobaan, jawaban, instance perangkat, perintah pengawas. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('type', 30)->default('UH');      // UH | PTS | PAS | PAT | TO | Lainnya
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_bank_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('question_count')->default(0); // 0 = semua soal bank
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->unsignedTinyInteger('kkm')->default(75);
            $table->json('settings')->nullable();            // Spesifikasi 5.6
            $table->timestamps();
        });

        Schema::create('exam_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('exam_session_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->dateTime('opens_at');
            $table->dateTime('closes_at');
            $table->string('status', 12)->default('scheduled'); // scheduled | open | paused | closed
            $table->string('token_key', 64);                     // acak, untuk ExamTokenService
            $table->boolean('token_required')->default(true);
            $table->json('ip_allowlist')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'opens_at']);
            $table->index(['classroom_id', 'date']);
        });

        Schema::create('exam_schedule_student', function (Blueprint $table) {
            $table->foreignId('exam_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 30)->nullable();        // susulan | remedial | khusus
            $table->primary(['exam_schedule_id', 'student_id']);
        });

        Schema::create('exam_schedule_user', function (Blueprint $table) {
            $table->foreignId('exam_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // pengawas
            $table->primary(['exam_schedule_id', 'user_id']);
        });

        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('not_started'); // not_started|in_progress|warned|locked|submitted|force_submitted
            $table->string('shield_status', 10)->default('active'); // active|warned|locked|done
            $table->unsignedSmallInteger('warning_count')->default(0);
            $table->unsignedInteger('last_incident_at')->nullable(); // detik Unix (ViolationPolicy)
            $table->string('lock_reason', 40)->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('package', 2)->nullable();
            $table->json('question_order')->nullable();   // [question_id, ...]
            $table->json('option_order')->nullable();     // {question_id: {display_key: original_key}}
            $table->timestamp('started_at')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedSmallInteger('extra_minutes')->default(0);
            $table->string('active_instance_id', 64)->nullable();
            $table->string('heartbeat_nonce', 40)->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('gap_reported_at')->nullable();
            $table->unsignedInteger('last_event_seq')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('device_info')->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->json('score_detail')->nullable();
            $table->boolean('needs_grading')->default(false);
            $table->timestamps();
            $table->unique(['exam_schedule_id', 'student_id']);
            $table->index(['exam_schedule_id', 'status']);
            $table->index('last_heartbeat_at');
        });

        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->json('answer')->nullable();             // dalam kunci ASLI (sudah dipetakan dari kunci tampilan)
            $table->unsignedInteger('rev')->default(0);
            $table->boolean('is_doubtful')->default(false);
            $table->boolean('is_correct')->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('answered_at')->nullable();
            $table->unsignedInteger('first_answered_sec')->nullable(); // detik sejak mulai (analitik kecepatan)
            $table->timestamps();
            $table->unique(['exam_attempt_id', 'question_id']);
        });

        Schema::create('attempt_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->string('instance_id', 64);
            $table->string('os', 20)->nullable();
            $table->string('browser', 20)->nullable();
            $table->string('device', 10)->nullable();
            $table->boolean('standalone')->default(false);
            $table->string('ip', 45)->nullable();
            $table->string('fingerprint', 64)->nullable();  // hash(ua + layar) untuk "satu perangkat banyak akun"
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unique(['exam_attempt_id', 'instance_id']);
            $table->index('fingerprint');
        });

        Schema::create('attempt_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                     // message | extend | force_submit | reload | unlock
            $table->json('payload')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['exam_attempt_id', 'delivered_at']);
        });
    }

    public function down(): void
    {
        foreach (['attempt_commands', 'attempt_instances', 'attempt_answers', 'exam_attempts', 'exam_schedule_user', 'exam_schedule_student', 'exam_schedules', 'exams'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
