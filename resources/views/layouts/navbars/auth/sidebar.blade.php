<aside class="sidenav navbar navbar-vertical navbar-expand-xs border-0 fixed-start ms-3"
       style="position: fixed; top: 0; left: 0; bottom: 0; width: 250px; background-color: white;">
  <div class="sidenav-header">
    <i class="fas fa-times p-3 cursor-pointer text-secondary opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
    <a class="align-items-center d-flex m-0 navbar-brand text-wrap" href="{{ route('dashboard') }}">
        <img src="../assets/img/logo-ct.png" class="navbar-brand-img h-100" alt="Logo Frutia"> <span class="ms-3 font-weight-bold">Frutia Admin</span> </a>
  </div>
  <hr class="horizontal dark mt-0">
  <div class="w-auto h-100" id="sidenav-collapse-main">
    <ul class="navbar-nav">

      <li class="nav-item">
        <a class="nav-link {{ (Request::is('dashboard') ? 'active' : '') }}" href="{{ route('dashboard') }}">
          <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
            <i style="font-size: 1rem;" class="fas fa-lg fa-tachometer-alt ps-2 pe-2 text-center {{ (Request::is('dashboard') ? 'text-white' : 'text-dark') }}" aria-hidden="true"></i>
          </div>
          <span class="nav-link-text ms-1">Dashboard</span>
        </a>
      </li>

      <li class="nav-item mt-2">
        <h6 class="ps-4 ms-2 text-uppercase text-xs font-weight-bolder opacity-6">Gestión</h6>
      </li>

      {{-- Dentro de <ul class="navbar-nav"> --}}

<li class="nav-item">
  <a class="nav-link {{ (Request::is('planes*') ? 'active' : '') }}" href="{{ route('planes.index') }}">
    <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
      <i style="font-size: 1rem;" class="fas fa-lg fa-dollar-sign ps-2 pe-2 text-center {{ (Request::is('planes*') ? 'text-white' : 'text-dark') }}" aria-hidden="true"></i>
    </div>
    <span class="nav-link-text ms-1">Planes y Precios</span>
  </a>
</li>

      <li class="nav-item">
        {{-- CORRECCIÓN AQUÍ: Cambiado de 'users.index' a 'usuarios.index' --}}
        <a class="nav-link {{ (Request::is('usuarios*') ? 'active' : '') }}" href="{{ route('usuarios.index') }}">
          <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
            <i style="font-size: 1rem;" class="fas fa-lg fa-users ps-2 pe-2 text-center {{ (Request::is('usuarios*') ? 'text-white' : 'text-dark') }}" aria-hidden="true"></i>
          </div>
          <span class="nav-link-text ms-1">Usuarios</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link {{ (Request::is('affiliates*') ? 'active' : '') }}" href="{{ route('affiliates.index') }}">
          <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
            <i style="font-size: 1rem;" class="fas fa-lg fa-handshake ps-2 pe-2 text-center {{ (Request::is('affiliates*') ? 'text-white' : 'text-dark') }}" aria-hidden="true"></i>
          </div>
          <span class="nav-link-text ms-1">Afiliados</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link {{ (Request::is('referrals*') ? 'active' : '') }}" href="{{ route('referrals.index') }}">
          <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
            <i style="font-size: 1rem;" class="fas fa-lg fa-chart-line ps-2 pe-2 text-center {{ (Request::is('referrals*') ? 'text-white' : 'text-dark') }}" aria-hidden="true"></i>
          </div>
          <span class="nav-link-text ms-1">Reporte de Referidos</span>
        </a>
      </li>

    </ul>
  </div>
</aside>