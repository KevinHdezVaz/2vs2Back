@extends('layouts.user_type.auth')

@section('content')
<main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg ">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                @if(session('success'))
                    <div class="alert alert-success text-white" role="alert">
                        {{ session('success') }}
                    </div>
                @endif
                <div class="card">
                    <div class="card-header pb-0">
                        <h6>Planes y Precios</h6>
                    </div>
                    <div class="card-body px-0 pt-0 pb-2">
                        <div class="table-responsive p-0">
                            <table class="table align-items-center mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Nombre del Plan</th>
                                        <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Precio Actual (USD)</th>
                                        <th class="text-secondary opacity-7"></th>
                                    </tr>
                                </thead>
                                {{-- ... código anterior de la tabla ... --}}
<tbody>
    @foreach($plans as $plan)
    <tr>
        <td>
            <p class="text-sm font-weight-bold mb-0 px-3">{{ $plan->name }}</p>
        </td>
        <td class="align-middle text-center">
    <span class="badge bg-gradient-success fs-6">${{ number_format($plan->price, 2) }}</span>
</td>
        <td class="align-middle">
            {{-- Asegúrate de que esta línea esté así --}}
            <a href="{{ route('planes.edit', $plan) }}" class="text-secondary font-weight-bold text-xs">Editar</a>
        </td>
    </tr>
    @endforeach
</tbody>
{{-- ... resto del código ... --}}
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection