<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Data master, siswa, pengaturan, log aktivitas (Spesifikasi Bagian 6). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20);                // 2026/2027
            $table->string('semester', 10);            // ganjil | genap
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 60)->unique();      // XII IPS 1
            $table->string('grade', 10)->nullable();   // X / XI / XII
            $table->foreignId('homeroom_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->timestamps();
        });

        Schema::create('subject_user', function (Blueprint $table) {
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['subject_id', 'user_id']);
        });

        Schema::create('classroom_user', function (Blueprint $table) {
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['classroom_id', 'user_id']);
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->timestamps();
        });

        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);                // Sesi 1
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->char('nisn', 10)->unique();
            $table->string('nis', 30)->nullable();
            $table->string('name', 120);
            $table->char('gender', 1)->nullable();     // L | P
            $table->string('birth_place', 80)->nullable();
            $table->date('birth_date');
            $table->string('password');                // bcrypt(DDMMYYYY)
            $table->boolean('must_change_password')->default(false);
            $table->string('photo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('failed_logins')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->json('accommodations')->nullable(); // {extra_minutes, large_font, disable_canvas}
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->string('current_session_id', 100)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->index('classroom_id');
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);              // login, unlock, reset_login, export, ...
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['action', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        foreach (['activity_logs', 'students', 'exam_sessions', 'rooms', 'classroom_user', 'subject_user', 'subjects', 'classrooms', 'academic_years', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
