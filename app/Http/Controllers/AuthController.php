<?php

namespace App\Http\Controllers;
use App\Models\Affiliate;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Auth as FirebaseAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    /**
     * Registra un nuevo usuario.
     */
    public function __construct()
    {
        // Configuración de Firebase
        $serviceAccountPath = storage_path('app/firebase/vs2-962e9-firebase-adminsdk-fbsvc-45655cd0c8.json');
        
        if (!file_exists($serviceAccountPath)) {
            throw new \RuntimeException("Archivo de configuración de Firebase no encontrado");
        }

        $this->firebaseAuth = (new Factory)
            ->withServiceAccount($serviceAccountPath)
            ->createAuth();
    }

    public function register(Request $request)
    {
        // 1. Validar los datos
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20|unique:users',
            'password' => 'required|string|min:6',
            'affiliate_code' => 'nullable|string|exists:affiliates,referral_code',
        ]);

        Log::info('Datos validados para registro:', $validated);

        // 2. Hashear la contraseña
        $validated['password'] = Hash::make($validated['password']);
        Log::info('Contraseña hasheada.');

        // 3. Añadir datos del período de prueba
        $validated['trial_ends_at'] = Carbon::now()->addDays(5);
        $validated['subscription_status'] = 'trial';
        
        // 4. Guardar el código de afiliado que se usó (si existe)
        if ($request->has('affiliate_code')) {
            $validated['applied_affiliate_code'] = $request->affiliate_code;
        }

        Log::info('Datos de prueba y afiliado añadidos.');

        // 5. Crear el usuario en la base de datos
        $user = User::create($validated);

        Log::info('Usuario creado:', ['id' => $user->id, 'email' => $user->email]);

        // 6. Crear un token
        $token = $user->createToken('auth_token')->plainTextToken;
        Log::info('Token generado para el usuario.');

        // 7. Devolver la respuesta
        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * Inicia sesión para un usuario existente.
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Verificar que el usuario exista y la contraseña sea correcta.
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }
    
        return response()->json([
            'user' => $user,
            'token' => $user->createToken('auth_token')->plainTextToken
        ]);
    }

    public function googleLogin(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_token' => 'required|string',
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
    
            $idToken = $request->input('id_token');
            
            \Log::info("Token recibido: " . substr($idToken, 0, 30) . "...");
    
            try {
                $verifiedIdToken = $this->firebaseAuth->verifyIdToken($idToken, true);
                $claims = $verifiedIdToken->claims();
                
                $firebaseUid = $claims->get('sub');
                $email = $claims->get('email');
                $name = $claims->get('name') ?? 'Usuario Google';
                
                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'password' => Hash::make(uniqid()),
                        'firebase_uid' => $firebaseUid,
                        'auth_provider' => 'google',
                        'trial_ends_at' => Carbon::now()->addDays(5),
                        'subscription_status' => 'trial',
                        'phone' => null,
                    ]
                );
    
                $token = $user->createToken('auth_token')->plainTextToken;
    
                return response()->json([
                    'success' => true,
                    'user' => $user,
                    'token' => $token,
                ]);
    
            } catch (\Throwable $e) {
                \Log::error("Error al verificar token:", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'token_sample' => substr($idToken, 0, 50)
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Error en verificación de token',
                    'error' => $e->getMessage(),
                ], 401);
            }
    
        } catch (\Exception $e) {
            \Log::error("Error general en googleLogin: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error en autenticación con Google',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getUserName(Request $request)
    {
        return response()->json([
            'name' => $request->user()->name,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        
        return response()->json(['message' => 'Cierre de sesión exitoso']);
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'user' => $user,
        ]);
    }

    // ✅ NUEVO: Obtener perfil completo con estadísticas
    public function getProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Contar sesiones completadas
        $completedSessions = \App\Models\Session::where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();
        
        // Contar sesiones activas
        $activeSessions = \App\Models\Session::where('user_id', $user->id)
            ->where('status', 'active')
            ->count();
        
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at->format('F j, Y'), // "January 15, 2024"
            'sessions_completed' => $completedSessions,
            'active_sessions' => $activeSessions,
        ]);
    }
    
    // ✅ NUEVO: Eliminar cuenta del usuario
    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        
        try {
            Log::info('Iniciando eliminación de cuenta', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            // Eliminar todas las sesiones del usuario (cascade eliminará todo lo relacionado)
            $deletedSessions = \App\Models\Session::where('user_id', $user->id)->delete();
            
            Log::info('Sesiones eliminadas', [
                'user_id' => $user->id,
                'deleted_sessions' => $deletedSessions
            ]);
            
            // Eliminar tokens de autenticación
            $user->tokens()->delete();
            
            // Eliminar el usuario
            $user->delete();
            
            Log::info('Cuenta eliminada exitosamente', [
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'message' => 'Account deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar cuenta', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Error deleting account: ' . $e->getMessage()
            ], 500);
        }
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Enlace de restablecimiento enviado.'], 200)
            : response()->json(['message' => 'No se pudo enviar el enlace.'], 400);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'token', 'password', 'password_confirmation'),
            function ($user, $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Contraseña restablecida exitosamente.'], 200)
            : response()->json(['message' => 'No se pudo restablecer la contraseña.'], 400);
    }
}