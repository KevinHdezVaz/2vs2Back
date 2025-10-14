@extends('layouts.user_type.auth')

@section('content')
<main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg ">
    <div class="container-fluid py-4">
        <div class="card">
            <div class="card-header pb-0">
                {{-- CAMBIO: Se usa $plane en lugar de $plan --}}
                <h6>Editar Precio del Plan: {{ $plane->name }}</h6>
            </div>
            <div class="card-body">
                {{-- CAMBIO: Se usa $plane en lugar de $plan --}}
                <form action="{{ route('planes.update', ['plane' => $plane]) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label for="price" class="form-label">Nuevo Precio (USD)</label>
                        {{-- CAMBIO: Se usa $plane en lugar de $plan --}}
                        <input type="number" step="0.01" class="form-control" id="price" name="price" value="{{ old('price', $plane->price) }}">
                        @error('price')
                            <p class="text-danger text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">Actualizar Precio</button>
                    <a href="{{ route('planes.index') }}" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</main>
@endsection