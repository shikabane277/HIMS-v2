@extends('layouts.hims')
@section('title','Add Employee')
@section('page-title','Add Employee')
@section('breadcrumb','HIMS / Employees / Add')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('employees.index') }}" class="btn-hims btn-hims-ghost">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="hims-card">
            <div class="card-header">
                <h5><i class="bi bi-person-plus-fill"></i> New Employee Record</h5>
            </div>
            <div class="card-body">
                @if($errors->any())
                    <div class="hims-alert error mb-3">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('employees.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="hims-label">First Name *</label>
                            <input type="text" name="first_name" class="hims-input" value="{{ old('first_name') }}" required placeholder="e.g. Maria">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Last Name *</label>
                            <input type="text" name="last_name" class="hims-input" value="{{ old('last_name') }}" required placeholder="e.g. Santos">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Email Address *</label>
                            <input type="email" name="email" class="hims-input" value="{{ old('email') }}" required placeholder="e.g. m.santos@hospital.ph">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Position Title</label>
                            <input type="text" name="position_title" class="hims-input" value="{{ old('position_title') }}" placeholder="e.g. Head Nurse, ICU">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Department *</label>
                            <select name="department_id" class="hims-input hims-select" required>
                                <option value="">— Select Department —</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->department_id }}" {{ old('department_id') == $dept->department_id ? 'selected' : '' }}>
                                        {{ $dept->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Role *</label>
                            <select name="role_id" class="hims-input hims-select" required>
                                <option value="">— Select Role —</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->role_id }}" {{ old('role_id') == $role->role_id ? 'selected' : '' }}>
                                        {{ $role->role_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Hire Date *</label>
                            <input type="date" name="hire_date" class="hims-input" value="{{ old('hire_date') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Reports To</label>
                            <select name="supervisor_id" class="hims-input hims-select">
                                <option value="">&mdash; No supervisor &mdash;</option>
                                @foreach($supervisors as $supervisor)
                                    <option value="{{ $supervisor->employee_id }}" @selected(old('supervisor_id') === $supervisor->employee_id)>
                                        {{ $supervisor->first_name }} {{ $supervisor->last_name }} &mdash;
                                        {{ $supervisor->position_title ?: 'No position title' }} &mdash;
                                        {{ $supervisor->department_name }} &mdash; {{ $supervisor->access_label }}
                                    </option>
                                @endforeach
                            </select>
                            <div id="managerWarning" class="hims-alert warning mt-2" style="display:none;padding:9px 12px;font-size:12px">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                No manager is assigned. Review authority will need an HR/Admin exception until a reporting line is set.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Employment Status</label>
                            @php $status = old('employment_status', 'active'); @endphp
                            <select name="employment_status" class="hims-input hims-select" required>
                                @foreach(['active'=>'Active','probationary'=>'Probationary','on_leave'=>'On Leave','suspended'=>'Suspended','resigned'=>'Resigned','terminated'=>'Terminated'] as $value => $label)
                                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="hims-label d-flex align-items-center gap-2">
                                <input type="checkbox" name="is_people_manager" value="1" @checked(old('is_people_manager'))>
                                People Manager
                            </label>
                            <div style="font-size:11.5px;color:#6b7280;margin-top:2px">
                                This employee can have other employees report to them. This setting does not grant HIMS access; the account role remains a separate decision.
                            </div>
                        </div>
                        <div class="col-12 mt-3 d-flex gap-2 justify-content-end">
                            <a href="{{ route('employees.index') }}" class="btn-hims btn-hims-outline">Cancel</a>
                            <button type="submit" class="btn-hims btn-hims-primary">
                                <i class="bi bi-check-circle"></i> Save Employee
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (() => {
        const select = document.querySelector('select[name="supervisor_id"]');
        const warning = document.getElementById('managerWarning');
        if (!select || !warning) return;
        const sync = () => { warning.style.display = select.value ? 'none' : 'flex'; };
        select.addEventListener('change', sync);
        sync();
    })();
</script>
@endpush
