@php($isEdit = $company->exists)

<form method="POST" action="{{ $isEdit ? route('admin.companies.update', $company) : route('admin.companies.store') }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="card card-flush">
        <div class="card-header"><div class="card-title"><h3 class="fw-bold mb-0">Datos de la empresa</h3></div></div>
        <div class="card-body">
            <div class="mb-7">
                <label class="form-label required">Nombre</label>
                <input type="text" name="name" value="{{ old('name', $company->name) }}" class="form-control form-control-solid @error('name') is-invalid @enderror" maxlength="255" placeholder="Ej. Grupo Editorial del Sureste" required autofocus>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-7">
                <label class="form-label">Descripción</label>
                <textarea name="description" rows="4" class="form-control form-control-solid @error('description') is-invalid @enderror" maxlength="2000" placeholder="Notas internas para identificar la marca, cliente o unidad editorial.">{{ old('description', $company->description) }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <label class="form-check form-switch form-check-custom form-check-solid">
                <input type="hidden" name="active" value="0">
                <input class="form-check-input" type="checkbox" name="active" value="1" @checked((bool) old('active', $company->active))>
                <span class="form-check-label">
                    <span class="fw-bold text-gray-800 d-block">Empresa activa</span>
                    <span class="text-muted fs-8">Sus destinos estarán disponibles al configurar publicaciones.</span>
                </span>
            </label>
        </div>
        <div class="card-footer d-flex justify-content-end gap-3">
            <a href="{{ route('admin.companies.index') }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="ki-outline ki-check fs-2"></i>{{ $isEdit ? 'Guardar cambios' : 'Crear empresa' }}</button>
        </div>
    </div>
</form>
