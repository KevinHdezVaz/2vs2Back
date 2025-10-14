@extends('layouts.user_type.auth')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header pb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6>Torneos</h6>
                        <a href="{{ route('torneos.create') }}" class="btn bg-gradient-primary">
                            <i class="fas fa-plus me-2"></i>Nuevo Torneo
                        </a>
                    </div>
                </div>
                <div class="card-body px-0 pt-0 pb-2">
                    @if(session('success'))
                        <div class="alert alert-success text-white mx-4">
                            {{ session('success') }}
                        </div>
                    @endif

                    <!-- Switch para mostrar/ocultar Torneos -->
                    <div class="px-4 py-3">
                        <form method="POST" action="{{ route('settings.update') }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="show_tournaments" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="showTournamentsSwitch" 
                                       name="show_tournaments" 
                                       value="1" 
                                       {{ $show_tournaments ? 'checked' : '' }}>
                                <label class="form-check-label" for="showTournamentsSwitch">
                                    Mostrar Torneos en la App
                                </label>
                            </div>
                            <button type="submit" class="btn btn-sm bg-gradient-primary mt-2">Guardar</button>
                        </form>
                    </div>

                    <div class="table-responsive p-0">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Torneo</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Formato</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Fechas</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Equipos</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Estado</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($torneos as $torneo)
                                    <tr>
                                        <td>
                                            <div class="d-flex px-3 py-1">
                                                <div>
                                                    @if(is_array($torneo->imagenesTorneo) && count($torneo->imagenesTorneo) > 0)
                                                        <img src="{{ $torneo->imagenesTorneo[0] }}" class="avatar avatar-sm me-3" alt="torneo imagen">
                                                    @else
                                                        <div class="avatar avatar-sm me-3 bg-gradient-primary">
                                                            <i class="fas fa-trophy text-white"></i>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="d-flex flex-column justify-content-center">
                                                    <h6 class="mb-0 text-sm">{{ $torneo->nombre }}</h6>
                                                    <p class="text-xs text-secondary mb-0">
                                                        {{ \Str::limit($torneo->descripcion, 50) }}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <p class="text-xs font-weight-bold mb-0">{{ ucfirst($torneo->formato) }}</p>
                                        </td>
                                        <td>
                                            <p class="text-xs font-weight-bold mb-0">
                                                {{ $torneo->fecha_inicio->format('d/m/Y') }} -
                                                {{ $torneo->fecha_fin->format('d/m/Y') }}
                                            </p>
                                        </td>
                                        <td>
                                            <p class="text-xs font-weight-bold mb-0">
                                                {{ $torneo->equipos->count() }}/{{ $torneo->maximo_equipos }}
                                            </p>
                                        </td>
                                        <td>
                                            <span class="badge badge-sm bg-gradient-{{ $torneo->getEstadoColor() }}">
                                                {{ ucfirst($torneo->estado) }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex">
                                                <a href="{{ route('torneos.edit', $torneo) }}" 
                                                   class="btn btn-link text-info px-3 mb-0">
                                                    <i class="fas fa-pencil-alt me-2"></i>Editar
                                                </a>
                                                <form action="{{ route('torneos.destroy', $torneo) }}" 
                                                      method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" 
                                                            class="btn btn-link text-danger mb-0"
                                                            onclick="return confirm('¿Está seguro de eliminar este torneo?')">
                                                        <i class="fas fa-trash me-2"></i>Eliminar
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($torneos->isEmpty())
                        <div class="text-center py-4">
                            <p class="text-secondary mb-0">No hay torneos registrados</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection