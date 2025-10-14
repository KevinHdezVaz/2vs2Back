@extends('layouts.user_type.auth')
@section('content')

<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header pb-0 px-3">
            <h6 class="mb-0">Nueva Notificación Push</h6>
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

            <form method="POST" action="{{ route('notifications.store') }}">
               @csrf

                <div class="form-group">
                    <label for="title">Título de la notificación</label>
                    <input type="text" id="title" name="title" 
                        class="form-control @error('title') is-invalid @enderror" 
                        value="{{ old('title') }}" required>
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="message">Mensaje</label>
                    <textarea id="message" name="message" 
                        class="form-control @error('message') is-invalid @enderror" 
                        rows="3" required>{{ old('message') }}</textarea>
                    @error('message')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <a href="{{ route('notifications.index') }}" class="btn btn-light m-0">Cancelar</a>
                    <button type="submit" class="btn bg-gradient-primary m-0 ms-2">Enviar Notificación</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
