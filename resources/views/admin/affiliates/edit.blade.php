@extends('layouts.user_type.auth')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header pb-0 px-3">
            <h6 class="mb-0">Editar Afiliado: {{ $affiliate->name }}</h6>
        </div>
        <div class="card-body pt-4 p-3">
            @if ($errors->any())
                <div class="alert alert-danger text-white" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('affiliates.update', $affiliate->id) }}">
                @csrf
                @method('PUT') {{-- Importante para la actualización --}}

                <div class="form-group mb-3">
                    <label for="name">Nombre del Afiliado</label>
                    <input type="text" id="name" name="name"
                           class="form-control"
                           value="{{ old('name', $affiliate->name) }}" required>
                </div>

                <div class="form-group mb-3">
                    <label for="contact_email">Email de Contacto</label>
                    <input type="email" id="contact_email" name="contact_email"
                           class="form-control"
                           value="{{ old('contact_email', $affiliate->contact_email) }}">
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="discount_percentage">Porcentaje de Descuento (%)</label>
                            <input type="number" step="0.01" id="discount_percentage" name="discount_percentage"
                                   class="form-control"
                                   value="{{ old('discount_percentage', $affiliate->discount_percentage) }}" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="commission_rate">Porcentaje de Comisión (%)</label>
                            <input type="number" step="0.01" id="commission_rate" name="commission_rate"
                                   class="form-control"
                                   value="{{ old('commission_rate', $affiliate->commission_rate) }}" required>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label for="status">Estado</label>
                    <select class="form-control" id="status" name="status">
                        <option value="active" {{ $affiliate->status == 'active' ? 'selected' : '' }}>Activo</option>
                        <option value="inactive" {{ $affiliate->status == 'inactive' ? 'selected' : '' }}>Inactivo</option>
                    </select>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <a href="{{ route('affiliates.index') }}" class="btn btn-light m-0">Cancelar</a>
                    <button type="submit" class="btn bg-gradient-primary m-0 ms-2">Actualizar Afiliado</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection