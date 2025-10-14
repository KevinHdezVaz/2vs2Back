<?php
namespace App\Http\Controllers\API;

use App\Models\Equipo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;

class EquipoController extends Controller
{
    public function index()
    {
        $userId = auth()->id();
        $equipos = Equipo::whereHas('miembros', function($query) use ($userId) {
            $query->where('user_id', $userId);
        })->with('miembros')->get();
        
        return response()->json($equipos);
    }
 
 public function store(Request $request)
{
    $request->validate([
        'nombre' => 'required|string|max:255',
        'color_uniforme' => 'required|string|max:255',
        'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
    ]);

    try {
        // Importante: DB::transaction debe retornar el resultado
        return DB::transaction(function () use ($request) {
            $logoPath = null;
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('equipos', 'public');
            }

            $equipo = Equipo::create([
                'nombre' => $request->nombre,
                'color_uniforme' => $request->color_uniforme,
                'logo' => $logoPath
            ]);

            $equipo->miembros()->attach(auth()->id(), [
                'rol' => 'capitan',
                'estado' => 'activo'
            ]);

            // Cargar los miembros después de crear la relación
            $equipo->load('miembros');

            return response()->json([
                'message' => 'Equipo creado exitosamente',
                'equipo' => $equipo
            ], 201);
        });
    } catch (\Exception $e) {
        \Log::error('Error al crear equipo: ' . $e->getMessage());
        \Log::error($e->getTraceAsString());
        
        return response()->json([
            'message' => 'Error al crear el equipo',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function getInvitacionesPendientesCount()
{
    $count = DB::table('equipo_usuarios')
        ->where('user_id', auth()->id())
        ->where('estado', 'pendiente')
        ->count();

    return response()->json(['count' => $count]);
}
public function unirseAEquipoAbierto(Request $request, Equipo $equipo)
{
    try {
        DB::beginTransaction();

        // Verificar si el usuario es capitán de algún equipo
        $esCapitan = DB::table('equipo_usuarios')
            ->where('user_id', $request->user_id)
            ->where('rol', 'capitan')
            ->where('estado', 'activo')
            ->exists();

        // Solo verificar si ya está en un equipo si NO es capitán
        if (!$esCapitan) {
            $usuarioEnEquipo = DB::table('equipo_usuarios')
                ->where('user_id', $request->user_id)
                ->where('estado', 'activo')
                ->exists();

            if ($usuarioEnEquipo) {
                return response()->json([
                    'message' => 'Ya perteneces a un equipo. Solo puedes estar en un equipo a la vez.'
                ], 400);
            }
        }

        // Resto del código...
        DB::commit();
        return response()->json(['message' => 'Te has unido al equipo exitosamente']);

    } catch (\Exception $e) {
        DB::rollback();
        return response()->json([
            'message' => 'Error al unirse al equipo',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function solicitarUnirseAEquipoPrivado(Equipo $equipo)
{
    try {
        // Verificar que el equipo sea privado
        if ($equipo->es_abierto) {
            return response()->json([
                'message' => 'Este es un equipo abierto'
            ], 400);
        }

        // Verificar si el usuario ya está en otro equipo
        $userInOtherTeam = DB::table('equipo_usuarios')
            ->where('user_id', auth()->id())
            ->where('estado', 'activo')
            ->exists();

        if ($userInOtherTeam) {
            return response()->json([
                'message' => 'Ya perteneces a otro equipo'
            ], 400);
        }

        // Verificar si ya tiene una solicitud pendiente
        $pendingSolicitud = $equipo->miembros()
            ->where('user_id', auth()->id())
            ->where('estado', 'pendiente')
            ->exists();

        if ($pendingSolicitud) {
            return response()->json([
                'message' => 'Ya tienes una solicitud pendiente para este equipo'
            ], 400);
        }

        // Crear solicitud
        $equipo->miembros()->attach(auth()->id(), [
            'rol' => 'miembro',
            'estado' => 'pendiente'
        ]);

        return response()->json([
            'message' => 'Solicitud enviada exitosamente'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al enviar la solicitud',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function equiposDisponibles($torneoId)
{
    try {
        $userId = auth()->id();
        \Log::info('Iniciando búsqueda de equipos', [
            'user_id' => $userId,
            'torneo_id' => $torneoId
        ]);

        // 1. Primero obtener el equipo privado donde el usuario es capitán
        $equipoPrivado = DB::table('equipos')
            ->join('equipo_usuarios', 'equipos.id', '=', 'equipo_usuarios.equipo_id')
            ->where('equipo_usuarios.user_id', $userId)
            ->where('equipo_usuarios.rol', 'capitan')
            ->where('equipo_usuarios.estado', 'activo')
            ->where('equipos.es_abierto', 0)  // Cambiar true por 1

            ->whereNotExists(function ($query) use ($torneoId) {
                $query->select(DB::raw(1))
                    ->from('torneo_equipos')
                    ->whereRaw('torneo_equipos.equipo_id = equipos.id')
                    ->where('torneo_equipos.torneo_id', $torneoId);
            })
            ->select('equipos.*');

        \Log::info('Equipo privado query', [
            'sql' => $equipoPrivado->toSql(),
            'bindings' => $equipoPrivado->getBindings()
        ]);

        // 2. Obtener equipos abiertos del torneo
        $equiposAbiertos = DB::table('equipos')
            ->join('torneo_equipos', 'equipos.id', '=', 'torneo_equipos.equipo_id')
             ->where('equipos.es_abierto', 1)  // Cambiar false por 0
            ->where('torneo_equipos.torneo_id', $torneoId)
            ->where('torneo_equipos.estado', 'aceptado')
            ->select('equipos.*');

        \Log::info('Equipos abiertos query', [
            'sql' => $equiposAbiertos->toSql(),
            'bindings' => $equiposAbiertos->getBindings()
        ]);

        // 3. Unir las consultas
        $equiposQuery = $equipoPrivado->union($equiposAbiertos);
        
        // 4. Obtener los resultados
        $equipos = $equiposQuery->get();

        \Log::info('Equipos encontrados', [
            'count' => $equipos->count(),
            'equipos' => $equipos->toArray()
        ]);

        // 5. Cargar los miembros de cada equipo
        $equiposConMiembros = [];
        foreach ($equipos as $equipo) {
            $miembros = DB::table('users')
                ->join('equipo_usuarios', 'users.id', '=', 'equipo_usuarios.user_id')
                ->where('equipo_usuarios.equipo_id', $equipo->id)
                ->where('equipo_usuarios.estado', 'activo')
                ->select(
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.phone',
                    'users.profile_image',
                    'users.verified',
                    'equipo_usuarios.rol',
                    'equipo_usuarios.estado',
                    'equipo_usuarios.posicion',
                    'equipo_usuarios.equipo_id',
                    'equipo_usuarios.user_id',
                    'equipo_usuarios.created_at',
                    'equipo_usuarios.updated_at'
                )
                ->get();

 
            $equipoData = (array) $equipo;
            $equipoData['es_abierto'] = $equipo->es_abierto;  // Quitar el cast a (bool)


             $equipoData['miembros'] = $miembros->map(function($miembro) {
                $pivotData = [
                    'equipo_id' => $miembro->equipo_id,
                    'user_id' => $miembro->user_id,
                    'rol' => $miembro->rol,
                    'estado' => $miembro->estado,
                    'posicion' => $miembro->posicion,
                    'created_at' => $miembro->created_at,
                    'updated_at' => $miembro->updated_at
                ];

                return [
                    'id' => $miembro->id,
                    'name' => $miembro->name,
                    'email' => $miembro->email,
                    'phone' => $miembro->phone,
                    'profile_image' => $miembro->profile_image,
                    'verified' => $miembro->verified,
                    'rol' => $miembro->rol,
                    'estado' => $miembro->estado,
                    'posicion' => $miembro->posicion,
                    'equipo_id' => $miembro->equipo_id,
                    'user_id' => $miembro->id,
                    'pivot' => $pivotData
                ];
            });
            
            $equiposConMiembros[] = $equipoData;
        }

        return response()->json($equiposConMiembros);

    } catch (\Exception $e) {
        \Log::error('Error en equiposDisponibles', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'message' => 'Error al obtener equipos',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function inscribirEquipoEnTorneo(Request $request, Equipo $equipo, $torneoId)
{
    try {
        // 1. Validaciones básicas
        if (!$equipo->esCapitan(auth()->user())) {
            return response()->json([
                'message' => 'Solo el capitán puede inscribir al equipo'
            ], 403);
        }

        $request->validate([
            'miembros' => 'required|array|min:5',
            'miembros.*.user_id' => 'required|exists:users,id',
            'miembros.*.posicion' => 'required|string'
        ]);

        DB::beginTransaction();

        // 2. Verificar que los miembros pertenezcan al equipo
        foreach ($request->miembros as $miembro) {
            $pertenece = $equipo->miembros()
                ->where('user_id', $miembro['user_id'])
                ->where('estado', 'activo')
                ->exists();

            if (!$pertenece) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Uno o más miembros no pertenecen al equipo'
                ], 400);
            }
        }

        // 3. Actualizar las posiciones de los miembros para el torneo
        foreach ($request->miembros as $miembro) {
            $equipo->miembros()
                ->where('user_id', $miembro['user_id'])
                ->update([
                    'posicion' => $miembro['posicion']
                ]);
        }

        // 4. Inscribir al equipo en el torneo
        $equipo->torneos()->attach($torneoId, [
            'estado' => 'aceptado',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        DB::commit();

        return response()->json([
            'message' => 'Equipo inscrito exitosamente en el torneo'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Error al inscribir equipo en torneo: ' . $e->getMessage());
        return response()->json([
            'message' => 'Error al inscribir el equipo',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function getInvitacionesPendientes()
{
    $userId = auth()->id();
    
    $equipos = Equipo::whereHas('miembros', function($query) use ($userId) {
        $query->where('user_id', $userId)
              ->where('estado', 'pendiente');
    })->with('miembros')->get();
    
    return response()->json($equipos);
}

public function rechazarInvitacion(Equipo $equipo)
{
    $equipo->miembros()->detach(auth()->id());
    
    return response()->json([
        'message' => 'Invitación rechazada exitosamente'
    ]);
}

 
public function invitarPorCodigo(Request $request, Equipo $equipo)
{
    $request->validate([
        'codigo' => 'required|string|size:8'
    ]);

    // Verificar si el usuario actual es capitán
    if (!$equipo->esCapitan(auth()->user())) {
        return response()->json([
            'message' => 'Solo el capitán puede invitar miembros'
        ], 403);
    }

    // Buscar usuario por código
    $userToInvite = User::where('invite_code', $request->codigo)->first();

    if (!$userToInvite) {
        return response()->json([
            'message' => 'Código de usuario inválido'
        ], 404);
    }

    // Verificar si ya es miembro del equipo
    if ($equipo->miembros()->where('user_id', $userToInvite->id)->exists()) {
        return response()->json([
            'message' => 'El usuario ya es miembro del equipo'
        ], 400);
    }

    // Verificar si el usuario ya está en otro equipo
    $userInOtherTeam = DB::table('equipo_usuarios')
        ->where('user_id', $userToInvite->id)
        ->where('estado', 'activo')
        ->exists();

    if ($userInOtherTeam) {
        return response()->json([
            'message' => 'El usuario ya pertenece a otro equipo'
        ], 400);
    }

    try {
        // Agregar al usuario como miembro pendiente
        $equipo->miembros()->attach($userToInvite->id, [
            'rol' => 'miembro',
            'estado' => 'pendiente'
        ]);

        return response()->json([
            'message' => 'Invitación enviada exitosamente'
        ]);
    } catch (\Exception $e) {
        \Log::error('Error al invitar usuario: ' . $e->getMessage());
        return response()->json([
            'message' => 'Error al procesar la invitación'
        ], 500);
    }
}



public function inscribirseATorneo(Request $request, Equipo $equipo, Torneo $torneo)
{
    // 1. Validaciones de Usuario
    if (!$equipo->esCapitan(auth()->user())) {
        return response()->json([
            'message' => 'Solo el capitán puede inscribir al equipo'
        ], 403);
    }

    // 2. Validaciones del Torneo
    if ($torneo->estado !== 'abierto') {
        return response()->json([
            'message' => 'El torneo no está abierto para inscripciones'
        ], 400);
    }

    if ($torneo->equipos()->count() >= $torneo->maximo_equipos) {
        return response()->json([
            'message' => 'El torneo ya alcanzó el máximo de equipos'
        ], 400);
    }

    // 3. Validaciones del Equipo
    if ($equipo->torneos()->where('torneo_id', $torneo->id)->exists()) {
        return response()->json([
            'message' => 'El equipo ya está inscrito en este torneo'
        ], 400);
    }

    if ($equipo->miembros()->where('estado', 'activo')->count() < $torneo->minimo_jugadores) {
        return response()->json([
            'message' => 'El equipo no cumple con el mínimo de jugadores requerido'
        ], 400);
    }

    // 4. Validación de Pago
    if ($torneo->cuota_inscripcion > 0 && !$request->hasFile('comprobante')) {
        return response()->json([
            'message' => 'Debe subir el comprobante de pago'
        ], 400);
    }

    try {
        $comprobantePath = null;
        if ($request->hasFile('comprobante')) {
            $comprobantePath = $request->file('comprobante')
                ->store('comprobantes/torneos', 'public');
        }

        $equipo->torneos()->attach($torneo->id, [
            'estado' => 'pendiente',
            'pago_confirmado' => false,
            'comprobante' => $comprobantePath,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'message' => 'Inscripción realizada exitosamente'
        ]);

    } catch (\Exception $e) {
        \Log::error('Error al inscribir equipo: ' . $e->getMessage());
        return response()->json([
            'message' => 'Error al procesar la inscripción'
        ], 500);
    }
}


public function invitarMiembro(Request $request, Equipo $equipo)
{
    $request->validate([
        'user_id' => 'required|exists:users,id'
    ]);

    if (!$equipo->esCapitan(auth()->user())) {
        return response()->json(['error' => 'No autorizado'], 403);
    }

    try {
        // Verificar si el usuario ya está en algún equipo
        $userInOtherTeam = DB::table('equipo_usuarios')
            ->where('user_id', $request->user_id)
            ->where('estado', 'activo')
            ->exists();

        if ($userInOtherTeam) {
            return response()->json([
                'message' => 'El jugador ya pertenece a otro equipo',
                'error' => 'PLAYER_IN_TEAM'
            ], 400);
        }

        // Verificar si ya tiene una invitación pendiente
        $pendingInvitation = DB::table('equipo_usuarios')
            ->where('user_id', $request->user_id)
            ->where('estado', 'pendiente')
            ->exists();

        if ($pendingInvitation) {
            return response()->json([
                'message' => 'El jugador ya tiene una invitación pendiente',
                'error' => 'PENDING_INVITATION'
            ], 400);
        }

        $equipo->miembros()->attach($request->user_id, [
            'rol' => 'miembro',
            'estado' => 'pendiente'
        ]);

        return response()->json([
            'message' => 'Invitación enviada exitosamente'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al enviar la invitación',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function buscarUsuarioPorCodigo($codigo)
{
    $user = User::where('invite_code', $codigo)->first();
    
    if (!$user) {
        return response()->json(['message' => 'Usuario no encontrado'], 404);
    }

    return response()->json($user);
}

public function eliminarMiembro(Equipo $equipo, User $user)
{
    if (!$equipo->esCapitan(auth()->user())) {
        return response()->json(['error' => 'No autorizado'], 403);
    }

    if ($equipo->esCapitan($user)) {
        return response()->json([
            'error' => 'No se puede eliminar al capitán'
        ], 400);
    }

    $equipo->miembros()->detach($user->id);
    
    return response()->json([
        'message' => 'Miembro eliminado exitosamente'
    ]);
}

public function aceptarInvitacion(Equipo $equipo)
{
    try {
        // Verificar si el usuario ya está en otro equipo
        $userInOtherTeam = DB::table('equipo_usuarios')
            ->where('user_id', auth()->id())
            ->where('estado', 'activo')
            ->exists();

        if ($userInOtherTeam) {
            return response()->json([
                'message' => 'Ya perteneces a otro equipo',
                'error' => 'PLAYER_IN_TEAM'
            ], 400);
        }

        $equipo->miembros()
               ->wherePivot('user_id', auth()->id())
               ->wherePivot('estado', 'pendiente')
               ->updateExistingPivot(auth()->id(), ['estado' => 'activo']);

        return response()->json([
            'message' => 'Invitación aceptada exitosamente'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al aceptar la invitación',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function abandonarEquipo(Equipo $equipo)
    {
        try {
            if ($equipo->esCapitan(auth()->user())) {
                return response()->json([
                    'error' => 'El capitán no puede abandonar el equipo'
                ], 400);
            }

            $equipo->miembros()->detach(auth()->id());
            return response()->json(['message' => 'Has abandonado el equipo']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al abandonar el equipo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function unirseATorneo(Request $request, Equipo $equipo)
    {
        $request->validate([
            'torneo_id' => 'required|exists:torneos,id'
        ]);

        if (!$equipo->esCapitan(auth()->user())) {
            return response()->json(['error' => 'Solo el capitán puede inscribir al equipo'], 403);
        }

        try {
            $equipo->torneos()->attach($request->torneo_id, [
                'estado' => 'pendiente',
                'pago_confirmado' => false
            ]);

            return response()->json(['message' => 'Solicitud de inscripción enviada']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al inscribir al equipo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}