<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request; // Asegúrate de importar Request
use App\Http\Controllers\Controller;

class UserController extends Controller
{
  // En app/Http/Controllers/Admin/UserController.php

public function index(Request $request)
{
    // Inicia la consulta
    $query = User::query();

    // Aplica el filtro de búsqueda si el campo 'search' no está vacío
    if ($request->filled('search')) {
        $search = $request->input('search');
        $query->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
    }

    // ▼▼▼ AÑADE ESTA LÍNEA AQUÍ ▼▼▼
    // Ordena los resultados por fecha de creación, del más nuevo al más viejo.
    $query->latest();
    // ▲▲▲ FIN DEL CAMBIO ▲▲▲

    // Obtiene los usuarios con paginación (50 por página)
    $users = $query->paginate(50);

    // Devuelve la vista y le pasa la variable $users paginada
    return view('admin.users.index', ['users' => $users]);
}
    
    // Aquí irían los demás métodos: create, store, edit, update, destroy...
}