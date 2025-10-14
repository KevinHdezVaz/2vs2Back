@extends('layouts.user_type.auth')
@section('content')

<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header pb-0 px-3">
            <h6 class="mb-0">Nuevo Partido Diario</h6>
        </div>
        
        <div class="card-body pt-4 p-3">
            {{-- Mensajes de error, éxito y advertencia --}}
            @if ($errors->any())
                <div class="alert alert-danger text-white" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger text-white" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning text-white" role="alert">
                    {{ session('warning') }}
                </div>
            @endif

            @if(session('success'))
                <div class="alert alert-success text-white" role="alert">
                    {{ session('success') }}
                </div>
            @endif

            <form action="{{ route('daily-matches.store') }}" method="POST">
                @csrf
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="week_selection">Seleccionar Semana</label>
                            <select name="week_selection" id="week_selection" class="form-control @error('week_selection') is-invalid @enderror" required>
                                <option value="current">Esta semana ({{ now()->startOfWeek()->format('d/m/Y') }} - {{ now()->endOfWeek()->format('d/m/Y') }})</option>
                                <option value="next">Próxima semana ({{ now()->addWeek()->startOfWeek()->format('d/m/Y') }} - {{ now()->addWeek()->endOfWeek()->format('d/m/Y') }})</option>
                            </select>
                            @error('week_selection')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name">Nombre del Partido</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name') }}" required 
                                   placeholder="ej: Partido Matutino">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="field_id">Cancha</label>
                            <select name="field_id" class="form-control @error('field_id') is-invalid @enderror" required>
                                <option value="">Seleccionar cancha...</option>
                                @foreach($fields as $field)
                                    <option value="{{ $field->id }}">{{ $field->name }}</option>
                                @endforeach
                            </select>
                            @error('field_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="game_type">Tipo de Partido</label>
                            <select name="game_type" id="game_type" class="form-control @error('game_type') is-invalid @enderror" required>
                                <option value="">Seleccionar tipo...</option>
                                <option value="fut5" {{ old('game_type') === 'fut5' ? 'selected' : '' }}>Fútbol 5</option>
                                <option value="fut7" {{ old('game_type') === 'fut7' ? 'selected' : '' }}>Fútbol 7</option>
                                <option value="fut11" {{ old('game_type') === 'fut11' ? 'selected' : '' }}>Fútbol 11</option>
                            </select>
                            @error('game_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="price">Precio por Jugador</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="price" step="0.01" 
                                       class="form-control @error('price') is-invalid @enderror"
                                       value="{{ old('price') }}" required>
                            </div>
                            @error('price')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-group mt-4">
                    <label>Días y Horarios Disponibles</label>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width: 15%">Día</th>
                                    <th style="width: 15%">Activo</th>
                                    <th>Horarios Disponibles</th>
                                </tr>
                            </thead>
                            <tbody>
                            @php
                                $days = [
                                    'domingo' => 'Domingo',
                                    'lunes' => 'Lunes',
                                    'martes' => 'Martes',
                                    'miercoles' => 'Miércoles',
                                    'jueves' => 'Jueves',
                                    'viernes' => 'Viernes',
                                    'sabado' => 'Sábado'
                                ];
                                $hours = [];
                                for($i = 10; $i <= 19; $i++) {
                                    $hours[] = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
                                }
                            @endphp

                            @foreach($days as $dayKey => $dayName)
                                <tr class="day-row">
                                    <td>{{ $dayName }}</td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input day-toggle" 
                                                   type="checkbox" 
                                                   data-day="{{ $dayKey }}">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="hours-container" id="hours-{{ $dayKey }}" style="display: none">
                                            @foreach($hours as $hour)
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input hour-checkbox" 
                                                           type="checkbox" 
                                                           name="days[{{ $dayKey }}][hours][]" 
                                                           value="{{ $hour }}"
                                                           disabled
                                                           id="{{ $dayKey }}-{{ $hour }}">
                                                    <label class="form-check-label" for="{{ $dayKey }}-{{ $hour }}">
                                                        {{ $hour }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <a href="{{ route('daily-matches.index') }}" class="btn btn-light m-0">Cancelar</a>
                    <button type="submit" class="btn bg-gradient-primary m-0 ms-2">Crear Partido</button>
                </div>

                <!-- Resumen de la selección -->
                <div class="mt-4">
                    <h6 class="mb-2">Resumen de la Selección</h6>
                    <div id="summary" class="alert alert-info text-white" role="alert">
                        <p class="mb-0">Por favor, completa el formulario para ver el resumen.</p>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const weekSelector = document.getElementById('week_selection');
    const nameInput = document.querySelector('input[name="name"]');
    const fieldSelect = document.querySelector('select[name="field_id"]');
    const gameTypeSelect = document.querySelector('select[name="game_type"]');
    const priceInput = document.querySelector('input[name="price"]');
    const dayToggles = document.querySelectorAll('.day-toggle');
    const summaryDiv = document.getElementById('summary');

    if (!weekSelector) {
        console.error('Elemento week_selection no encontrado');
        return;
    }
    if (!summaryDiv) {
        console.error('Elemento summary no encontrado');
        return;
    }

    const dayMap = {
        'domingo': 0, 'lunes': 1, 'martes': 2, 'miercoles': 3,
        'jueves': 4, 'viernes': 5, 'sabado': 6
    };

    function formatDate(date) {
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0'); // +1 porque getMonth() empieza en 0
        return `${day}/${month}`;
    }

    function updateDaysAvailability() {
        const isNextWeek = weekSelector.value === 'next';
        const now = new Date();
        const currentDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const currentHour = now.getHours();

        dayToggles.forEach(toggle => {
            const dayName = toggle.dataset.day;
            let dayDate = new Date(now);
            dayDate.setDate(dayDate.getDate() - dayDate.getDay()); // Ir al inicio de la semana (domingo)
            dayDate.setDate(dayDate.getDate() + dayMap[dayName]); // Avanzar al día correspondiente

            if (isNextWeek) {
                dayDate.setDate(dayDate.getDate() + 7);
                toggle.disabled = false;
            } else {
                const dayDateWithoutTime = new Date(dayDate.getFullYear(), dayDate.getMonth(), dayDate.getDate());
                toggle.disabled = dayDateWithoutTime < currentDate;
            }

            if (toggle.disabled) {
                toggle.checked = false;
                const hoursContainer = document.getElementById(`hours-${dayName}`);
                if (hoursContainer) hoursContainer.style.display = 'none';
            } else {
                const hoursContainer = document.getElementById(`hours-${dayName}`);
                if (hoursContainer) {
                    const hourCheckboxes = hoursContainer.querySelectorAll('.hour-checkbox');
                    const dayDateWithoutTime = new Date(dayDate.getFullYear(), dayDate.getMonth(), dayDate.getDate());
                    const isToday = dayDateWithoutTime.getTime() === currentDate.getTime();

                    hourCheckboxes.forEach(checkbox => {
                        const hour = parseInt(checkbox.value.split(':')[0]);
                        checkbox.disabled = isToday && hour < currentHour;
                        if (checkbox.disabled) checkbox.checked = false;
                    });
                }
            }
        });
        updateSummary();
    }

    function updateSummary() {
        let summaryHtml = '<p class="mb-0">';
        const weekText = weekSelector.value === 'current' ? 'Esta semana' : 'Próxima semana';
        const name = nameInput ? nameInput.value || 'No especificado' : 'No especificado';
        const field = fieldSelect && fieldSelect.selectedIndex >= 0 ? fieldSelect.options[fieldSelect.selectedIndex].text : 'No seleccionada';
        const gameType = gameTypeSelect && gameTypeSelect.selectedIndex >= 0 ? gameTypeSelect.options[gameTypeSelect.selectedIndex].text : 'No seleccionado';
        const price = priceInput && priceInput.value ? `$${priceInput.value}` : 'No especificado';

        summaryHtml += `<strong>Semana:</strong> ${weekText}<br>`;
        summaryHtml += `<strong>Nombre del Partido:</strong> ${name}<br>`;
        summaryHtml += `<strong>Cancha:</strong> ${field}<br>`;
        summaryHtml += `<strong>Tipo de Partido:</strong> ${gameType}<br>`;
        summaryHtml += `<strong>Precio por Jugador:</strong> ${price}<br>`;

        let selectedDays = [];
        const now = new Date();
        const isNextWeek = weekSelector.value === 'next';
        dayToggles.forEach(toggle => {
            if (toggle.checked) {
                const dayName = toggle.dataset.day;
                let dayDate = new Date(now);
                dayDate.setDate(dayDate.getDate() - dayDate.getDay() + dayMap[dayName]);
                if (isNextWeek) dayDate.setDate(dayDate.getDate() + 7);

                const formattedDate = formatDate(dayDate);
                const hoursContainer = document.getElementById(`hours-${dayName}`);
                if (hoursContainer) {
                    const selectedHours = Array.from(hoursContainer.querySelectorAll('.hour-checkbox:checked'))
                        .map(checkbox => checkbox.value);
                    if (selectedHours.length > 0) {
                        selectedDays.push(`<strong>${formattedDate}:</strong> ${selectedHours.join(', ')}`);
                    }
                }
            }
        });

        summaryHtml += `<strong>Días y Horarios:</strong> ${selectedDays.length > 0 ? selectedDays.join('<br>') : 'Ningún día seleccionado'}`;
        summaryHtml += '</p>';
        summaryDiv.innerHTML = summaryHtml;
    }

    dayToggles.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const day = this.dataset.day;
            const hoursContainer = document.getElementById(`hours-${day}`);
            if (!hoursContainer) return;

            const checkboxes = hoursContainer.querySelectorAll('.hour-checkbox');
            const now = new Date();
            const currentDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const currentHour = now.getHours();
            const dayDate = new Date(now);
            dayDate.setDate(dayDate.getDate() - dayDate.getDay() + dayMap[day]);
            const isToday = dayDate.getTime() === currentDate.getTime();

            if (this.checked) {
                hoursContainer.style.display = 'block';
                checkboxes.forEach(checkbox => {
                    const hour = parseInt(checkbox.value.split(':')[0]);
                    checkbox.disabled = isToday && hour < currentHour;
                });
            } else {
                hoursContainer.style.display = 'none';
                checkboxes.forEach(checkbox => {
                    checkbox.disabled = true;
                    checkbox.checked = false;
                });
            }
            updateSummary();
        });
    });

    weekSelector.addEventListener('change', () => { updateDaysAvailability(); updateSummary(); });
    if (nameInput) nameInput.addEventListener('input', updateSummary);
    if (fieldSelect) fieldSelect.addEventListener('change', updateSummary);
    if (gameTypeSelect) gameTypeSelect.addEventListener('change', updateSummary);
    if (priceInput) priceInput.addEventListener('input', updateSummary);
    document.querySelectorAll('.hour-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSummary);
    });

    updateDaysAvailability();
});
</script>

<style>
.hours-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.form-check-inline {
    margin-right: 15px;
    background-color: #f8f9fa;
    padding: 5px 10px;
    border-radius: 5px;
}

.hour-checkbox:disabled + label {
    color: #999;
}

.form-check-input:checked + label {
    font-weight: bold;
    color: #2196F3;
}
</style>
@endsection