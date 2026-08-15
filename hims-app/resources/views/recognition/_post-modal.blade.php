@push('modals')
<div class="hims-modal-backdrop" id="recognitionPostModal" role="dialog" aria-modal="true" aria-labelledby="recognitionPostTitle">
    <div class="hims-modal" style="max-width:680px">
        <div class="hims-modal-header">
            <h5 id="recognitionPostTitle"><i class="bi bi-stars"></i> Give Recognition</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="{{ route('recognition.posts.store') }}">
            @csrf
            <input type="hidden" name="_modal" value="recognitionPostModal">
            <div class="hims-modal-body">
                @if($errors->any() && old('_modal') === 'recognitionPostModal')
                <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif
                <div class="row g-3">
                    <div class="col-12">
                        <label class="hims-label" for="recognition_recipient">Colleague *</label>
                        <select name="recipient_id" id="recognition_recipient" class="hims-input hims-select" required>
                            <option value="">Select a colleague</option>
                            @foreach($employees as $employee)
                            <option value="{{ $employee->employee_id }}" @selected(old('_modal') === 'recognitionPostModal' && old('recipient_id') === $employee->employee_id)>
                                {{ $employee->first_name }} {{ $employee->last_name }}{{ $employee->position_title ? ' - '.$employee->position_title : '' }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="hims-label" for="recognition_badge">Hospital Value Badge</label>
                        <select name="badge_id" id="recognition_badge" class="hims-input hims-select">
                            <option value="">No badge</option>
                            @foreach($badges as $badge)
                            <option value="{{ $badge->badge_id }}" @selected(old('_modal') === 'recognitionPostModal' && old('badge_id') === $badge->badge_id)>
                                {{ $badge->badge_name }} - {{ $badge->points_value }} pts
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="hims-label" for="recognition_message">Message *</label>
                        <textarea name="message" id="recognition_message" class="hims-input" rows="5" required maxlength="1000" placeholder="Describe the contribution you are recognizing">{{ old('_modal') === 'recognitionPostModal' ? old('message') : '' }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="hims-label">Visibility</label>
                        <div class="recognition-visibility" role="radiogroup" aria-label="Recognition visibility">
                            <label class="btn-hims btn-hims-outline" style="cursor:pointer">
                                <input type="radio" name="is_public" value="1" class="visually-hidden"
                                       @checked(old('_modal') !== 'recognitionPostModal' || old('is_public', '1') === '1')>
                                <i class="bi bi-globe2"></i> Public
                            </label>
                            <label class="btn-hims btn-hims-outline" style="cursor:pointer">
                                <input type="radio" name="is_public" value="0" class="visually-hidden"
                                       @checked(old('_modal') === 'recognitionPostModal' && old('is_public') === '0')>
                                <i class="bi bi-lock"></i> Private
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-send"></i> Post Recognition</button>
            </div>
        </form>
    </div>
</div>
@endpush

@include('partials.modal-js')

@if($errors->any() && old('_modal') === 'recognitionPostModal')
@push('scripts')<script>window.himsModal.open('recognitionPostModal');</script>@endpush
@endif
