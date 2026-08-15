@push('modals')
<div class="hims-modal-backdrop" id="recognitionBadgeModal" role="dialog" aria-modal="true" aria-labelledby="recognitionBadgeTitle">
    <div class="hims-modal" style="max-width:640px">
        <div class="hims-modal-header">
            <h5 id="recognitionBadgeTitle"><i class="bi bi-patch-plus"></i> New Value Badge</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="{{ route('recognition.badges.store') }}">
            @csrf
            <input type="hidden" name="_modal" value="recognitionBadgeModal">
            <div class="hims-modal-body">
                @if($errors->any() && old('_modal') === 'recognitionBadgeModal')
                <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="hims-label" for="recognition_badge_name">Badge Name *</label>
                        <input type="text" name="badge_name" id="recognition_badge_name" class="hims-input" required maxlength="100" value="{{ old('_modal') === 'recognitionBadgeModal' ? old('badge_name') : '' }}">
                    </div>
                    <div class="col-md-5">
                        <label class="hims-label" for="recognition_value">Hospital Value</label>
                        <input type="text" name="hospital_value" id="recognition_value" class="hims-input" maxlength="100" value="{{ old('_modal') === 'recognitionBadgeModal' ? old('hospital_value') : '' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="hims-label" for="recognition_badge_icon">Bootstrap Icon Class</label>
                        <input type="text" name="badge_icon" id="recognition_badge_icon" class="hims-input" maxlength="50" value="{{ old('_modal') === 'recognitionBadgeModal' ? old('badge_icon', 'bi bi-award') : 'bi bi-award' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="hims-label" for="recognition_badge_color">Color</label>
                        <input type="color" name="badge_color" id="recognition_badge_color" class="hims-input" value="{{ old('_modal') === 'recognitionBadgeModal' ? old('badge_color', '#047857') : '#047857' }}" style="height:42px;padding:4px">
                    </div>
                    <div class="col-md-3">
                        <label class="hims-label" for="recognition_points">Points *</label>
                        <input type="number" name="points_value" id="recognition_points" class="hims-input" required min="1" max="100" value="{{ old('_modal') === 'recognitionBadgeModal' ? old('points_value', 5) : 5 }}">
                    </div>
                    <div class="col-12">
                        <label class="hims-label" for="recognition_badge_description">Description</label>
                        <textarea name="description" id="recognition_badge_description" class="hims-input" rows="3" maxlength="1000">{{ old('_modal') === 'recognitionBadgeModal' ? old('description') : '' }}</textarea>
                    </div>
                </div>
            </div>
            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Create Badge</button>
            </div>
        </form>
    </div>
</div>
@endpush

@include('partials.modal-js')

@if($errors->any() && old('_modal') === 'recognitionBadgeModal')
@push('scripts')<script>window.himsModal.open('recognitionBadgeModal');</script>@endpush
@endif
