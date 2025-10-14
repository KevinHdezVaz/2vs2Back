@extends('layouts.user_type.auth')

@section('content')

    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg ">
        <nav class="navbar navbar-main navbar-expand-lg px-0 mx-4 shadow-none border-radius-xl">
            <div class="container-fluid py-1 px-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
                        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="javascript:;">Gestión</a></li>
                        <li class="breadcrumb-item text-sm text-dark active" aria-current="page">Usuarios</li>
                    </ol>
                    <h6 class="font-weight-bolder mb-0">Lista de Usuarios</h6>
                </nav>
            </div>
        </nav>
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0">
                            <div class="d-flex align-items-center justify-content-between">
                                <h6 class="mb-0">Todos los Usuarios</h6>
                             </div>

                            <form action="{{ route('usuarios.index') }}" method="GET" class="mt-3">
                                <div class="input-group">
                                    <span class="input-group-text text-body"><i class="fas fa-search" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control" name="search" placeholder="Buscar por nombre o email..." value="{{ request('search') }}">
                                    <button type="submit" class="btn btn-primary mb-0">Buscar</button>
                                </div>
                            </form>
                        </div>

                        <div class="card-body px-0 pt-0 pb-2">
                            <div class="table-responsive p-0">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Usuario</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Suscripción</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Registrado</th>
                                         </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($users as $user)
                                        <tr>
                                            <td>
                                                <div class="d-flex px-3 py-1">
                                                    <div class="d-flex flex-column justify-content-center">
                                                        <h6 class="mb-0 text-sm">{{ $user->name }}</h6>
                                                        <p class="text-xs text-secondary mb-0">{{ $user->email }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="align-middle text-center text-sm">
                                                @if($user->subscription_status == 'active')
                                                    <span class="badge badge-sm bg-gradient-success">Activa</span>
                                                @elseif($user->subscription_status == 'trial')
                                                    <span class="badge badge-sm bg-gradient-info">Prueba</span>
                                                @else
                                                    <span class="badge badge-sm bg-gradient-danger">Cancelada</span>
                                                @endif
                                            </td>
                                            <td class="align-middle text-center">
                                                <span class="text-secondary text-xs font-weight-bold">{{ $user->created_at->format('d/m/Y') }}</span>
                                            </td>
                                             
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="4" class="text-center py-4">No se encontraron usuarios que coincidan con la búsqueda.</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="card-footer d-flex justify-content-center">
                            {{ $users->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection