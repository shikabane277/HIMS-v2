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
                            <label class="hims-label">Employment Status</label>
                            <select name="employment_status" class="hims-input hims-select">
                                <option value="active" selected>Active</option>
                                <option value="probationary">Probationary</option>
                                <option value="on_leave">On Leave</option>
                                <option value="resigned">Resigned</option>
                                <option value="terminated">Terminated</option>
                            </select>
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
