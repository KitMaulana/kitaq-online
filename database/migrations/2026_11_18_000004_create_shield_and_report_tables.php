<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** KITAQ Shield (event, buka kunci, alarm pengawas), analitik integritas, ekspor antrean. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shield_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('seq')->nullable();     // null = event dari server (heartbeat_gap, second_instance, dll)
            $table->string('instance_id', 64)->nullable(); // seq unik per instance (tab/perangkat)
            $table->string('type', 40);
            $table->string('action', 8);                    // ignore | log | warn | lock
            $table->boolean('merged')->default(false);
            $table->json('detail')->nullable();
            $table->timestamp('client_at')->nullable();     // waktu kejadian (sudah dijepit server)
            $table->timestamp('received_at')->useCurrent();
            $table->unique(['exam_attempt_id', 'instance_id', 'seq']);
            $table->index(['exam_attempt_id', 'received_at']);
            $table->index(['type', 'received_at']);
        });

        Schema::create('unlock_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 500);
            $table->boolean('reset_warnings')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('monitor_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_attempt_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind', 30);                     // locked | second_instance | heartbeat_gap | login_blocked
            $table->string('message', 255);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['exam_schedule_id', 'id']);
        });

        Schema::create('integrity_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_schedule_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);                     // identical_wrong | speed | shared_device
            $table->json('student_ids');
            $table->decimal('score', 8, 2)->default(0);
            $table->json('detail')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('verdict', 20)->nullable();      // wajar | perlu_tindak | pelanggaran
            $table->string('note', 500)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['exam_schedule_id', 'kind']);
        });

        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);                     // recap_xlsx | recap_pdf | erapor_xlsx | violations_xlsx | students_xlsx
            $table->json('params')->nullable();
            $table->string('status', 12)->default('pending'); // pending | running | done | failed
            $table->string('path')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['report_exports', 'integrity_flags', 'monitor_alerts', 'unlock_logs', 'shield_events'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
