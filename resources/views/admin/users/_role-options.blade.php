<div class="row g-3">
    @foreach ($roles as $role)
        @php($optionId = $prefix.'-role-'.$role->id)
        <div class="col-md-6">
            <label for="{{ $optionId }}" class="d-flex align-items-center gap-3 border rounded p-4 h-100 cursor-pointer">
                <span class="form-check form-check-custom form-check-solid">
                    <input id="{{ $optionId }}" class="form-check-input" type="checkbox"
                        name="role_names[]" value="{{ $role->name }}"
                        @checked(in_array($role->name, $selectedRoles, true))>
                </span>
                <span>
                    <span class="d-block fw-bold text-gray-900">{{ $role->name }}</span>
                    <span class="text-muted fs-8">{{ $role->permissions->count() }} permisos incluidos</span>
                </span>
            </label>
        </div>
    @endforeach
</div>
