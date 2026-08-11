<div class="row g-3">
    @foreach ($permissions as $permission)
        @php($optionId = $prefix.'-permission-'.$permission->id)
        <div class="col-md-6">
            <label for="{{ $optionId }}" class="d-flex align-items-start gap-3 border rounded p-4 h-100 cursor-pointer">
                <span class="form-check form-check-custom form-check-solid mt-1">
                    <input id="{{ $optionId }}" class="form-check-input" type="checkbox"
                        name="permission_names[]" value="{{ $permission->name }}"
                        @checked(in_array($permission->name, $selectedPermissions, true))>
                </span>
                <span class="min-w-0">
                    <span class="d-block fw-bold text-gray-900">{{ $permissionLabels[$permission->name] ?? str($permission->name)->replace(['.', '_'], ' ')->headline() }}</span>
                    <code class="fs-8 text-muted text-break">{{ $permission->name }}</code>
                </span>
            </label>
        </div>
    @endforeach
</div>
