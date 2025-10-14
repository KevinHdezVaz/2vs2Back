use App\Models\DailyMatch;
@extends('layouts.user_type.auth')

@section('content')
    <div class="row">
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-capitalize font-weight-bold">Nuevos Usuarios</p>
                                <h5 class="font-weight-bolder mb-0">
                                    {{ $newUsersCount }}
                                    <span class="text-success text-sm font-weight-bolder">Este mes</span>
                                </h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-primary shadow text-center border-radius-md">
                                <i class="ni ni-single-02 text-lg opacity-10"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-capitalize font-weight-bold">Nuevos Equipos</p>
                                <h5 class="font-weight-bolder mb-0">
                                    {{ $newTeamsCount }}
                                    <span class="text-success text-sm font-weight-bolder">Este mes</span>
                                </h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-primary shadow text-center border-radius-md">
                                <i class="ni ni-trophy text-lg opacity-10"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
    <div class="card">
        <div class="card-body p-3">
            <div class="row">
                <div class="col-8">
                    <div class="numbers">
                        <p class="text-sm mb-0 text-capitalize font-weight-bold">Partidos Jugados</p>
                        <h5 class="font-weight-bolder mb-0">
                            {{ $matchesPlayedThisMonth }}
                            <span class="text-success text-sm font-weight-bolder">Este mes</span>
                        </h5>
                    </div>
                </div>
                <div class="col-4 text-end">
                    <div class="icon icon-shape bg-gradient-success shadow text-center border-radius-md">
                        <i class="ni ni-calendar-grid-58 text-lg opacity-10"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-capitalize font-weight-bold">Ocupación de Canchas</p>
                                <h5 class="font-weight-bolder mb-0">
                                    {{ number_format($occupationPercentage, 2) }}%
                                    <span class="text-success text-sm font-weight-bolder">Hoy</span>
                                </h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-info shadow text-center border-radius-md">
                                <i class="ni ni-world text-lg opacity-10"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-7">
            <div class="card z-index-2">
                <div class="card-header pb-0">
                    <h6>Registro de Usuarios y Equipos</h6>
                </div>
                <div class="card-body p-3">
                    <div class="chart">
                        <canvas id="mixed-chart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card z-index-2">
                <div class="card-header pb-0">
                    <h6>Partidos por Día (Esta Semana)</h6>
                </div>
                <div class="card-body p-3">
                    <div class="chart">
                        <canvas id="matches-chart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header pb-0">
                    <h6>Filtro de Partidos</h6>
                </div>
                <div class="card-body p-3">
                    <form method="GET" action="{{ route('home') }}">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="date">Fecha</label>
                                    <input type="date" name="date" id="date" class="form-control" value="{{ request('date') }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="status">Estado</label>
                                    <select name="status" id="status" class="form-control">
                                        <option value="">Todos</option>
                                        <option value="full" {{ request('status') == 'full' ? 'selected' : '' }}>Completo</option>
                                        <option value="available" {{ request('status') == 'available' ? 'selected' : '' }}>Disponible</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label> </label>
                                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header pb-0">
                    <h6>Lista de Partidos Diarios</h6>
                    <p class="text-sm">Todos los partidos disponibles y completos</p>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Nombre</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Cancha</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Fecha</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Hora</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($matches as $match)
                                    <tr class="{{ $match->status == 'full' ? 'bg-light text-muted' : '' }}">
                                        <td>
                                            <span class="text-xs font-weight-bold">{{ $match->name }}</span>
                                        </td>
                                        <td>
                                            <span class="text-xs font-weight-bold">{{ $match->field->name ?? 'Sin cancha' }}</span>
                                        </td>
                                        <td>
                                            <span class="text-xs font-weight-bold">{{ $match->schedule_date->format('d/m/Y') }}</span>
                                        </td>
                                        <td>
                                            <span class="text-xs font-weight-bold">{{ $match->start_time }} - {{ $match->end_time }}</span>
                                        </td>
                                        <td>
                                            <span class="badge badge-sm {{ $match->status == 'full' ? 'bg-secondary' : 'bg-success' }}">
                                                {{ $match->status == 'full' ? 'Completo' : 'Disponible' }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No hay partidos disponibles</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-center mt-3">
                        {{ $matches->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header pb-0">
                    <h6>Próximos Partidos (Hoy y Mañana)</h6>
                </div>
                <div class="card-body p-3">
                    <ul class="list-group">
                        @forelse ($upcomingMatches as $match)
                            <li class="list-group-item">
                                <span class="font-weight-bold">{{ $match->name }}</span> - 
                                {{ $match->schedule_date->format('d/m/Y') }} a las {{ $match->start_time }} 
                                (Cancha: {{ $match->field->name ?? 'Sin cancha' }})
                            </li>
                        @empty
                            <li class="list-group-item text-center">No hay partidos próximos</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header pb-0">
                <h6>Acciones Rápidas</h6>
            </div>
            <div class="card-body p-3">
                <div class="row">
                    <div class="col-md-4">
                        <a href="{{ route('daily-matches.create') }}" class="btn btn-primary w-100">Crear Partido</a>
                    </div>
                    <div class="col-md-4">
                        <a href="{{ route('field-management.create') }}" class="btn btn-success w-100">Agregar Cancha</a>
                    </div>
                     
                </div>
            </div>
        </div>
    </div>
</div>

 
@endsection

@push('dashboard')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />

<script>
window.onload = function() {
    // Gráfico Mixto (Usuarios y Equipos)
    var ctx = document.getElementById("mixed-chart").getContext("2d");
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: @json($monthLabels),
            datasets: [{
                label: 'Usuarios Registrados',
                data: @json($userData),
                borderColor: '#cb0c9f',
                tension: 0.4,
                fill: false
            }, {
                label: 'Equipos Creados',
                data: @json($teamData),
                borderColor: '#3A416F',
                tension: 0.4,
                fill: false
            }]
        },
        options: {
            responsive: true,
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });

    // Gráfico de Partidos por Día
    var ctxMatches = document.getElementById("matches-chart").getContext("2d");
    new Chart(ctxMatches, {
        type: 'bar',
        data: {
            labels: @json($matchesByDay->pluck('date')->toArray()),
            datasets: [{
                label: 'Partidos',
                data: @json($matchesByDay->pluck('count')->toArray()),
                backgroundColor: '#3A416F',
                borderColor: '#3A416F',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Mapa de Canchas
    document.addEventListener('DOMContentLoaded', function() {
        var map = L.map('map').setView([-34.6037, -58.3816], 13); // Ejemplo: Buenos Aires
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
        }).addTo(map);

        @foreach ($fields as $field)
            L.marker([{{ $field->latitude ?? -34.6037 }}, {{ $field->longitude ?? -58.3816 }}]).addTo(map)
                .bindPopup('{{ $field->name }} - {{ $field->address ?? 'Sin dirección' }}');
        @endforeach
    });
};
</script>
@endpush