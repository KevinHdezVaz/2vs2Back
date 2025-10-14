@extends('layouts.user_type.auth')
@section('content')

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header pb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6>Historial de Notificaciones</h6>
                        <a href="{{ route('notifications.create') }}" class="btn bg-gradient-primary">
                            Nueva Notificación Push
                        </a>
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
                                    <th>Título</th>
                                    <th>Mensaje</th>
                                    <th>Fecha de envío</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($notifications as $notification)
                                    <tr>
                                        <td class="ps-4">{{ $notification->title }}</td>
                                        <td>{{ $notification->message }}</td>
                                        <td>{{ $notification->created_at->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <form action="{{ route('notifications.destroy', $notification) }}" 
                                                  method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-link text-danger text-gradient p-0" 
                                                        onclick="return confirm('¿Está seguro de eliminar esta notificación?')">
                                                    <i class="far fa-trash-alt"></i>
                                                </button>
                                            </form>
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