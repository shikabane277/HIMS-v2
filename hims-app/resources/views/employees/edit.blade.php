@extends('layouts.hims')
@section('title','Edit Employee')
@section('page-title','Edit Employee')
@section('breadcrumb','HIMS / Employees / Edit')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">{{ $employee->first_name }} {{ $employee->last_name }}</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">
            {{ $employee->employee_code }} · joined {{ \Illuminate\Support\Str::substr((string) $employee->hire_date, 0, 10) }}
        </p>
    </div>
    <a href="{{ route('employees.show', $employee->employee_id) }}" class="btn-hims btn-hims-ghost">
        <i class="bi bi-arrow-left"></i> Back to Profile
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="hims-card">
            <div class="card-header">
                <h5><i class="bi bi-person-gear"></i> Employee Record</h5>
            </div>
            <div class="card-body">
                @if($errors->any())
                    <div class="hims-alert error mb-3">
                        <i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="hims-alert error mb-3">
                        <i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('employees.update', $employee->employee_id) }}">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="hims-label">First Name *</label>
                            <input type="text" name="first_name" class="hims-input" required maxlength="100"
                                   value="{{ old('first_name', $employee->first_name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Last Name *</label>
                            <input type="text" name="last_name" class="hims-input" required maxlength="100"
                                   value="{{ old('last_name', $employee->last_name) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="hims-label">Email Address *</label>
                            <input type="email" name="email" class="hims-input" required maxlength="255"
                                   value="{{ old('email', $employee->email) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Phone</label>
                            <input type="text" name="phone" class="hims-input" maxlength="100"
                                   value="{{ old('phone', $employee->phone) }}" placeholder="e.g. +63 917 000 0000">
                        </div>

                        <div class="col-md-6">
                            <label class="hims-label">Department *</label>
                            @php $deptId = old('department_id', $employee->department_id); @endphp
                            <select name="department_id" class="hims-input hims-select" required>
                                <option value="">— Select Department —</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->department_id }}" @selected($deptId === $dept->department_id)>
                                        {{ $dept->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Job Role *</label>
                            @php $roleId = old('role_id', $employee->role_id); @endphp
                            <select name="role_id" class="hims-input hims-select" required>
                                <option value="">— Select Role —</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->role_id }}" @selected($roleId === $role->role_id)>
                                        {{ $role->role_name }}
                                    </option>
                                @endforeach
                            </select>
                            <div style="font-size:11.5px;color:#9ca3af;margin-top:5px">
                                The job role drives which competency requirements this employee is measured against.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="hims-label">Position Title</label>
                            <input type="text" name="position_title" class="hims-input" maxlength="200"
                                   value="{{ old('position_title', $employee->position_title) }}" placeholder="e.g. Head Nurse, ICU">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Reports To</label>
                            @php $supervisorId = old('supervisor_id', $employee->supervisor_id); @endphp
                            <select name="supervisor_id" class="hims-input hims-select">
                                <option value="">— No supervisor —</option>
                                @foreach($supervisors as $supervisor)
                                    <option value="{{ $supervisor->employee_id }}" @selected($supervisorId === $supervisor->employee_id)>
                                        {{ $supervisor->first_name }} {{ $supervisor->last_name }}{{ $supervisor->position_title ? ' — '.$supervisor->position_title : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="hims-label">Hire Date *</label>
                            <input type="date" name="hire_date" class="hims-input" required
                                   value="{{ old('hire_date', \Illuminate\Support\Str::substr((string) $employee->hire_date, 0, 10)) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Employment Status *</label>
                            @php $status = old('employment_status', $employee->employment_status); @endphp
                            <select name="employment_status" class="hims-input hims-select" required>
                                @foreach(['active'=>'Active','probationary'=>'Probationary','on_leave'=>'On Leave','suspended'=>'Suspended','resigned'=>'Resigned','terminated'=>'Terminated'] as $value => $label)
                                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <div style="font-size:11.5px;color:#9ca3af;margin-top:5px">
                                Only active staff are counted in workforce dashboards and gap analysis.
                            </div>
                        </div>

                        <div class="col-12 mt-3 d-flex gap-2 justify-content-end">
                            <a href="{{ route('employees.show', $employee->employee_id) }}" class="btn-hims btn-hims-outline">Cancel</a>
                            <button type="submit" class="btn-hims btn-hims-primary">
                                <i class="bi bi-check-circle"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
