@extends('layouts.hims')
@section('title','Give Recognition')
@section('page-title','Social Recognition')
@section('breadcrumb','HIMS / Recognition / Give')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('recognition.index') }}" class="btn-hims btn-hims-ghost"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="hims-card">
            <div class="card-header"><h5><i class="bi bi-star-fill"></i> Give Recognition</h5></div>
            <div class="card-body">
                @if($errors->any())
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('recognition.posts.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="hims-label">Recognizing *</label>
                            <select name="recipient_id" class="hims-input hims-select" required>
                                <option value="">— Select a colleague —</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->employee_id }}" {{ old('recipient_id')==$emp->employee_id?'selected':'' }}>
                                        {{ $emp->first_name }} {{ $emp->last_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Badge (optional)</label>
                            <select name="badge_id" class="hims-input hims-select">
                                <option value="">— No badge —</option>
                                @foreach($badges as $badge)
                                    <option value="{{ $badge->badge_id }}" {{ old('badge_id')==$badge->badge_id?'selected':'' }}>
                                        {{ $badge->badge_icon }} {{ $badge->badge_name }} (+{{ $badge->points_value }} pts)
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="hims-label">Post Type</label>
                            <select name="post_type" class="hims-input hims-select">
                                <option value="peer">Peer-to-Peer</option>
                                <option value="management">Management</option>
                                <option value="milestone">Milestone</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="hims-label">Message *</label>
                            <textarea name="message" class="hims-input" rows="5" required placeholder="Share what this person did that deserves recognition…" style="resize:vertical">{{ old('message') }}</textarea>
                        </div>
                        <div class="col-12 d-flex gap-2 justify-content-end mt-2">
                            <a href="{{ route('recognition.index') }}" class="btn-hims btn-hims-outline">Cancel</a>
                            <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-star-fill"></i> Post Recognition</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
