@extends('layouts.hims')
@section('title','Training Venues')
@section('page-title','Training Management')
@section('breadcrumb','HIMS / Training / Venues')
@section('content')
@include('partials.training-tabs')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin:0">Training Venues</h2>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0">Manage rooms and facilities used for training sessions.</p>
    </div>
    @can('manage-venues')
    <button type="button" class="btn-hims btn-hims-primary" data-modal-open="venueCreateModal"><i class="bi bi-plus-circle"></i> Add Venue</button>
    @endcan
</div>
<div class="row g-3">
    @forelse($venues as $venue)
    <div class="col-md-4">
        <div class="hims-card" id="venue-{{ $venue->venue_id }}" style="scroll-margin-top:84px">
            <div class="card-header">
                <h5 style="font-size:14px"><i class="bi bi-building"></i> {{ $venue->venue_name }}</h5>
                <span class="hims-badge {{ $venue->is_active ? 'green' : 'gray' }}">{{ $venue->is_active ? 'Active' : 'Offline' }}</span>
            </div>
            <div class="card-body">
                <table class="hims-kv" style="width:100%;font-size:13px">
                    <tr style="border-bottom:1px solid var(--hims-border)">
                        <td style="padding:7px 0;color:#6b7280">Building</td>
                        <td style="padding:7px 0;font-weight:600">{{ $venue->building ?? '—' }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--hims-border)">
                        <td style="padding:7px 0;color:#6b7280">Floor</td>
                        <td style="padding:7px 0">{{ $venue->floor ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:7px 0;color:#6b7280">Capacity</td>
                        <td style="padding:7px 0;font-weight:700;color:var(--hims-primary)">{{ $venue->capacity }} pax</td>
                    </tr>
                </table>
                @can('manage-venues')
                <div class="mt-3 d-flex gap-2 justify-content-end">
                    <button type="button" class="btn-hims btn-hims-outline btn-sm"
                            data-modal-open="venueEditModal"
                            data-venue-edit
                            data-action="{{ route('training.venues.update', $venue->venue_id) }}"
                            data-name="{{ $venue->venue_name }}"
                            data-building="{{ $venue->building }}"
                            data-floor="{{ $venue->floor }}"
                            data-capacity="{{ $venue->capacity }}"
                            data-active="{{ $venue->is_active ? '1' : '0' }}">
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                    <form method="POST" action="{{ route('training.venues.destroy', $venue->venue_id) }}"
                          onsubmit="return confirm('Delete venue {{ addslashes($venue->venue_name) }}?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-hims btn-sm" style="background:#fee2e2;color:#dc2626;border:none;border-radius:8px;padding:6px 10px;cursor:pointer">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
                @endcan
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="hims-card">
            <div class="card-body" style="text-align:center;padding:60px;color:#9ca3af">
                <div style="font-size:48px;margin-bottom:12px">🏛️</div>
                <div style="font-size:16px;font-weight:600;color:var(--hims-text-dark);margin-bottom:6px">No venues yet</div>
                @can('manage-venues')
                <button type="button" class="btn-hims btn-hims-primary mt-2" data-modal-open="venueCreateModal">Add First Venue</button>
                @endcan
            </div>
        </div>
    </div>
    @endforelse
</div>

@can('manage-venues')
    @include('training.venues._create-modal')
    @include('training.venues._edit-modal')
@endcan
@endsection
