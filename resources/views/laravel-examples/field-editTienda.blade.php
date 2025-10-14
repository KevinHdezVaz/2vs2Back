@extends('layouts.user_type.auth')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header pb-0 px-3">
            <h6 class="mb-0">Editar Producto</h6>
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
            <form method="POST" action="{{ route('product.update', $product->id) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label for="name">Nombre del Producto</label>
                    <input type="text" id="name" name="name" 
                        class="form-control @error('name') is-invalid @enderror" 
                        value="{{ old('name', $product->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="description">Descripción</label>
                    <textarea id="description" name="description" 
                        class="form-control @error('description') is-invalid @enderror" 
                        rows="3" required>{{ old('description', $product->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="price">Precio</label>
                    <input type="number" id="price" name="price" 
                        class="form-control @error('price') is-invalid @enderror" 
                        value="{{ old('price', $product->price) }}" step="0.01" required>
                    @error('price')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="images">Imágenes del Producto</label>
                    <input type="file" id="images" name="images[]" 
                        class="form-control @error('images') is-invalid @enderror" 
                        accept="image/jpeg,image/png,image/jpg" multiple>
                    @error('images')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Formatos permitidos: JPG, JPEG, PNG. Tamaño máximo: 10MB por imagen.</small>
                    @if ($product->images)
                        <div class="mt-2">
                            @foreach ($product->images as $image)
                                <img src="{{ asset('storage/' . $image) }}" alt="Imagen del producto" 
                                    class="img-thumbnail" style="max-width: 200px; margin-right: 10px;">
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="form-group">
                    <label for="colors">Colores disponibles (separados por comas)</label>
                    <input type="text" id="colors" name="colors" 
                        class="form-control @error('colors') is-invalid @enderror" 
                        value="{{ old('colors', implode(',', $product->colors ?? [])) }}" 
                        placeholder="Rojo, Azul, Negro">
                    @error('colors')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Ejemplo: Rojo, Azul, Negro</small>
                </div>
                <div class="form-group">
                    <label for="sizes">Tallas disponibles (separadas por comas)</label>
                    <input type="text" id="sizes" name="sizes" 
                        class="form-control @error('sizes') is-invalid @enderror" 
                        value="{{ old('sizes', implode(',', $product->sizes ?? [])) }}" 
                        placeholder="S, M, L, XL">
                    @error('sizes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Ejemplo: S, M, L, XL</small>
                </div>
                <div class="form-group">
                    <label for="units">Cantidad máxima de piezas disponibles</label>
                    <input type="number" id="units" name="units" 
                        class="form-control @error('units') is-invalid @enderror" 
                        value="{{ old('units', $product->units ?? 1) }}" min="1" step="1">
                    @error('units')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Ejemplo: 5 (máximo de piezas que se pueden pedir)</small>
                </div>
                <div class="d-flex justify-content-end mt-4">
                    <a href="{{ route('product.index') }}" class="btn btn-light m-0">Cancelar</a>
                    <button type="submit" class="btn bg-gradient-primary m-0 ms-2">Actualizar Producto</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection