@extends('layouts.user_type.auth')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header pb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6>Productos</h6>
                        <a href="{{ route('product.create') }}" class="btn bg-gradient-primary">
                            Nuevo Producto
                        </a>
                    </div>
                </div>
                <div class="card-body px-0 pt-0 pb-2">
                    @if(session('success'))
                        <div class="alert alert-success text-white mx-4">
                            {{ session('success') }}
                        </div>
                    @endif

                    <!-- Switch para mostrar/ocultar Tienda -->
                    <div class="px-4 py-3">
                    <form method="POST" action="{{ route('settings.update') }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="show_store" value="0">
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="showStoreSwitch" name="show_store" 
            value="1" {{ $show_store ? 'checked' : '' }}>
        <label class="form-check-label" for="showStoreSwitch">
            Mostrar Tienda en la App
        </label>
    </div>
    <button type="submit" class="btn btn-sm bg-gradient-primary mt-2">Guardar</button>
</form>
                    </div>

                    <div class="table-responsive p-0">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Imagen</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Nombre</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Descripción</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Precio</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Fecha de Creación</th>
                                    <th class="text-secondary opacity-7">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($products as $product)
                                    <tr>
                                        <td>
                                            <div class="d-flex px-2 py-1">
                                                <div>
                                                    @if ($product->images)
                                                        @foreach ($product->images as $image)
                                                            <img src="{{ asset('storage/' . $image) }}" class="avatar avatar-sm me-3" alt="Product Image">
                                                        @endforeach
                                                    @else
                                                        <span>No hay imágenes</span>
                                                    @endif
                                                </div>
                                                <div class="d-flex flex-column justify-content-center">
                                                    <h6 class="mb-0 text-sm">Producto #{{ $product->id }}</h6>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <p class="text-xs font-weight-bold mb-0">
                                                {{ $product->name }}
                                            </p>
                                        </td>
                                        <td>
                                            <p class="text-xs font-weight-bold mb-0">
                                                {{ $product->description }}
                                            </p>
                                        </td>
                                        <td>
                                            <p class="text-xs font-weight-bold mb-0">
                                                ${{ number_format($product->price, 2) }}
                                            </p>
                                        </td>
                                        <td>
                                            <span class="text-secondary text-xs font-weight-bold">
                                                {{ \Carbon\Carbon::parse($product->created_at)->format('d/m/Y') }}
                                            </span>
                                        </td>
                                        <td class="align-middle">
                                            <a href="{{ route('product.edit', $product->id) }}" class="btn btn-link text-warning text-gradient px-3 mb-0">
                                                <i class="fas fa-pencil-alt"></i> Editar
                                            </a>
                                            <form action="{{ route('product.destroy', $product->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-link text-danger text-gradient px-3 mb-0"
                                                        onclick="return confirm('¿Está seguro de eliminar este producto?')">
                                                    <i class="far fa-trash-alt"></i> Eliminar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection