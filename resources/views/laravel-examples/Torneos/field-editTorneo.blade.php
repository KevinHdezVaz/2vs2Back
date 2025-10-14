@extends('layouts.user_type.auth')
@section('content')
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

<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header pb-0 px-3">
            <h6 class="mb-0">Editar Torneo</h6>
        </div>
        <div class="card-body pt-4 p-3">
            <form action="{{ route('torneos.update', $torneo->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="nombre">Nombre del Torneo</label>
                            <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" 
                                   value="{{ old('nombre', $torneo->nombre) }}" required>
                            @error('nombre')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="formato">Formato del Torneo</label>
                            <select name="formato" class="form-control @error('formato') is-invalid @enderror" required>
                                <option value="liga" {{ $torneo->formato == 'liga' ? 'selected' : '' }}>Liga</option>
                                <option value="eliminacion" {{ $torneo->formato == 'eliminacion' ? 'selected' : '' }}>Eliminación Directa</option>
                                <option value="grupos_eliminacion" {{ $torneo->formato == 'grupos_eliminacion' ? 'selected' : '' }}>Grupos + Eliminación</option>
                            </select>
                            @error('formato')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <textarea name="descripcion" class="form-control @error('descripcion') is-invalid @enderror" 
                              rows="3" required>{{ old('descripcion', $torneo->descripcion) }}</textarea>
                    @error('descripcion')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="fecha_inicio">Fecha de Inicio</label>
                            <input type="date" name="fecha_inicio" class="form-control @error('fecha_inicio') is-invalid @enderror" 
                                   value="{{ old('fecha_inicio', $torneo->fecha_inicio) }}" required>
                            @error('fecha_inicio')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="fecha_fin">Fecha de Fin</label>
                            <input type="date" name="fecha_fin" class="form-control @error('fecha_fin') is-invalid @enderror" 
                                   value="{{ old('fecha_fin', $torneo->fecha_fin) }}" required>
                            @error('fecha_fin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                <div class="form-group">
                            <label for="formato">Estatus</label>
                            <select name="formato" class="form-control @error('formato') is-invalid @enderror" required>
                                <option value="proximamente" {{ $torneo->estado == 'proximamente' ? 'selected' : '' }}>Proximamente</option>
                                <option value="abierto" {{ $torneo->estado == 'abierto' ? 'selected' : '' }}>Abierto</option>
                                <option value="en_progreso" {{ $torneo->estado == 'en_progreso' ? 'selected' : '' }}>En Progreso</option>
                                <option value="completado" {{ $torneo->estado == 'completado' ? 'selected' : '' }}>Completado</option>
                                <option value="cancelado" {{ $torneo->estado == 'cancelado' ? 'selected' : '' }}>Cancelado</option>

                            </select>
                            @error('formato')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="minimo_equipos">Mínimo de Equipos</label>
                            <input type="number" name="minimo_equipos" class="form-control @error('minimo_equipos') is-invalid @enderror" 
                                   value="{{ old('minimo_equipos', $torneo->minimo_equipos) }}" required min="2">
                            @error('minimo_equipos')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="maximo_equipos">Máximo de Equipos</label>
                            <input type="number" name="maximo_equipos" class="form-control @error('maximo_equipos') is-invalid @enderror" 
                                   value="{{ old('maximo_equipos', $torneo->maximo_equipos) }}" required min="2">
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
                                <input type="number" name="cuota_inscripcion" step="0.01" class="form-control @error('cuota_inscripcion') is-invalid @enderror" 
                                       value="{{ old('cuota_inscripcion', $torneo->cuota_inscripcion) }}" required>
                                @error('cuota_inscripcion')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="premio">Premio</label>
                    <input type="text" name="premio" class="form-control @error('premio') is-invalid @enderror" 
                           value="{{ old('premio', $torneo->premio) }}" placeholder="Ej: $10,000 en premios">
                    @error('premio')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="reglas">Reglas del Torneo</label>
                    <div id="rules-container">
                        @foreach(old('reglas', $torneo->reglas ?? []) as $index => $regla)
                            <div class="rule-item mb-2">
                                <div class="input-group">
                                    <input type="text" name="reglas[]" class="form-control" value="{{ $regla }}" placeholder="Agregar regla">
                                    <button type="button" class="btn btn-danger remove-rule">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm mt-2" id="add-rule">
                        <i class="fas fa-plus"></i> Agregar Regla
                    </button>
                </div>

                <div class="form-group">
                    <label>Imágenes del Torneo</label>
                    <div class="row mb-3">
                        @if($torneo->imagenesTorneo)
                            @foreach(json_decode($torneo->imagenesTorneo) as $index => $imagen)
                                <div class="col-md-3 mb-2" id="image-container-{{ $index }}">
                                    <div class="position-relative">
                                        <img src="{{ $imagen }}" class="img-thumbnail" style="height: 150px; width: 100%; object-fit: cover;">
                                        <button type="button" 
                                                class="btn btn-danger btn-sm position-absolute"
                                                style="top: 5px; right: 5px; padding: 3px 8px; z-index: 10;"
                                                onclick="removeImage({{ $index }})">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <input type="hidden" name="existing_images[]" value="{{ $imagen }}">
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                    
                    <label for="imagenes">Agregar Nuevas Imágenes</label>
                    <input type="file" name="imagenes[]" class="form-control @error('imagenesTorneo') is-invalid @enderror" multiple accept="image/*">
                    <div id="image-preview-container" class="row mt-3"></div>
                    @error('imagenes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="estado" value="abierto" {{ $torneo->estado == 'abierto' ? 'checked' : '' }}>
                    <label class="form-check-label">Abrir inscripciones inmediatamente</label>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <a href="{{ route('torneos.index') }}" class="btn btn-light m-0">Cancelar</a>
                    <button type="submit" class="btn bg-gradient-primary m-0 ms-2">Actualizar Torneo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    
    // Funciones globales para eliminar imágenes
function removeImage(index) {
    const container = document.getElementById(`image-container-${index}`);
    if (container) {
        container.remove();
        updateExistingImages();
    }
}

function removePreview(button) {
    button.closest('.col-md-3').remove();
}

function updateExistingImages() {
    const currentImages = Array.from(document.querySelectorAll('input[name="existing_images[]"]'))
        .map(input => input.value);
    
    let removedImages = document.getElementById('removed_images');
    if (!removedImages) {
        removedImages = document.createElement('input');
        removedImages.type = 'hidden';
        removedImages.name = 'removed_images';
        removedImages.id = 'removed_images';
        document.querySelector('form').appendChild(removedImages);
    }
    removedImages.value = JSON.stringify(currentImages);
}

document.addEventListener('DOMContentLoaded', function () {
    const rulesContainer = document.getElementById('rules-container');
    const addRuleBtn = document.getElementById('add-rule');

    // Event listeners para las reglas
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

    // Función para previsualizar imágenes
    function previewImages(input) {
        const previewContainer = document.getElementById('image-preview-container');
        if (input.files && input.files.length > 0) {
            Array.from(input.files).forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewDiv = document.createElement('div');
                    previewDiv.className = 'col-md-3 mb-2';
                    previewDiv.innerHTML = `
                        <div class="position-relative">
                            <img src="${e.target.result}" class="img-thumbnail" style="height: 150px; width: 100%; object-fit: cover;">
                            <button type="button" 
                                    class="btn btn-danger btn-sm position-absolute"
                                    style="top: 5px; right: 5px; padding: 3px 8px;"
                                    onclick="removePreview(this)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `;
                    previewContainer.appendChild(previewDiv);
                };
                reader.readAsDataURL(file);
            });
        }
    }

    // Event listener para la carga de nuevas imágenes
    const imageInput = document.querySelector('input[name="imagenes[]"]');
    if (imageInput) {
        imageInput.addEventListener('change', function() {
            document.getElementById('image-preview-container').innerHTML = ''; // Limpiar previsualizaciones anteriores
            previewImages(this);
        });
    }
});
</script>
@endsection