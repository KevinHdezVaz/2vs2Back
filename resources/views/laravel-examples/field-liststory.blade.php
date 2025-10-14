@extends('layouts.user_type.auth')
@section('content')

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header pb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6>Historias</h6>
                        <a href="{{ route('admin.stories.create') }}" class="btn bg-gradient-primary">
                            Nueva Historia
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
                                    <th>Imagen</th>
                                    <th>Video</th>
                                    <th>Estado</th>
                                    <th>Expira</th>
                                    <th>Creado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stories as $story)
                                    <tr>
                                        <td class="ps-4">{{ $story->title }}</td>
                                      <!-- Modificar la columna de imagen -->
<td>
    @if($story->image_url)
        <img src="{{ asset('storage/' . $story->image_url) }}" 
             alt="Vista previa" 
             class="avatar avatar-sm me-3"
         >
    @else
        <i class="fas fa-image text-secondary"></i>
    @endif
</td>
                                        <td>
                                            @if($story->video_url)
                                                <i class="fas fa-video text-success"></i>
                                            @else
                                                <i class="fas fa-times text-secondary"></i>
                                            @endif
                                        </td>
                                        <td>
                                            @if($story->is_active && $story->expires_at->isFuture())
                                                <span class="badge badge-sm bg-gradient-success">Activa</span>
                                            @else
                                                <span class="badge badge-sm bg-gradient-secondary">Expirada</span>
                                            @endif
                                        </td>
                                        <td>{{ $story->expires_at->format('d/m/Y H:i') }}</td>
                                        <td>{{ $story->created_at->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <form action="{{ route('admin.stories.destroy', $story) }}" 
                                                  method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-link text-danger text-gradient p-0" 
                                                        onclick="return confirm('¿Está seguro de eliminar esta historia?')">
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