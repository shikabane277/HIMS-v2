<?php

namespace Tests\Unit;

use App\Services\Ai\AbstractAiProvider;
use App\Services\Ai\HimsKnowledge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Guards the grounding block in the system prompt.
 *
 * This is prompt text, so nothing breaks when it drifts out of step with the
 * app — the assistant just quietly starts inventing UI again. The original
 * regression: asked how to enrol an employee in a course, the model described an
 * employee search, an "enrolment type" dropdown and an enrolment-date field,
 * none of which existed.
 *
 * The guide has since had to be corrected in the opposite direction, which is
 * the more interesting failure and the reason these pins are worth keeping.
 * Course self-enrolment was removed outright: the paragraph that once said
 * "nobody can enrol anyone but themselves" became a flat denial of a feature
 * that now exists, stated confidently, with the user's only recourse being to
 * stop looking. So what is pinned here is the *current* boundary — no
 * self-enrolment at all, sessions still self-service — not merely the presence
 * of a negatives section.
 */
class HimsKnowledgeTest extends TestCase
{
    /** The claims that must survive any edit to the guide. */
    public static function requiredFacts(): array
    {
        return [
            // Course enrolment, after self-enrolment was removed. Each of these
            // replaced a pin asserting the exact opposite, so a future edit that
            // reinstates the old wording fails here rather than in production.
            'no self-service enrolment' => ['no self-service course enrolment'],
            'no enrol button anywhere' => ['no "enrol" or "join" button'],
            'nor a request workflow' => ['no request or approval workflow'],
            'somebody with authority does it' => ['created by somebody with authority over the employee'],
            'and where they do it' => ['"assign training"'],
            'a supervisor is held to their reports' => ['own direct reports'],
            'completion is never self-certified' => ['nobody certifies their own completion'],
            'sessions are the exception' => ['sessions are the one thing you can still put yourself on'],
            'no automatic progress' => ['no automatic progress'],
            'no quizzes' => ['no quizzes'],
            // Notifications used to be listed as absent. They exist now, so what
            // must be pinned is the *boundary*. Two of the three kinds are raised
            // by a scheduled daily scan and escalate beyond the employee; the
            // third, CPD verification, is raised the moment HR approves and goes
            // to the owner alone. The guide previously said no alert ever follows
            // a user action, which stopped being true when verifyCpd() began
            // notifying — and a wrong negative here is the exact shape that sends
            // someone hunting the UI for a control that was never built.
            'no push or SMS' => ['no push notifications and no sms'],
            'the bell does exist' => ['in-app notifications do exist'],
            'alerts are scan-driven' => ['raised by a scheduled daily scan'],
            'alerts escalate' => ['supervisor and department head'],
            'cpd verification alerts immediately' => ['verifies one of your cpd entries'],
            'that one does not escalate' => ['goes to you only'],
            'no uploads' => ['no file or document upload'],
            // Labels quoted exactly as they render, so directions can be followed
            // literally. The catalogue heading is spelled "Catalogue"; the CPD
            // button reads "Record CPD", not "Log CPD Activity".
            'the catalogue by its label' => ['course catalogue'],
            'the register flow' => ['"register"'],
            'cpd logging' => ['"record cpd"'],
            'external cpd needs approval' => ['wait for hr approval'],
            'check-in is not self-service' => ['no qr code or self-check-in'],
            'feedback needs attendance' => ['after you have been marked'],
            // Learning absorbed Compliance, so the guide has to name the tabs a
            // direction would send somebody to, and say the old item is gone.
            'the oversight tabs are named' => ['"required training"'],
            'compliance is not its own item' => ['no separate "compliance" item'],
            'do not invent' => ['never invent a page, button, field'],
        ];
    }

    #[DataProvider('requiredFacts')]
    public function test_the_guide_states(string $claim): void
    {
        $this->assertStringContainsString(
            $claim,
            $this->flatten(HimsKnowledge::appGuide()),
            "The grounding block no longer tells the model: {$claim}"
        );
    }

    /**
     * Lowercased and collapsed onto one line.
     *
     * The guide is hard-wrapped for readability, so a phrase like "no employee
     * picker" is split across two lines in the source. Asserting on the raw
     * string would fail on wrapping alone and make the test a nuisance rather
     * than a guard.
     */
    private function flatten(string $text): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower($text));
    }

    /** Every module in the sidebar, so directions cannot miss one. */
    public function test_the_guide_covers_every_module(): void
    {
        $guide = $this->flatten(HimsKnowledge::appGuide());

        foreach ([
            'dashboard', 'my development', 'performance', 'competency',
            'gap analysis', 'learning', 'training', 'succession',
            'employees', 'departments', 'users & access',
        ] as $module) {
            $this->assertStringContainsString($module, $guide, "Sidebar item missing from the guide: {$module}");
        }
    }

    /**
     * The negatives are what suppress invention — listing features alone does
     * not, since the model fills whatever gap is left. If this section thins
     * out, the guide has lost the part that was doing the work.
     */
    public function test_the_guide_keeps_its_list_of_absent_features(): void
    {
        $guide = HimsKnowledge::appGuide();

        $this->assertStringContainsString('WHAT HIMS DOES NOT HAVE', $guide);
        $this->assertGreaterThanOrEqual(
            6,
            preg_match_all('/^\s*- No /mi', $guide),
            'The "does not have" list has shrunk; invention is likely to return.'
        );
    }

    /** A guide that never reaches the prompt is the same as not having one. */
    public function test_the_guide_is_carried_in_the_system_prompt(): void
    {
        $prompt = $this->exposeSystemContext(null);

        $this->assertStringContainsString('WHAT HIMS DOES NOT HAVE', $prompt);
        $this->assertStringContainsString('Hospital Information Management System', $prompt);
    }

    /** Grounding and the RBAC scope must coexist, not replace one another. */
    public function test_the_role_scope_is_appended_without_dropping_the_guide(): void
    {
        $prompt = $this->exposeSystemContext('ACCESS SCOPE: test fragment.');

        $this->assertStringContainsString('WHAT HIMS DOES NOT HAVE', $prompt);
        $this->assertStringContainsString('ACCESS SCOPE: test fragment.', $prompt);
    }

    /** Reads the protected systemContext() off a concrete driver. */
    private function exposeSystemContext(?string $scope): string
    {
        $driver = new class extends AbstractAiProvider
        {
            protected function label(): string
            {
                return 'Test';
            }

            public function ask(string $prompt, array $history = [], ?string $scope = null): string
            {
                return '';
            }

            public function exposeSystemContext(?string $scope): string
            {
                return $this->systemContext($scope);
            }
        };

        return $driver->exposeSystemContext($scope);
    }
}
