<!-- resources/views/admin/verifications/show.blade.php -->

@extends('layouts.user_type.auth')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header pb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6>Detalles de la Solicitud de Verificación</h6>
                        <a href="{{ route('admin.verifications.index') }}" class="btn bg-gradient-secondary">
                            Volver a la Lista
                        </a>
                    </div>
                </div>
                <div class="card-body px-0 pt-0 pb-2">
                    @if(session('success'))
                        <div class="alert alert-success text-white mx-4">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="px-4">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Información del Usuario</h6>
                                <p><strong>Nombre:</strong> {{ $verification->user->name }}</p>
                                <p><strong>Email:</strong> {{ $verification->user->email }}</p>
                                <p><strong>Fecha de Solicitud:</strong> {{ $verification->created_at->format('d/m/Y H:i') }}</p>
                            </div>
                            <div class="col-md-6">
                                <h6>Estado de la Verificación</h6>
                                <p>
                                    @if($verification->status === 'pending')
                                        <span class="badge badge-sm bg-gradient-warning">Pendiente</span>
                                    @elseif($verification->status === 'approved')
                                        <span class="badge badge-sm bg-gradient-success">Aprobado</span>
                                    @else
                                        <span class="badge badge-sm bg-gradient-danger">Rechazado</span>
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h6>Imagen del DNI</h6>
                                <img src="{{ asset('storage/' . $verification->dni_image_path) }}" 
                                     alt="DNI" 
                                     class="img-fluid rounded">
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h6>Acciones</h6>
                                <form action="{{ route('admin.verifications.update', $verification->id) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <button type="submit" name="status" value="approved" class="btn bg-gradient-success me-2">
                                        <i class="fas fa-check me-2"></i> Aprobar
                                    </button>
                                    <button type="submit" name="status" value="rejected" class="btn bg-gradient-danger">
                                        <i class="fas fa-times me-2"></i> Rechazar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection