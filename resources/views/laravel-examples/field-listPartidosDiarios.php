@extends('layouts.user_type.auth')
@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header pb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6>Partidos Diarios</h6>
                        <a href="{{ route('daily-matches.create') }}" class="btn bg-gradient-primary">
                            Nuevo Partido
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
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Cancha</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Horario</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Nombre del partido</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Precio</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Estado</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Fecha</th>
                                    <th class="text-secondary opacity-7">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($matches as $match)
                                    <tr>
                                        <td>
                                            <div class="d-flex px-2 py-1">
                                                <div class="d-flex flex-column justify-content-center">
                                                    <h6 class="mb-0 text-sm">{{ $match->field->name }}</h6>
                                                    <p class="text-xs text-secondary mb-0">{{ $match->field->type }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <p class="text-xs font-weight-bold mb-0">
                                                {{ \Carbon\Carbon::parse($match->start_time)->format('H:i') }} - 
                                                {{ \Carbon\Carbon::parse($match->end_time)->format('H:i') }}
                                            </p>
                                        </td>
                                        <td>
    <p class="text-xs font-weight-bold mb-0">
        {{ $match->name }}
    </p>
</td>

                                        <td>
                                            <p class="text-xs font-weight-bold mb-0">
                                                ${{ number_format($match->price, 2) }}
                                            </p>
                                        </td>
                                        <td>
                                            @if($match->player_count >= $match->max_players)
                                                <span class="badge badge-sm bg-gradient-secondary">Completo</span>
                                            @else
                                                <span class="badge badge-sm bg-gradient-success">Disponible</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="text-secondary text-xs font-weight-bold">
                                                {{ \Carbon\Carbon::parse($match->schedule_date)->format('d/m/Y') }}
                                            </span>
                                        </td>
                                        <td class="align-middle">
                                            
                                            <form action="{{ route('daily-matches.destroy', $match->id) }}" 
                                                  method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" 
                                                        class="btn btn-link text-danger text-gradient px-3 mb-0"
                                                        onclick="return confirm('¿Está seguro de eliminar este partido?')">
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