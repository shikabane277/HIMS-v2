<?php

namespace App\Http\Controllers;

use App\Services\AwardService;
use App\Support\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Recognition → Awards: HR manages the award catalogue and issues awards;
 * everyone sees their own awards and badges; anyone with the link can
 * verify a certificate.
 */
class AwardController extends Controller
{
    public function __construct(private AwardService $awards) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $canManage = $user->can('manage-recognition');
        $myEmployeeId = $this->currentEmployeeId();

        $types = DB::table('award_types as at')
            ->when(! $canManage, fn ($q) => $q->where('at.is_active', true))
            ->select('at.*')
            ->selectSub(fn ($q) => $q->from('employee_awards')->whereColumn('employee_awards.award_type_id', 'at.award_type_id')->where('status', 'issued')->selectRaw('COUNT(*)'), 'issued_count')
            ->orderByRaw("CASE WHEN at.category = 'service' THEN 1 ELSE 0 END")
            ->orderBy('at.category')->orderBy('at.auto_value')->orderBy('at.name')
            ->get();

        $recent = $this->scopeToVisibleEmployees(
            DB::table('employee_awards as ea')
                ->join('award_types as at', 'ea.award_type_id', '=', 'at.award_type_id')
                ->join('employees as e', 'ea.employee_id', '=', 'e.employee_id')
                ->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')
                ->select('ea.*', 'at.name as award_name', 'at.badge_icon', 'at.badge_color', 'at.category',
                    DB::raw("CONCAT(e.first_name,' ',e.last_name) as employee_name"), 'd.name as department_name')
        )
            ->when($request->query('category'), fn ($q, $c) => $q->where('at.category', $c))
            ->when($request->query('status') !== 'revoked', fn ($q) => $q->where('ea.status', 'issued'), fn ($q) => $q->where('ea.status', 'revoked'))
            ->orderByDesc('ea.awarded_on')->orderByDesc('ea.created_at')
            ->paginate(20)->withQueryString();

        $mine = $myEmployeeId ? AwardService::awardsFor($myEmployeeId) : collect();

