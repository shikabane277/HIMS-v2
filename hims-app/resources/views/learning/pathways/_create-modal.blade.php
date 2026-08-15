{{--
    New-learning-pathway form, as a modal. Replaces the standalone create page;
    the route, the POST and the validation are unchanged.

    Needs $roles from the host controller.

    Target roles are checkboxes rather than a <select multiple>: the old control
    hid "you may pick more than one" behind Ctrl/Cmd-click, and "leave it empty
    for everyone" is far easier to read off an untouched list of tickboxes.

    Pushed to the layout's @stack('modals'), outside <main>, so the backdrop's
    position: fixed resolves against the viewport — see the note at the stack.
--}}
@push('modals')
<div class="hims-modal-backdrop" id="pathwayCreateModal" role="dialog" aria-modal="true" aria-labelledby="pathwayCreateTitle">
    <div class="hims-modal">
        <div class="hims-modal-header">
            <h5 id="pathwayCreateTitle"><i class="bi bi-diagram-2"></i> New Learning Pathway</h5>
            <button type="button" class="hims-modal-close" data-modal-dismiss aria-label="Close">&times;</button>
        </div>

        <form method="POST" action="{{ route('learning.pathways.store') }}">
            @csrf
            <input type="hidden" name="_modal" value="pathwayCreateModal">
            <div class="hims-modal-body">
                @if($errors->any() && old('_modal') === 'pathwayCreateModal')
                    <div class="hims-alert error mb-3"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>
                @endif

                <div class="row g-3">
                    <div class="col-12">
                        <label class="hims-label" for="pc_pathway_name">Pathway Name *</label>
                        <input type="text" name="pathway_name" id="pc_pathway_name" class="hims-input" required
                               value="{{ old('pathway_name') }}" placeholder="e.g. New Nurse Orientation Pathway">
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="pc_is_mandatory">Mandatory?</label>
                        <select name="is_mandatory" id="pc_is_mandatory" class="hims-input hims-select">
                            <option value="0" @selected(! old('is_mandatory'))>No</option>
                            <option value="1" @selected(old('is_mandatory'))>Yes</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="hims-label" for="pc_total_cpd_hours">Total CPD Hours</label>
                        <input type="number" name="total_cpd_hours" id="pc_total_cpd_hours" class="hims-input"
                               value="{{ old('total_cpd_hours') }}" min="0" step="0.5" placeholder="e.g. 24">
                    </div>

                    <div class="col-12">
                        <label class="hims-label">Target Roles</label>
                        <small style="display:block;color:#9ca3af;font-size:11.5px;margin-bottom:8px">
                            Tick the roles this pathway is meant for. Tick nothing to make it available to everyone.
                        </small>
                        @if(($roles ?? collect())->isEmpty())
                            <div class="hims-checklist-empty" style="border:1px solid var(--hims-border);border-radius:var(--hims-radius-sm)">
                                No roles defined yet — this pathway will be available to everyone.
                            </div>
                        @else
                            <div class="hims-checklist" id="pc_target_roles">
                                @foreach($roles as $role)
                                    <label>
                                        <input type="checkbox" name="target_roles[]" value="{{ $role->role_id }}"
                                               @checked(in_array($role->role_id, old('target_roles', []), true))>
                                        <span>{{ $role->role_name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="col-12">
                        <label class="hims-label" for="pc_description">Description</label>
                        <textarea name="description" id="pc_description" class="hims-input" rows="3" placeholder="Learning goals and target audience…">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="hims-modal-footer">
                <button type="button" class="btn-hims btn-hims-outline" data-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-hims btn-hims-primary"><i class="bi bi-check-circle"></i> Create Pathway</button>
            </div>
        </form>
    </div>
</div>
@endpush

@include('partials.modal-js')
@if(request('new') === 'pathway' || ($errors->any() && old('_modal') === 'pathwayCreateModal'))
@push('scripts')
<script>window.himsModal.open('pathwayCreateModal');</script>
@endpush
@endif
