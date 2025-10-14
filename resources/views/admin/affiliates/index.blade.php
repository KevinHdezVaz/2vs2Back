@extends('layouts.user_type.auth')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
      <div class="card-header pb-0 px-3">
        <div class="d-flex justify-content-between align-items-center">
          <h6 class="mb-0">Gestión de Afiliados</h6>
          <a href="{{ route('affiliates.create') }}" class="btn bg-gradient-primary btn-sm mb-0">
            +&nbsp; Crear Nuevo Afiliado
          </a>
        </div>
      </div>
      <div class="card-body pt-4 p-3">
        <div class="table-responsive p-0">
          <table class="table align-items-center mb-0">
            <thead>
              <tr>
                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Nombre del Afiliado</th>
                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Código</th>
                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Descuento</th>
                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Comisión</th>
                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Estado</th>
                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($affiliates as $affiliate)
              <tr>
                <td>
                  <div class="d-flex px-2 py-1">
                    <div class="d-flex flex-column justify-content-center">
                      <h6 class="mb-0 text-sm">{{ $affiliate->name }}</h6>
                      <p class="text-xs text-secondary mb-0">{{ $affiliate->contact_email }}</p>
                    </div>
                  </div>
                </td>
                <td>
                  <p class="text-sm font-weight-bold mb-0">{{ $affiliate->referral_code }}</p>
                </td>
                <td class="align-middle text-center text-sm">
                  <span class="badge badge-sm bg-gradient-success">{{ $affiliate->discount_percentage }}%</span>
                </td>
                <td class="align-middle text-center text-sm">
                    <span class="badge badge-sm bg-gradient-info">{{ $affiliate->commission_rate }}%</span>
                </td>
                <td class="align-middle text-center">
                  <span class="text-secondary text-xs font-weight-bold">{{ $affiliate->status }}</span>
                </td>
                <td class="align-middle text-center">
                  <!-- ▼▼▼ INICIO DEL CAMBIO ▼▼▼ -->
                  <a href="{{ route('affiliates.edit', $affiliate->id) }}" class="btn btn-link text-secondary font-weight-bold text-xs p-0">
                    Editar
                  </a>
                  <form action="{{ route('affiliates.destroy', $affiliate->id) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-link text-danger font-weight-bold text-xs p-0 ms-2" onclick="return confirm('¿Estás seguro de que quieres eliminar a este afiliado?')">
                      Eliminar
                    </button>
                  </form>
                  <!-- ▲▲▲ FIN DEL CAMBIO ▲▲▲ -->
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="6" class="text-center py-4">No hay afiliados creados todavía.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
</div>
@endsection