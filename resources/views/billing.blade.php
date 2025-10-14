@extends('layouts.user_type.auth')

@section('content')
    <div class="container-fluid py-4">
        <div class="row">
            <!-- Resumen y Gráfica -->

            <div class="row">
    <div class="col-md-6">
        <form id="dateFilterForm">
            <div class="input-group">
                <!-- Selector de mes -->
                <select name="month" class="form-control">
                    <option value="">Todos los meses</option>
                    @for ($i = 1; $i <= 12; $i++)
                        <option value="{{ $i }}" {{ request('month') == $i ? 'selected' : '' }}>
                            {{ DateTime::createFromFormat('!m', $i)->formatLocalized('%B') }}
                        </option>
                    @endfor
                </select>

                <!-- Botón de filtrar -->
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </div>
        </form>
    </div>
</div>

            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header pb-0 p-3">
                        <h6 class="mb-0">Resumen de Ingresos</h6>
                    </div>
                    <div class="card-body p-3">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header pb-0 p-3">
                        <div class="row">
                            <div class="col-6 d-flex align-items-center">
                                <h6 class="mb-0">Resumen</h6>
                            </div>
                            <div class="col-6 text-end">
                                <a href="{{ route('payments') }}" class="btn btn-outline-primary btn-sm mb-0">Ver Todo</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-3 pb-0">
    <ul class="list-group">
        <li class="list-group-item border-0 ps-0">Pagos por Partidos: ${{ $totalMatchPayments }}</li>
        <li class="list-group-item border-0 ps-0">Pagos por Bono: ${{ $totalBonusPayments }}</li>
        <li class="list-group-item border-0 ps-0">Pagos por Reservas Canchas: ${{ $totalBookingPayments }}</li>
        <li class="list-group-item border-0 ps-0">Total Completados: ${{ $totalCompleted }}</li>
        <li class="list-group-item border-0 ps-0">Total Pendientes: ${{ $totalPending }}</li>
    </ul>
</div>
                </div>
            </div>
        </div>

        <!-- Lista de Órdenes -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <h6>Órdenes Recientes</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <form id="dateFilterForm">
                                    <div class="input-group">
                                        <input type="text" id="startDate" name="start_date" class="form-control flatpickr" placeholder="Fecha Inicial">
                                        <input type="text" id="endDate" name="end_date" class="form-control flatpickr" placeholder="Fecha Final">
                                        <button type="submit" class="btn btn-primary">Filtrar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-3">
    @foreach($orders as $order)
        <div class="card mb-3">
            <div class="card-header pb-0">
                <h6>Orden #{{ $order->id }}</h6>
                <p class="text-sm">Creada: {{ $order->created_at->format('d/m/Y H:i') }}</p>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-uppercase text-body text-xs font-weight-bolder">Detalles de la Orden</h6>
                        <ul class="list-group">
                            <li class="list-group-item border-0 ps-0">Usuario: {{ $order->user->name ?? 'Usuario Desconocido' }}</li>
                            <li class="list-group-item border-0 ps-0">Total: ${{ $order->total }}</li>
                            <li class="list-group-item border-0 ps-0">Estado: <span class="badge badge-sm {{ $order->status == 'completed' ? 'bg-success' : 'bg-warning' }}">{{ $order->status }}</span></li>
                            <li class="list-group-item border-0 ps-0">Tipo: {{ $order->type ?? 'N/A' }}</li>
                            <li class="list-group-item border-0 ps-0">Referencia ID: {{ $order->reference_id ?? 'N/A' }}</li>
                            <li class="list-group-item border-0 ps-0">Actualizada: {{ $order->updated_at ? $order->updated_at->format('d/m/Y H:i') : 'N/A' }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <!-- Paginador -->
    <div class="d-flex justify-content-center mt-4">
        {{ $orders->links() }}
    </div>
</div>
                </div>
            </div>
        </div>
    </div>

    <script>
 
    document.addEventListener("DOMContentLoaded", function() {
        // Inicializar Flatpickr
        flatpickr(".flatpickr", {
            dateFormat: "Y-m-d",
        });

        // Gráfico de ingresos
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Pagos por Partidos', 'Pagos por Bono', 'Pagos por Reservas', 'Completados', 'Pendientes'],
                datasets: [{
                    label: 'Ingresos',
                    data: [
                        {{ $totalMatchPayments }}, // Total de partidos
                        {{ $totalBonusPayments }}, // Total de bonos
                        {{ $totalBookingPayments }}, // Total de reservas
                        {{ $totalCompleted }}, // Total completados
                        {{ $totalPending }} // Total pendientes
                    ],
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(153, 102, 255, 0.2)',
                        'rgba(255, 159, 64, 0.2)',
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)'
                    ],
                    borderColor: [
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    });

    </script>
@endsection