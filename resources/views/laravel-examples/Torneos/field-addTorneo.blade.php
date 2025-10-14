@extends('layouts.user_type.auth')
@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header pb-0 px-3">
            <h6 class="mb-0">Nuevo Torneo</h6>
        </div>
        <div class="card-body pt-4 p-3">
            @if ($errors->any())
                <div class="alert alert-danger text-white" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(session('success'))
                <div class="alert alert-success text-white" role="alert">
                    {{ session('success') }}
                </div>
            @endif

            <form action="{{ route('torneos.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="nombre">Nombre del Torneo</label>
                            <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" required>
                            @error('nombre')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="formato">Formato del Torneo</label>
                            <select name="formato" class="form-control @error('formato') is-invalid @enderror" required>
                                <option value="liga">Liga</option>
                                <option value="eliminacion">Eliminación Directa</option>
                                <option value="grupos_eliminacion">Grupos + Eliminación</option>
                            </select>
                            @error('formato')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <textarea name="descripcion" class="form-control @error('descripcion') is-invalid @enderror" rows="3" required></textarea>
                    @error('descripcion')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="fecha_inicio">Fecha de Inicio</label>
                            <input type="date" name="fecha_inicio" class="form-control @error('fecha_inicio') is-invalid @enderror" required>
                            @error('fecha_inicio')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="fecha_fin">Fecha de Fin</label>
                            <input type="date" name="fecha_fin" class="form-control @error('fecha_fin') is-invalid @enderror" required>
                            @error('fecha_fin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="minimo_equipos">Mínimo de Equipos</label>
                            <input type="number" name="minimo_equipos" class="form-control @error('minimo_equipos') is-invalid @enderror" required min="2">
                            @error('minimo_equipos')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="maximo_equipos">Máximo de Equipos</label>
                            <input type="number" name="maximo_equipos" class="form-control @error('maximo_equipos') is-invalid @enderror" required min="2">
                            @error('maximo_equipos')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="cuota_inscripcion">Cuota de Inscripción</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="cuota_inscripcion" step="0.01" class="form-control @error('cuota_inscripcion') is-invalid @enderror" required>
                                @error('cuota_inscripcion')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="premio">Premio</label>
                    <input type="text" name="premio" class="form-control @error('premio') is-invalid @enderror" placeholder="Ej: $10,000 en premios">
                    @error('premio')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="reglas">Reglas del Torneo</label>
                    <div id="rules-container">
                        <div class="rule-item mb-2">
                            <div class="input-group">
                                <input type="text" name="reglas[]" class="form-control" placeholder="Agregar regla">
                                <button type="button" class="btn btn-danger remove-rule">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm mt-2" id="add-rule">
                        <i class="fas fa-plus"></i> Agregar Regla
                    </button>
                </div>

                <div class="form-group">
                    <label>Imágenes del Torneo</label>
                    <input type="file" name="imagenes[]" class="form-control @error('imagenes') is-invalid @enderror" multiple accept="image/*">
                    <small class="form-text text-muted">Puedes seleccionar múltiples imágenes</small>
                    @error('imagenes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" name="estado" value="abierto" checked>
                    <label class="form-check-label">Abrir inscripciones inmediatamente</label>
                </div>

                <div class="d-flex justify-content-end">
                    <a href="{{ route('torneos.index') }}" class="btn btn-light m-0">Cancelar</a>
                    <button type="submit" class="btn bg-gradient-primary m-0 ms-2">Crear Torneo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const rulesContainer = document.getElementById('rules-container');
    const addRuleBtn = document.getElementById('add-rule');

    addRuleBtn.addEventListener('click', function() {
        const ruleItem = document.createElement('div');
        ruleItem.className = 'rule-item mb-2';
        ruleItem.innerHTML = `
            <div class="input-group">
                <input type="text" name="reglas[]" class="form-control" placeholder="Agregar regla">
                <button type="button" class="btn btn-danger remove-rule">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        rulesContainer.appendChild(ruleItem);
    });

    rulesContainer.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-rule') || e.target.parentElement.classList.contains('remove-rule')) {
            const ruleItem = e.target.closest('.rule-item');
            ruleItem.remove();
        }
    });
});
</script>
@endsection