        return view('recognition.awards.index', [
            'types' => $types,
            'recent' => $recent,
            'mine' => $mine,
            'canManage' => $canManage,
            'categories' => AwardService::CATEGORIES,
            'autoRules' => AwardService::AUTO_RULES,
            'employees' => $canManage
                ? DB::table('employees as e')->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')
                    ->whereIn('e.employment_status', ['active', 'probationary', 'on_leave'])
                    ->orderBy('e.last_name')->get(['e.employee_id', 'e.first_name', 'e.last_name', 'e.employee_code', 'd.name as department_name'])
                : collect(),
            'stats' => [
                'issued' => DB::table('employee_awards')->where('status', 'issued')->count(),
                'this_year' => DB::table('employee_awards')->where('status', 'issued')->whereYear('awarded_on', now()->year)->count(),
                'types' => DB::table('award_types')->where('is_active', true)->count(),
                'auto' => DB::table('employee_awards')->where('status', 'issued')->where('source', 'auto')->count(),
            ],
        ]);
    }

    public function storeType(Request $request)
    {
        $data = $this->validateType($request);
        $id = (string) Str::uuid();

        DB::table('award_types')->insert(array_merge($data, [
            'award_type_id' => $id,
            'is_active' => true,
            'created_by' => $this->currentEmployeeId(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        AuditTrail::record('create_award_type', 'award_types', $id, afterState: $data);

        return redirect()->route('recognition.awards.index')->with('success', "Award \"{$data['name']}\" created.");
    }

    public function updateType(Request $request, string $id)
    {
        $type = DB::table('award_types')->where('award_type_id', $id)->first();
        abort_if(! $type, 404);
        $data = $this->validateType($request, $id);

        DB::table('award_types')->where('award_type_id', $id)->update(array_merge($data, ['updated_at' => now()]));
        AuditTrail::record('update_award_type', 'award_types', $id, beforeState: (array) $type, afterState: $data);

        return redirect()->route('recognition.awards.index')->with('success', "Award \"{$data['name']}\" updated.");
    }

    public function toggleType(string $id)
    {
        $type = DB::table('award_types')->where('award_type_id', $id)->first();
        abort_if(! $type, 404);

        DB::table('award_types')->where('award_type_id', $id)->update(['is_active' => ! $type->is_active, 'updated_at' => now()]);
        AuditTrail::record('toggle_award_type', 'award_types', $id, afterState: ['is_active' => ! $type->is_active]);

        return back()->with('success', "\"{$type->name}\" ".($type->is_active ? 'retired.' : 'reactivated.'));
    }

    public function issue(Request $request)
    {
        $data = $request->validate([
            'award_type_id' => ['required', 'string', Rule::exists('award_types', 'award_type_id')->where('is_active', true)],
            'employee_ids' => ['required', 'array', 'min:1', 'max:200'],
            'employee_ids.*' => ['string', 'exists:employees,employee_id'],
            'citation' => ['nullable', 'string', 'max:1000'],
            'awarded_on' => ['required', 'date', 'before_or_equal:today'],
            'period_label' => ['nullable', 'string', 'max:40'],
        ]);

        $issuedBy = $this->currentEmployeeId();
        $count = 0;
        $lastCode = null;

        DB::transaction(function () use ($data, $issuedBy, &$count, &$lastCode) {
            foreach (array_unique($data['employee_ids']) as $employeeId) {
                $id = $this->awards->issue($data['award_type_id'], $employeeId, $data['citation'] ?? null,
                    $data['awarded_on'], $data['period_label'] ?? null, 'manual', $issuedBy);
                $lastCode = DB::table('employee_awards')->where('award_id', $id)->value('certificate_code');
                $count++;
            }
        });

        $name = DB::table('award_types')->where('award_type_id', $data['award_type_id'])->value('name');

        return redirect()->route('recognition.awards.index')->with('success',
            $count === 1
                ? "{$name} issued. Certificate {$lastCode} has been generated and the employee notified."
                : "{$name} issued to {$count} employees. Certificates generated and everyone notified.");
    }

    public function revoke(Request $request, string $awardId)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $this->awards->revoke($awardId, $data['reason']);

        return back()->with('success', 'Award revoked. Its certificate now shows as revoked when verified.');
    }

    public function runAuto()
    {
        $issued = $this->awards->runAutomaticAwards();
        $total = array_sum($issued);

        return back()->with('success', $total
            ? "Automatic awards issued: {$total} (".collect($issued)->map(fn ($c, $n) => "{$n}: {$c}")->implode(', ').').'
            : 'No automatic awards were due.');
    }

    /** Public, shareable certificate — the verification page. */
    public function certificate(string $code)
    {
        $award = $this->awards->certificate($code);
        abort_if(! $award, 404, 'Certificate not found.');

        return view('recognition.awards.certificate', [
            'award' => $award,
            'verifyUrl' => route('awards.verify', $award->certificate_code),
        ]);
    }

    /** Downloadable badge image (SVG). */
    public function badge(string $code)
    {
        $award = $this->awards->certificate($code);
        abort_if(! $award, 404);

        return response()
            ->view('recognition.awards.badge-svg', ['award' => $award], 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', 'inline; filename="'.$award->certificate_code.'.svg"');
    }

    private function validateType(Request $request, ?string $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('award_types', 'name')->ignore($ignoreId, 'award_type_id')],
            'category' => ['required', Rule::in(array_keys(AwardService::CATEGORIES))],
            'description' => ['nullable', 'string', 'max:1000'],
            'criteria' => ['nullable', 'string', 'max:2000'],
            'badge_icon' => ['nullable', 'string', 'max:50', 'regex:/^bi-[a-z0-9-]+$/'],
            'badge_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'certificate_title' => ['required', 'string', 'max:120'],
            'certificate_body' => ['nullable', 'string', 'max:2000'],
            'signatory_name' => ['nullable', 'string', 'max:120'],
            'signatory_title' => ['nullable', 'string', 'max:120'],
            'auto_rule' => ['nullable', Rule::in(array_keys(AwardService::AUTO_RULES))],
            'auto_value' => ['nullable', 'required_with:auto_rule', 'integer', 'min:1', 'max:60'],
            'points_value' => ['required', 'integer', 'min:0', 'max:500'],
        ]);

        $data['badge_icon'] = $data['badge_icon'] ?? 'bi-award';
        $data['badge_color'] = $data['badge_color'] ?? '#16a34a';
        $data['auto_rule'] = $data['auto_rule'] ?? null;
        $data['auto_value'] = $data['auto_rule'] ? (int) $data['auto_value'] : null;

        return $data;
    }
}
