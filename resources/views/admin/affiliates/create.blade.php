@extends('layouts.user_type.auth')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header pb-0 px-3">
            <h6 class="mb-0">Crear Nuevo Afiliado</h6>
        </div>
        <div class="card-body pt-4 p-3">
            {{-- Muestra errores de validación si los hay --}}
            @if ($errors->any())
                <div class="alert alert-danger text-white" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('affiliates.store') }}">
                @csrf
                <div class="form-group mb-3">
                    <label for="name">Nombre del Afiliado (Gimnasio, Influencer, etc.)</label>
                    <input type="text" id="name" name="name"
                           class="form-control"
                           value="{{ old('name') }}" required>
                </div>

                <div class="form-group mb-3">
                    <label for="contact_email">Email de Contacto (Opcional)</label>
                    <input type="email" id="contact_email" name="contact_email"
                           class="form-control"
                           value="{{ old('contact_email') }}">
                </div>

                <div class="form-group mb-3">
    <label for="referral_code">Código de Afiliado (Ej: KEVIN10, GYMDESCUENTO)</label>
    <input type="text" id="referral_code" name="referral_code"
           class="form-control"
           value="{{ old('referral_code') }}" required>
</div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="discount_percentage">Porcentaje de Descuento para el Cliente (%)</label>
                            <input type="number" step="0.01" id="discount_percentage" name="discount_percentage"
                                   class="form-control"
                                   value="{{ old('discount_percentage') }}" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="commission_rate">Porcentaje de Comisión para el Afiliado (%)</label>
                            <input type="number" step="0.01" id="commission_rate" name="commission_rate"
                                   class="form-control"
                                   value="{{ old('commission_rate') }}" required>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <a href="{{ route('affiliates.index') }}" class="btn btn-light m-0">Cancelar</a>
                    <button type="submit" class="btn bg-gradient-primary m-0 ms-2">Crear Afiliado</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection