@extends('layouts.user_type.auth')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header pb-0 px-3">
            <h6 class="schedule-card-title mb-0">Editar Bono</h6>
        </div>
        <div class="card-body pt-4 p-3">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <form action="{{ route('bonos.update', $bono->id) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="titulo">Título</label>
                            <input type="text" name="titulo" class="form-control @error('titulo') is-invalid @enderror" 
                                   value="{{ old('titulo', $bono->titulo) }}" required>
                            @error('titulo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="tipo">Tipo</label>
                            <input type="text" name="tipo" class="form-control @error('tipo') is-invalid @enderror" 
                                   value="{{ old('tipo', $bono->tipo) }}" required>
                            @error('tipo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <textarea name="descripcion" class="form-control @error('descripcion') is-invalid @enderror" 
                              rows="3" required>{{ old('descripcion', $bono->descripcion) }}</textarea>
                    @error('descripcion')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="precio">Precio (MXN)</label>
                            <input type="number" name="precio" class="form-control @error('precio') is-invalid @enderror" 
                                   value="{{ old('precio', $bono->precio) }}" step="0.01" required>
                            @error('precio')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="duracion_dias">Duración (días)</label>
                            <input type="number" name="duracion_dias" class="form-control @error('duracion_dias') is-invalid @enderror" 
                                   value="{{ old('duracion_dias', $bono->duracion_dias) }}" required>
                            @error('duracion_dias')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="usos_totales">Usos Totales (opcional)</label>
                            <input type="number" name="usos_totales" class="form-control @error('usos_totales') is-invalid @enderror" 
                                   value="{{ old('usos_totales', $bono->usos_totales) }}">
                            @error('usos_totales')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="caracteristicas">Características (separadas por comas)</label>
                    <input type="text" name="caracteristicas" class="form-control @error('caracteristicas') is-invalid @enderror" 
                           value="{{ old('caracteristicas', is_array($bono->caracteristicas) ? implode(', ', $bono->caracteristicas) : $bono->caracteristicas) }}">
                    <small class="text-muted">Ejemplo: "Acceso VIP, Descuento especial, Uso ilimitado"</small>
                    @error('caracteristicas')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" 
                           {{ old('is_active', $bono->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label">Bono Activo</label>
                    @error('is_active')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <a href="{{ route('bonos.index') }}" class="btn btn-light m-0">Cancelar</a>
                    <button type="submit" class="btn bg-gradient-primary m-0 ms-2">Actualizar Bono</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection