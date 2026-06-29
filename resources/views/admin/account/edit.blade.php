@extends('layouts.admin')

@section('title', 'Mi cuenta | '.config('app.name'))

@section('toolbar')
    <div>
        <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Mi cuenta</h1>
        <div class="text-muted fw-semibold fs-7 pt-1">Perfil y seguridad</div>
    </div>
@endsection

@section('content')
    <div class="row g-7">
        <div class="col-xl-7">
            <form method="POST" action="{{ route('admin.account.update') }}" enctype="multipart/form-data" class="card card-flush">
                @csrf @method('PATCH')
                <div class="card-header"><div class="card-title"><h3 class="fw-bold mb-0">Información del perfil</h3></div></div>
                <div class="card-body pt-2">
                    @if ($errors->hasAny(['name', 'email', 'profile_photo']))
                        <div class="alert alert-danger">Revisa los campos marcados.</div>
                    @endif
                    <div class="d-flex align-items-center gap-5 mb-8">
                        <div class="symbol symbol-75px">
                            @if (auth()->user()->profile_photo_path || auth()->user()->google_avatar_url)
                                <img src="{{ auth()->user()->avatarUrl() }}" alt="{{ auth()->user()->name }}" class="rounded-circle" style="object-fit:cover">
                            @else
                                <div class="symbol-label bg-light-primary text-primary fw-bold fs-2">{{ auth()->user()->initials() }}</div>
                            @endif
                        </div>
                        <div class="flex-grow-1"><label class="form-label fw-semibold">Foto de perfil</label><input type="file" name="profile_photo" class="form-control @error('profile_photo') is-invalid @enderror" accept="image/*"><div class="form-text">JPG, PNG o WEBP; máximo 2 MB.</div></div>
                    </div>
                    <div class="mb-7"><label class="form-label required">Nombre</label><input type="text" name="name" value="{{ old('name', auth()->user()->name) }}" class="form-control @error('name') is-invalid @enderror">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="mb-2"><label class="form-label required">Correo electrónico</label><input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" class="form-control @error('email') is-invalid @enderror">@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                </div>
                <div class="card-footer d-flex justify-content-end"><button class="btn btn-primary">Guardar perfil</button></div>
            </form>
        </div>

        <div class="col-xl-5">
            <form method="POST" action="{{ route('admin.account.password.update') }}" class="card card-flush">
                @csrf @method('PUT')
                <div class="card-header"><div class="card-title"><h3 class="fw-bold mb-0">Cambiar contraseña</h3></div></div>
                <div class="card-body pt-2">
                    @if ($errors->updatePassword->any())<div class="alert alert-danger">{{ $errors->updatePassword->first() }}</div>@endif
                    <div class="mb-7"><label class="form-label required">Contraseña actual</label><input type="password" name="current_password" class="form-control" autocomplete="current-password"></div>
                    <div class="mb-7"><label class="form-label required">Nueva contraseña</label><input type="password" name="password" class="form-control" autocomplete="new-password"></div>
                    <div><label class="form-label required">Confirmar contraseña</label><input type="password" name="password_confirmation" class="form-control" autocomplete="new-password"></div>
                </div>
                <div class="card-footer d-flex justify-content-end"><button class="btn btn-primary">Actualizar contraseña</button></div>
            </form>
        </div>
    </div>
@endsection
