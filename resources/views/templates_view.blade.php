<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $filename }} - Game Templates</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/github-dark.min.css">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Open Sans', sans-serif;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .card-header {
            background: white;
            border-bottom: 1px solid #e9ecef;
            border-radius: 10px 10px 0 0 !important;
            padding: 1.5rem;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
        }
        .stat-card h4 {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .stat-card p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .block-card {
            border-left: 4px solid #667eea;
            background: #f8f9fa;
        }
        pre {
            background: #1e1e1e;
            border-radius: 8px;
            padding: 1.5rem;
            max-height: 600px;
            overflow-y: auto;
        }
        code {
            font-size: 0.875rem;
            font-family: 'Courier New', monospace;
        }
        .badge-custom {
            padding: 0.5em 1em;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('templates.index') }}">
                <i class="fas fa-arrow-left me-2"></i>
                Volver al Panel
            </a>
            <div class="d-flex">
                <a href="{{ route('templates.download', $filename) }}" class="btn btn-light btn-sm me-2">
                    <i class="fas fa-download me-1"></i> Descargar
                </a>
                <a href="{{ route('templates.logout') }}" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i> Cerrar Sesión
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <!-- Header -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1">
                            <i class="fas fa-file-code text-primary me-2"></i>
                            {{ $filename }}
                        </h4>
                        <div>
                            @if($is_valid)
                                <span class="badge bg-success badge-custom">
                                    <i class="fas fa-check-circle me-1"></i>JSON Válido
                                </span>
                            @else
                                <span class="badge bg-danger badge-custom">
                                    <i class="fas fa-exclamation-triangle me-1"></i>JSON Inválido
                                </span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <a href="{{ route('templates.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver
                        </a>
                        <a href="{{ route('templates.download', $filename) }}" class="btn btn-primary">
                            <i class="fas fa-download me-2"></i>Descargar
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @if($is_valid && $data)
            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <h4>{{ $data['courts'] ?? 'N/A' }}</h4>
                        <p><i class="fas fa-volleyball-ball me-1"></i>Canchas</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <h4>{{ $data['players'] ?? 'N/A' }}</h4>
                        <p><i class="fas fa-users me-1"></i>Jugadores</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <h4>{{ isset($data['blocks']) ? count($data['blocks']) : 'N/A' }}</h4>
                        <p><i class="fas fa-layer-group me-1"></i>Bloques</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <h4>{{ $data['type'] ?? 'N/A' }}</h4>
                        <p><i class="fas fa-tag me-1"></i>Tipo</p>
                    </div>
                </div>
            </div>

            <!-- Estructura de Bloques -->
            @if(isset($data['blocks']) && is_array($data['blocks']))
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-sitemap me-2 text-primary"></i>
                            Estructura de Bloques
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($data['blocks'] as $index => $block)
                                <div class="col-md-6 mb-3">
                                    <div class="card block-card">
                                        <div class="card-body">
                                            <h6 class="text-primary mb-3">
                                                <i class="fas fa-cube me-2"></i>
                                                {{ $block['label'] ?? "Bloque " . ($index + 1) }}
                                            </h6>
                                            
                                            @if(isset($block['rounds']) && is_array($block['rounds']))
                                                <div class="mb-2">
                                                    <strong>
                                                        <i class="fas fa-sync-alt me-1"></i>
                                                        Rondas:
                                                    </strong>
                                                    <span class="badge bg-secondary">{{ count($block['rounds']) }}</span>
                                                </div>
                                                
                                                @php
                                                    $totalGames = 0;
                                                    foreach($block['rounds'] as $round) {
                                                        if(isset($round['courts'])) {
                                                            $totalGames += count($round['courts']);
                                                        }
                                                    }
                                                @endphp
                                                
                                                <div class="mb-2">
                                                    <strong>
                                                        <i class="fas fa-gamepad me-1"></i>
                                                        Total de Juegos:
                                                    </strong>
                                                    <span class="badge bg-info">{{ $totalGames }}</span>
                                                </div>

                                                <hr>
                                                
                                                @foreach($block['rounds'] as $roundIndex => $round)
                                                    <div class="mb-2">
                                                        <small class="text-muted">
                                                            <i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i>
                                                            Ronda {{ $roundIndex + 1 }}
                                                            @if(isset($round['courts']))
                                                                - {{ count($round['courts']) }} partido(s)
                                                            @endif
                                                        </small>
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @endif

        <!-- JSON Completo -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-code me-2 text-primary"></i>
                    Contenido JSON Completo
                </h5>
            </div>
            <div class="card-body p-0">
                <pre class="mb-0"><code class="language-json">{{ $content }}</code></pre>
            </div>
        </div>

        <!-- Información Adicional -->
        <div class="card">
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <i class="fas fa-file-alt fa-2x text-primary mb-2"></i>
                        <p class="mb-0 text-muted small">Archivo JSON</p>
                    </div>
                    <div class="col-md-4">
                        <i class="fas fa-database fa-2x text-success mb-2"></i>
                        <p class="mb-0 text-muted small">Estructura Validada</p>
                    </div>
                    <div class="col-md-4">
                        <i class="fas fa-shield-alt fa-2x text-info mb-2"></i>
                        <p class="mb-0 text-muted small">Formato Correcto</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            hljs.highlightAll();
        });
    </script>
</body>
</html>