@extends('layouts.user_type.guest')

@section('content')

  <main class="main-content  mt-0">
    <section>
      <div class="page-header min-vh-100">
        <div class="container">
          <div class="row">
            {{-- Columna Izquierda para el Formulario --}}
            <div class="col-xl-5 col-lg-6 col-md-7 d-flex flex-column mx-lg-0 mx-auto">
              <div class="card card-plain">
                <div class="card-header pb-0 text-left bg-transparent">
                  <h4 class="font-weight-bolder">Bienvenido Administrador</h4>
                  <p class="mb-0">Ingresa tu email y contraseña para continuar</p>
                </div>
                <div class="card-body">
                  <form method="POST" action="{{ url('/login') }}">
                    @csrf
                    <label>Email</label>
                    <div class="mb-3">
                      <input type="email" class="form-control" name="email" id="email" placeholder="Email" value="prueba@gmail.com" aria-label="Email" aria-describedby="email-addon">
                      @error('email')
                        <p class="text-danger text-xs mt-2">{{ $message }}</p>
                      @enderror
                    </div>
                    <label>Password</label>
                    <div class="mb-3">
                      <input type="password" class="form-control" name="password" id="password" placeholder="Password" value="prueba" aria-label="Password" aria-describedby="password-addon">
                      @error('password')
                        <p class="text-danger text-xs mt-2">{{ $message }}</p>
                      @enderror
                    </div>
                    <div class="form-check form-switch">
                      <input class="form-check-input" type="checkbox" id="rememberMe" checked="">
                      <label class="form-check-label" for="rememberMe">Remember me</label>
                    </div>
                    <div class="text-center">
                      <button type="submit" class="btn btn-primary w-100 mt-4 mb-0">Entrar</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            {{-- Columna Derecha para la Imagen (Ahora corregida) --}}
            <div class="col-6 d-lg-flex d-none h-100 my-auto pe-0 position-absolute top-0 end-0 text-center justify-content-center flex-column">
              <div class="position-relative bg-gradient-primary h-100 m-3 px-7 border-radius-lg d-flex flex-column justify-content-center overflow-hidden" 
                   style="background-image: url('../assets/img/curved-images/fondoAppFrutia.webp'); background-size: cover;">
                <span class="mask bg-primary opacity-1"></span>
                <h4 class="mt-5 text-white font-weight-bolder position-relative">"Frutiapp"</h4>
              </div>
            </div>

          </div>
        </div>
      </div>
    </section>
  </main>

@endsection