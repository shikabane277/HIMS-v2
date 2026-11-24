<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Formal awards with digital certificates and badges.
 *
 *   award_types      — the catalogue HR manages (name, category, criteria,
 *                      badge look, certificate wording, optional auto rule)
 *   employee_awards  — each award actually given, with a unique certificate
 *                      code that anyone can verify at /awards/verify/{code}
 *
 * Peer "kudos" stay in recognition_posts; this is the formal layer.
 * The starter catalogue follows common hospital, school and public-service HR
 * practice: length-of-service milestones, excellence and compassion awards,
 * PRAISE-style performance / innovation / conduct awards, team awards and
 * learning milestones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('award_types', function (Blueprint $table) {
            $table->char('award_type_id', 36)->primary();
            $table->string('name', 120)->unique();
            $table->string('category', 30);
            $table->text('description')->nullable();
            $table->text('criteria')->nullable();
            $table->string('badge_icon', 50)->default('bi-award');
            $table->string('badge_color', 7)->default('#16a34a');
            $table->string('certificate_title', 120)->default('Certificate of Recognition');
            $table->text('certificate_body')->nullable();
            $table->string('signatory_name', 120)->nullable();
            $table->string('signatory_title', 120)->nullable();
            $table->string('auto_rule', 30)->nullable();      // service_years | cpd_hours_year
            $table->integer('auto_value')->nullable();
            $table->integer('points_value')->default(10);
            $table->boolean('is_active')->default(true);
            $table->char('created_by', 36)->nullable();
            $table->timestamps();
            $table->index(['auto_rule', 'is_active']);
        });

        Schema::create('employee_awards', function (Blueprint $table) {
            $table->char('award_id', 36)->primary();
            $table->char('award_type_id', 36);
            $table->char('employee_id', 36);
            $table->text('citation')->nullable();
            $table->date('awarded_on');
            $table->string('period_label', 40)->nullable();
            $table->char('awarded_by', 36)->nullable();
            $table->string('source', 10)->default('manual');   // manual | auto
            $table->string('certificate_code', 40)->unique();
            $table->string('status', 10)->default('issued');   // issued | revoked
            $table->text('revoked_reason')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('award_type_id')->references('award_type_id')->on('award_types');
            $table->foreign('employee_id')->references('employee_id')->on('employees');
            $table->foreign('awarded_by')->references('employee_id')->on('employees')->nullOnDelete();
            $table->index(['employee_id', 'status']);
            $table->index(['award_type_id', 'employee_id']);
        });

        $now = now();
        $body = 'In recognition of {years} years of dedicated and faithful service to the hospital and its patients.';
        $rows = [];

        foreach ([5 => '#0ea5e9', 10 => '#16a34a', 15 => '#7c3aed', 20 => '#d97706', 25 => '#b45309', 30 => '#be123c'] as $years => $color) {
            $rows[] = [
                'name' => "{$years}-Year Service Award",
                'category' => 'service',
                'description' => "Length-of-service milestone for {$years} years with the hospital.",
                'criteria' => "Given automatically on the employee's {$years}th work anniversary (from hire date in HR1).",
                'badge_icon' => 'bi-hourglass-split', 'badge_color' => $color,
                'certificate_title' => 'Certificate of Loyalty Service',
                'certificate_body' => str_replace('{years}', (string) $years, $body),
                'auto_rule' => 'service_years', 'auto_value' => $years, 'points_value' => $years * 2,
            ];
        }

        $rows = array_merge($rows, [
            ['name' => 'Compassionate Care Award', 'category' => 'compassion', 'description' => 'Honours a caregiver whose kindness and skill made a difference to a patient or family.', 'criteria' => 'Nominated by patients, families or colleagues; selected quarterly by the recognition committee. Made a special connection with a patient or family, or excelled at educating them.', 'badge_icon' => 'bi-heart-pulse', 'badge_color' => '#db2777', 'certificate_title' => 'Certificate of Compassionate Care', 'certificate_body' => 'For extraordinary, compassionate care that made a lasting difference to patients and families. {citation}', 'auto_rule' => null, 'auto_value' => null, 'points_value' => 30],
            ['name' => 'Clinical Excellence Award', 'category' => 'excellence', 'description' => 'Outstanding clinical practice, judgement and outcomes.', 'criteria' => 'Consistently exceeds clinical standards, evidenced by audit results, peer feedback or a recognised improvement in outcomes.', 'badge_icon' => 'bi-clipboard2-pulse', 'badge_color' => '#0f766e', 'certificate_title' => 'Certificate of Clinical Excellence', 'certificate_body' => 'For exemplary clinical practice and professional excellence. {citation}', 'auto_rule' => null, 'auto_value' => null, 'points_value' => 30],
            ['name' => 'Outstanding Employee of the Quarter', 'category' => 'performance', 'description' => 'Top performer for the quarter, based on performance review and supervisor nomination.', 'criteria' => 'Overall performance rating of "exceeds expectations" in the period and endorsed by the department head.', 'badge_icon' => 'bi-trophy', 'badge_color' => '#ca8a04', 'certificate_title' => 'Certificate of Outstanding Performance', 'certificate_body' => 'For outstanding work performance during {period}. {citation}', 'auto_rule' => null, 'auto_value' => null, 'points_value' => 40],
            ['name' => 'Innovation & Suggestion Award', 'category' => 'innovation', 'description' => 'An idea or improvement that saved time, money or improved care.', 'criteria' => 'Implemented suggestion, innovation or process improvement with measurable benefit to the hospital.', 'badge_icon' => 'bi-lightbulb', 'badge_color' => '#2563eb', 'certificate_title' => 'Certificate of Innovation', 'certificate_body' => 'For an innovative contribution that improved hospital service. {citation}', 'auto_rule' => null, 'auto_value' => null, 'points_value' => 30],
            ['name' => 'Exemplary Conduct Award', 'category' => 'conduct', 'description' => 'Integrity, ethics and professionalism beyond the call of duty.', 'criteria' => 'Demonstrated exemplary ethical behaviour, honesty or courtesy that upheld the hospital\'s values.', 'badge_icon' => 'bi-shield-check', 'badge_color' => '#4f46e5', 'certificate_title' => 'Certificate of Exemplary Conduct', 'certificate_body' => 'For exemplary conduct and integrity in public service. {citation}', 'auto_rule' => null, 'auto_value' => null, 'points_value' => 25],
            ['name' => 'Team Excellence Award', 'category' => 'teamwork', 'description' => 'A unit or team that achieved something together.', 'criteria' => 'Given to each member of a team or unit that delivered an outstanding shared result.', 'badge_icon' => 'bi-people', 'badge_color' => '#0891b2', 'certificate_title' => 'Certificate of Team Excellence', 'certificate_body' => 'As a valued member of a team recognised for outstanding collaboration. {citation}', 'auto_rule' => null, 'auto_value' => null, 'points_value' => 20],
            ['name' => 'Preceptor & Mentor of the Year', 'category' => 'leadership', 'description' => 'Outstanding teaching, precepting and mentoring of new staff and students.', 'criteria' => 'Nominated by orientees, students or the education unit for exceptional guidance and teaching.', 'badge_icon' => 'bi-mortarboard', 'badge_color' => '#9333ea', 'certificate_title' => 'Certificate of Excellence in Mentoring', 'certificate_body' => 'For dedication to teaching and developing colleagues. {citation}', 'auto_rule' => null, 'auto_value' => null, 'points_value' => 30],
            ['name' => 'Patient Safety Champion', 'category' => 'excellence', 'description' => 'Prevented harm or strengthened a safety practice.', 'criteria' => 'Reported or prevented a safety risk, or led an improvement in a patient-safety goal.', 'badge_icon' => 'bi-shield-plus', 'badge_color' => '#dc2626', 'certificate_title' => 'Certificate of Patient Safety Leadership', 'certificate_body' => 'For championing patient safety. {citation}', 'auto_rule' => null, 'auto_value' => null, 'points_value' => 30],
            ['name' => 'Lifelong Learner Award', 'category' => 'learning', 'description' => 'Exceptional commitment to continuing professional development.', 'criteria' => 'Given automatically when an employee reaches 40 qualified CPD hours in a calendar year.', 'badge_icon' => 'bi-book', 'badge_color' => '#059669', 'certificate_title' => 'Certificate of Professional Development', 'certificate_body' => 'For completing {cpd_hours} qualified CPD hours in {period}.', 'auto_rule' => 'cpd_hours_year', 'auto_value' => 40, 'points_value' => 20],
        ]);

        foreach ($rows as $row) {
            DB::table('award_types')->insert(array_merge([
                'award_type_id' => (string) Str::uuid(),
                'signatory_name' => null,
                'signatory_title' => 'Chief Human Resources Officer',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], $row));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_awards');
        Schema::dropIfExists('award_types');
    }
};
