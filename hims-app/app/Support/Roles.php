<?php

namespace App\Support;

/**
 * The HR3 account types — one list that the Users screen, validation, labels,
 * the AI assistant's scope and the permission matrix all read, so they cannot
 * disagree.
 *
 * Hospital-wide        admin, hr_manager
 * Hospital-wide, L&D   training_officer
 * Own team             supervisor
 * Self only            staff, trainee
 *
 * "Session instructor" is not an account type: any account named as the
 * instructor of a training session may take attendance for that session.
 */
class Roles
{
    public const ADMIN = 'admin';

    public const HR_MANAGER = 'hr_manager';

    public const TRAINING_OFFICER = 'training_officer';

    public const SUPERVISOR = 'supervisor';

    public const STAFF = 'staff';

    public const TRAINEE = 'trainee';

    /** Order = how they are listed on screen (most to least access). */
    public const ALL = [self::ADMIN, self::HR_MANAGER, self::TRAINING_OFFICER, self::SUPERVISOR, self::STAFF, self::TRAINEE];

    /** Roles whose access ends on a set date. */
    public const TIME_LIMITED = [self::TRAINEE];

    public static function label(?string $role): string
    {
        return self::definitions()[$role]['label'] ?? 'No HIMS account';
    }

    public static function icon(?string $role): string
    {
        return self::definitions()[$role]['icon'] ?? 'bi-person';
    }

    /** @return array<string, string> role => label */
    public static function options(): array
    {
        return collect(self::definitions())->map(fn ($d) => $d['label'])->all();
    }

    /**
     * @return array<string, array{label: string, icon: string, who: string, scope: string, can: list<string>, cannot: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            self::ADMIN => [
                'label' => 'System Administrator',
                'icon' => 'bi-shield-lock',
                'who' => 'HRIS / IT staff who run the system.',
                'scope' => 'Whole hospital',
                'can' => ['Everything HR Managers can do', 'Create and manage login accounts and roles', 'System settings, integrations (HR1/HR2), record-access password', 'Audit history'],
                'cannot' => ['Deactivate the last active administrator'],
            ],
            self::HR_MANAGER => [
                'label' => 'HR Manager',
                'icon' => 'bi-person-badge',
                'who' => 'HR department heads and HR officers.',
                'scope' => 'Whole hospital',
                'can' => ['Review cycles, KPIs and all performance reviews', 'Competency framework and credential verification', 'Learning, training and CPD (everything a Training Officer can do)', 'Succession planning and promotions', 'Awards, certificates and badges', 'Employee records, departments, audit history'],
                'cannot' => ['Manage login accounts or system settings'],
            ],
            self::TRAINING_OFFICER => [
                'label' => 'Training Officer',
                'icon' => 'bi-mortarboard',
                'who' => 'Training & Development / Nursing Education unit staff who run the hospital\'s learning programme.',
                'scope' => 'Whole hospital — learning and training only',
                'can' => ['Create and edit courses, learning pathways and modules', 'Schedule training sessions, manage venues, take attendance', 'Assign mandatory training and set renewal rules', 'Verify external CPD certificates and record course completions', 'Accreditation / training compliance reports and account coverage', 'Department-level training-needs (gap) analysis'],
                'cannot' => ['See performance reviews or scores', 'See succession plans', 'Edit employee records, issue awards or manage accounts'],
            ],
            self::SUPERVISOR => [
                'label' => 'Supervisor / Department Head',
                'icon' => 'bi-people',
                'who' => 'Department heads, charge nurses, unit managers, preceptors with direct reports.',
                'scope' => 'Their own direct reports',
                'can' => ['Write performance reviews for their team', 'Assess competencies and run gap analysis for their team', 'Schedule team training, take attendance, record completions', 'View their team\'s compliance and nominate successors'],
                'cannot' => ['See other departments', 'Change hospital-wide frameworks, rules or venues'],
            ],
            self::STAFF => [
                'label' => 'Staff',
                'icon' => 'bi-person',
                'who' => 'Every regular employee — nurses, doctors, allied health, admin and support staff.',
                'scope' => 'Their own records',
                'can' => ['See and respond to their own performance reviews', 'Own competencies, credentials, CPD, certificates and badges', 'Enrol in courses, register for training, give recognition'],
                'cannot' => ['See anyone else\'s records'],
            ],
            self::TRAINEE => [
                'label' => 'Trainee',
                'icon' => 'bi-backpack',
                'who' => 'Students on clinical placement, interns, OJT, residents on rotation and new hires in orientation.',
                'scope' => 'Their own records, until the access end date',
                'can' => ['Learning, training sessions and CPD', 'Own competencies, skills sign-off status and certificates', 'Recognition and badges'],
                'cannot' => ['Performance reviews (they are evaluated through their preceptor/supervisor and competency checks)', 'Sign in after their access end date'],
            ],
        ];
    }
}
