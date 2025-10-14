<!-- resources/views/admin/verifications/index.blade.php -->

@extends('layouts.user_type.auth')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header pb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6>Solicitudes de Verificación de DNI</h6>
                    </div>
                </div>
                <div class="card-body px-0 pt-0 pb-2">
                    @if(session('success'))
                        <div class="alert alert-success text-white mx-4">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="table-responsive p-0">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Estado</th>
                                    <th>Fecha de Solicitud</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($verifications as $verification)
                                    <tr>
                                        <td class="ps-4">{{ $verification->user->name }}</td>
                                        <td>
                                            @if($verification->status === 'pending')
                                                <span class="badge badge-sm bg-gradient-warning">Pendiente</span>
                                            @elseif($verification->status === 'approved')
                                                <span class="badge badge-sm bg-gradient-success">Aprobado</span>
                                            @else
                                                <span class="badge badge-sm bg-gradient-danger">Rechazado</span>
                                            @endif
                                        </td>
                                        <td>{{ $verification->created_at->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <a href="{{ route('admin.verifications.show', $verification->id) }}" 
                                               class="btn btn-link text-info text-gradient px-3 mb-0">
                                                <i class="fas fa-eye me-2"></i> Ver Detalles
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection