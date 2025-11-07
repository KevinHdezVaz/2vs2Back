<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Templates - Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        }
        .card-header {
            background: white;
            border-bottom: 1px solid #e9ecef;
            border-radius: 10px 10px 0 0 !important;
        }
        .table {
            margin-bottom: 0;
        }
        .badge {
            padding: 0.5em 0.75em;
        }
        .btn-sm {
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-file-code me-2"></i>
                Game Templates Manager
            </a>
            <div class="d-flex">
                <span class="navbar-text text-white me-3">
                    <i class="fas fa-user me-1"></i> admin
                </span>
                <a href="{{ route('templates.logout') }}" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i> Cerrar Sesión
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <!-- Alertas -->
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Card Principal -->
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-folder-open me-2 text-primary"></i>
                        Templates JSON
                    </h5>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-upload me-2"></i>Subir Template
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><i class="fas fa-file me-2"></i>Archivo</th>
                                <th><i class="fas fa-weight me-2"></i>Tamaño</th>
                                <th class="text-center"><i class="fas fa-check-circle me-2"></i>Estado</th>
                                <th class="text-center"><i class="fas fa-clock me-2"></i>Última Modificación</th>
                                <th class="text-center"><i class="fas fa-cog me-2"></i>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($templates as $template)
                                <tr>
                                    <td>
                                        <i class="fas fa-file-code text-primary me-2"></i>
                                        <strong>{{ $template['filename'] }}</strong>
                                    </td>
                                    <td>{{ $template['size'] }}</td>
                                    <td class="text-center">
                                        @if($template['is_valid'])
                                            <span class="badge bg-success">
                                                <i class="fas fa-check me-1"></i>Válido
                                            </span>
                                        @else
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times me-1"></i>Error
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <small class="text-muted">{{ $template['modified'] }}</small>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="{{ route('templates.view', $template['filename']) }}" 
                                               class="btn btn-outline-info" 
                                               title="Ver contenido">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button"
                                                    class="btn btn-outline-warning"
                                                    onclick="showRenameModal('{{ $template['filename'] }}')"
                                                    title="Renombrar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="{{ route('templates.download', $template['filename']) }}" 
                                               class="btn btn-outline-secondary"
                                               title="Descargar">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-outline-danger" 
                                                    onclick="confirmDelete('{{ $template['filename'] }}')"
                                                    title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <p class="text-muted mb-0">No hay templates disponibles</p>
                                        <small class="text-muted">Sube tu primer template usando el botón de arriba</small>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Card de Información -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2 text-info"></i>
                    Información de Templates
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">Formato de Nombre</h6>
                        <p class="mb-2"><code>[Canchas]C[Horas]H[Jugadores]P-[Tipo].json</code></p>
                        <p class="text-muted small mb-0">Ejemplo: <code>2C3H8P-R.json</code></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">Ubicación</h6>
                        <p class="mb-0"><code>storage/app/game_templates/</code></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para subir archivo -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('templates.upload') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-upload me-2"></i>
                            Subir Template JSON
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="template_file" class="form-label">Selecciona el archivo JSON</label>
                            <input type="file" 
                                   class="form-control @error('template_file') is-invalid @enderror" 
                                   id="template_file" 
                                   name="template_file" 
                                   accept=".json"
                                   required>
                            @error('template_file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Solo archivos .json (máximo 2MB)
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-2"></i>Subir Archivo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ✅ NUEVO: Modal para renombrar -->
    <div class="modal fade" id="renameModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="renameForm" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-edit me-2"></i>
                            Renombrar Template
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="old_name" class="form-label">Nombre Actual</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="old_name" 
                                   readonly
                                   disabled>
                        </div>
                        <div class="mb-3">
                            <label for="new_name" class="form-label">Nuevo Nombre</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="new_name" 
                                   name="new_name" 
                                   placeholder="Ejemplo: 2C3H8P-R.json"
                                   required>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Debe terminar en .json
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Renombrar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Form oculto para eliminar -->
    <form id="deleteForm" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(filename) {
            if (confirm(`¿Estás seguro de que deseas eliminar "${filename}"?\n\nEsta acción no se puede deshacer.`)) {
                const form = document.getElementById('deleteForm');
                form.action = `/templates/${filename}`;
                form.submit();
            }
        }

        // ✅ NUEVO: Función para mostrar modal de renombrar
        function showRenameModal(filename) {
            document.getElementById('old_name').value = filename;
            document.getElementById('new_name').value = filename;
            document.getElementById('renameForm').action = `/templates/${filename}/rename`;
            
            const modal = new bootstrap.Modal(document.getElementById('renameModal'));
            modal.show();
        }
    </script>
</body>
</html>