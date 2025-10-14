@extends('layouts.user_type.auth')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
      <div class="card-header pb-0 px-3">
        <h6 class="mb-0">Reporte de Referidos</h6>
      </div>
      <div class="card-body pt-4 p-3">
        <div class="table-responsive p-0">
          <table class="table align-items-center mb-0">
            <thead>
              <tr>
                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Afiliado</th>
                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Nuevo Usuario</th>
                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Monto Venta</th>
                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Comisión Ganada</th>
                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Fecha</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($referrals as $referral)
              <tr>
                <td>
                  <p class="text-sm font-weight-bold mb-0 px-3">{{ $referral->affiliate->name ?? 'N/A' }}</p>
                </td>
                <td>
                  <p class="text-sm mb-0">{{ $referral->newUser->email ?? 'N/A' }}</p>
                </td>
                <td class="align-middle text-center text-sm">
                  <span class="badge badge-sm bg-gradient-success">${{ number_format($referral->sale_amount, 2) }}</span>
                </td>
                <td class="align-middle text-center text-sm">
                    <span class="badge badge-sm bg-gradient-info">${{ number_format($referral->commission_earned, 2) }}</span>
                </td>
                <td class="align-middle text-center">
                  <span class="text-secondary text-xs font-weight-bold">{{ $referral->created_at->format('d/m/Y') }}</span>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="5" class="text-center py-4">Aún no se ha registrado ninguna venta por referidos.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
</div>
@endsection