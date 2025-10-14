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
            <h6 class="schedule-card-title mb-0">Editar Cancha</h6>
        </div>
        <div class="card-body pt-4 p-3">
            <form action="{{ route('field-management.update', $field->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name">Nombre de la Cancha</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name', $field->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Tipos de Cancha</label>
                            <div class="row">
                                @php
                                    $types = is_string($field->types) ? json_decode($field->types, true) : ($field->types ?? []);
                                    $types = is_array($types) ? $types : [];
                                @endphp
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="types[]" value="fut5"
                                               {{ in_array('fut5', $types) ? 'checked' : '' }}>
                                        <label class="form-check-label">Fútbol 5</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="types[]" value="fut7"
                                               {{ in_array('fut7', $types) ? 'checked' : '' }}>
                                        <label class="form-check-label">Fútbol 7</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="types[]" value="fut11"
                                               {{ in_array('fut11', $types) ? 'checked' : '' }}>
                                        <label class="form-check-label">Fútbol 11</label>
                                    </div>
                                </div>
                            </div>
                            @error('types')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="mt-2 text-muted" id="types-display">
                                @php
                                    $typeNames = [];
                                    foreach ($types as $type) {
                                        switch ($type) {
                                            case 'fut5':
                                                $typeNames[] = 'Fútbol 5';
                                                break;
                                            case 'fut7':
                                                $typeNames[] = 'Fútbol 7';
                                                break;
                                            case 'fut11':
                                                $typeNames[] = 'Fútbol 11';
                                                break;
                                        }
                                    }
                                    if (count($typeNames) > 1) {
                                        echo 'Tipos seleccionados: ' . implode(' y ', $typeNames);
                                    } else {
                                        echo 'Tipo seleccionado: ' . ($typeNames[0] ?? 'Ninguno');
                                    }
                                @endphp
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Descripción</label>
                    <textarea name="description" class="form-control @error('description') is-invalid @enderror" 
                              rows="3" required>{{ old('description', $field->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="municipio">Municipio</label>
                            <input type="text" name="municipio" class="form-control @error('municipio') is-invalid @enderror" 
                                   value="{{ old('municipio', $field->municipio) }}" required>
                            @error('municipio')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="price_per_match">Precio por Partido</label>
                            <input type="number" name="price_per_match" class="form-control @error('price_per_match') is-invalid @enderror" 
                                   value="{{ old('price_per_match', $field->price_per_match) }}" step="0.01" required>
                            @error('price_per_match')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="latitude">Latitud</label>
                            <input type="number" name="latitude" class="form-control @error('latitude') is-invalid @enderror" 
                                   value="{{ old('latitude', $field->latitude) }}" step="any">
                            @error('latitude')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="longitude">Longitud</label>
                            <input type="number" name="longitude" class="form-control @error('longitude') is-invalid @enderror" 
                                   value="{{ old('longitude', $field->longitude) }}" step="any">
                            @error('longitude')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Amenities</label>
                    @php
                        $amenities = is_string($field->amenities) ? json_decode($field->amenities, true) : ($field->amenities ?? []);
                        $amenities = is_array($amenities) ? $amenities : [];
                    @endphp
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="amenities[]" value="Vestuarios"
                                       {{ in_array('Vestuarios', $amenities) ? 'checked' : '' }}>
                                <label class="form-check-label">Vestuarios</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="amenities[]" value="Estacionamiento"
                                       {{ in_array('Estacionamiento', $amenities) ? 'checked' : '' }}>
                                <label class="form-check-label">Estacionamiento</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="amenities[]" value="Iluminación nocturna"
                                       {{ in_array('Iluminación nocturna', $amenities) ? 'checked' : '' }}>
                                <label class="form-check-label">Iluminación nocturna</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Horarios Disponibles</label>
                    <p class="text-muted">Los horarios disponibles se calculan automáticamente según los partidos registrados.</p>
                    <div class="mt-2">
                        @if(isset($field->calculated_available_hours))
                            @foreach($field->calculated_available_hours as $day => $hours)
                                <h6>{{ ucfirst($day) }}</h6>
                                <ul class="list-group">
                                    @foreach($hours as $hour)
                                        <li class="list-group-item">{{ $hour }}</li>
                                    @endforeach
                                </ul>
                            @endforeach
                        @else
                            <p>No hay horarios disponibles calculados aún.</p>
                        @endif
                    </div>
                </div>

                <div class="form-group">
    <label for="images">Imágenes Actuales</label>
    <div class="row mb-3">
        @php
            $images = is_string($field->images) ? json_decode($field->images, true) : ($field->images ?? []);
            $images = is_array($images) ? $images : [];
        @endphp
        @if(!empty($images))
            @foreach($images as $index => $image)
                <div class="col-md-3 mb-2" id="image-container-{{ $index }}">
                    <div class="position-relative">
                        <img src="{{ $image }}" class="img-thumbnail" style="height: 150px; width: 100%; object-fit: cover;">
                        <button type="button" 
                                class="btn btn-danger btn-sm position-absolute"
                                style="top: 5px; right: 5px; padding: 3px 8px; z-index: 10;"
                                onclick="removeImage({{ $index }})">
                            <i class="fas fa-times"></i>
                        </button>
                        <input type="hidden" name="existing_images[]" value="{{ $image }}">
                    </div>
                </div>
            @endforeach
        @else
            <p>No hay imágenes actuales.</p>
        @endif
    </div>
    
    <label for="images">Agregar Nuevas Imágenes</label>
    <input type="file" name="images[]" class="form-control @error('images') is-invalid @enderror" multiple accept="image/*">
    <div id="image-preview-container" class="row mt-3"></div>
    @error('images')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" {{ $field->is_active ? 'checked' : '' }}>
                    <label class="form-check-label">Cancha Activa</label>
                    @error('is_active')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <a href="{{ route('field-management') }}" class="btn btn-light m-0">Cancelar</a>
                    <button type="submit" class="btn bg-gradient-primary m-0 ms-2">Actualizar Cancha</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Funciones para manejo de imágenes
function removeImage(index) {
    const container = document.getElementById(`image-container-${index}`);
    if (container) {
        container.remove();
        updateExistingImages();
    }
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

function previewImages(input) {
    const previewContainer = document.getElementById('image-preview-container');
    if (!previewContainer) {
        const container = document.createElement('div');
        container.id = 'image-preview-container';
        container.className = 'row mt-3';
        input.parentElement.appendChild(container);
    }

    const files = input.files;
    const container = document.getElementById('image-preview-container');
    container.innerHTML = '';

    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        if (file) {
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
                container.appendChild(previewDiv);
            };
            reader.readAsDataURL(file);
        }
    }
}

function removePreview(button) {
    button.closest('.col-md-3').remove();
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelector('input[name="images[]"]').addEventListener('change', function() {
        previewImages(this);
    });

    const typeCheckboxes = document.querySelectorAll('input[name="types[]"]');
    const typesDisplay = document.getElementById('types-display');

    function updateTypesDisplay() {
        const selectedTypes = Array.from(typeCheckboxes)
            .filter(checkbox => checkbox.checked)
            .map(checkbox => checkbox.nextElementSibling.textContent.trim());

        if (selectedTypes.length === 0) {
            typesDisplay.textContent = 'Ningún tipo seleccionado';
        } else if (selectedTypes.length === 1) {
            typesDisplay.textContent = 'Tipo seleccionado: ' + selectedTypes[0];
        } else {
            typesDisplay.textContent = 'Tipos seleccionados: ' + selectedTypes.slice(0, -1).join(', ') + ' y ' + selectedTypes[selectedTypes.length - 1];
        }
    }

    typeCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateTypesDisplay);
    });

    updateTypesDisplay();
});
</script>
@endsection