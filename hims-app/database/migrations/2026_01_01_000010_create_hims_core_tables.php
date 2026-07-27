<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->char('department_id', 36)->primary();
            $table->string('name', 150)->unique();
            $table->string('department_code', 20)->unique()->nullable();
            $table->char('head_employee_id', 36)->nullable();
            $table->char('parent_dept_id', 36)->nullable();
            $table->boolean('is_clinical')->default(true);
            $table->timestamps();

            $table->foreign('parent_dept_id')->references('department_id')->on('departments')->nullOnDelete();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->char('role_id', 36)->primary();
            $table->string('role_name', 100)->unique();
            $table->string('role_slug', 50)->unique();
            $table->char('department_id', 36)->nullable();
            $table->boolean('is_clinical')->default(true);
            $table->timestamps();

            $table->foreign('department_id')->references('department_id')->on('departments')->nullOnDelete();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->char('employee_id', 36)->primary();
            $table->string('employee_code', 30)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255)->unique();
            $table->string('phone', 100)->nullable(); // encrypted
            $table->char('department_id', 36);
            $table->char('role_id', 36);
            $table->string('position_title', 200)->nullable();
            $table->date('hire_date');
            $table->string('employment_status', 20)->default('active');
            $table->char('supervisor_id', 36)->nullable();
            $table->text('profile_image_url')->nullable();
            $table->timestamps();

            $table->foreign('department_id')->references('department_id')->on('departments');
            $table->foreign('role_id')->references('role_id')->on('roles');
            $table->foreign('supervisor_id')->references('employee_id')->on('employees')->nullOnDelete();
        });

        // Now add head_employee_id FK (circular dependency resolved)
        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('head_employee_id')->references('employee_id')->on('employees')->nullOnDelete();
        });

        Schema::create('system_users', function (Blueprint $table) {
            $table->char('user_id', 36)->primary();
            $table->char('employee_id', 36)->unique();
            $table->string('username', 50)->unique();
            $table->string('password_hash', 255);
            $table->string('access_role', 30);
            $table->boolean('mfa_enabled')->default(false);
            $table->text('mfa_secret')->nullable(); // encrypted
            $table->timestamp('last_login')->nullable();
            $table->integer('failed_login_count')->default(0);
            $table->boolean('account_locked')->default(false);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('password_changed_at')->useCurrent();
            $table->boolean('must_change_password')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('employee_id')->references('employee_id')->on('employees');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->char('notification_id', 36)->primary();
            $table->char('recipient_id', 36);
            $table->string('notification_type', 50);
            $table->string('title', 300);
            $table->text('message')->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->char('reference_id', 36)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('recipient_id')->references('employee_id')->on('employees');
            $table->index(['recipient_id', 'is_read', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::table('system_users', fn($t) => $t->dropForeign(['employee_id']));
        Schema::dropIfExists('system_users');
        Schema::table('departments', fn($t) => $t->dropForeign(['head_employee_id']));
        Schema::dropIfExists('employees');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('departments');
    }
};
