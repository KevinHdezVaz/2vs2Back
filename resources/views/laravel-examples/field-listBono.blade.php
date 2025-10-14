@extends('layouts.user_type.auth')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header pb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6>Bonos</h6>
                        <a href="{{ route('bonos.create') }}" class="btn bg-gradient-primary">
                            Nuevo Bono
                        </a>
                    </div>
                </div>
                <div class="card-body px-0 pt-0 pb-2">
                    @if(session('success'))
                        <div class="alert alert-success text-white mx-4">
                            {{ session('success') }}
                        </div>
                    @endif
                    
                    <div class="table-responsive p-0">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Título</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Tipo</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Precio</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Duración (días)</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Estado</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Fecha Creación</th>
                                    <th class="text-secondary opacity-7">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($bonos as $bono)
                                    <tr>
                                        <td>
                                            <div class="d-flex px-2 py-1">
                                                @if($bono->image_path)
                                                    <div>
                                                        <img src="{{ $bono->image_path }}" class="avatar avatar-sm me-3" alt="Bono Image">
                                                    </div>
                                                @endif
                                                <div class="d-flex flex-column justify-content-center">
                                                    <h6 class="mb-0 text-sm">{{ $bono->titulo }}</h6>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <p class="text-xs font-weight-bold mb-0">{{ $bono->tipo }}</p>
                                        </td>
                                        <td>
                                            <p class="text-xs font-weight-bold mb-0">${{ number_format($bono->precio, 2) }}</p>
                                        </td>
                                        <td>
                                            <p class="text-xs font-weight-bold mb-0">{{ $bono->duracion_dias }}</p>
                                        </td>
                                        <td>
                                            <span class="badge badge-sm {{ $bono->is_active ? 'bg-gradient-success' : 'bg-gradient-secondary' }}">
                                                {{ $bono->is_active ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-secondary text-xs font-weight-bold">
                                                {{ \Carbon\Carbon::parse($bono->created_at)->format('d/m/Y') }}
                                            </span>
                                        </td>
                                        <td class="align-middle">
                                            <a href="{{ route('bonos.edit', $bono->id) }}" class="btn btn-link text-info px-3 mb-0">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('bonos.destroy', $bono->id) }}" 
                                                  method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" 
                                                        class="btn btn-link text-danger text-gradient px-3 mb-0"
                                                        onclick="return confirm('¿Está seguro de eliminar este bono?')">
                                                    <i class="far fa-trash-alt"></i>
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