<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ChatMensaje;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ChatMensajeController extends Controller
{
    
    public function store(Request $request)
{
    try {
      
        $request->validate([
            'equipo_id' => 'required|exists:equipos,id',
            'mensaje' => 'nullable|string',
            'archivo' => 'nullable|file|max:10240',
            'tipo' => 'nullable|in:imagen,archivo',
            'reply_to_id' => 'nullable|exists:chat_mensajes,id'
        ]);

        $fileUrl = null;
        $fileName = null;
        $fileType = null;

        if ($request->hasFile('archivo')) {
            $file = $request->file('archivo');
            $fileName = $file->getClientOriginalName();
            $fileType = $request->tipo;
            $folder = $fileType === 'imagen' ? 'chat_images' : 'chat_files';
            $path = $file->store($folder, 'public');
            $fileUrl = $path;
        }
        $mensaje = ChatMensaje::create([
            'equipo_id' => $request->equipo_id,
            'user_id' => Auth::id(),
            'mensaje' => $request->mensaje ?? '',
            'file_url' => $fileUrl,
            'file_type' => $fileType,
            'file_name' => $fileName,
            'reply_to_id' => $request->reply_to_id
        ]);
    
        $mensaje->load(['user:id,name,profile_image', 'replyTo.user:id,name,profile_image']);

        return response()->json([
            'id' => $mensaje->id,
            'mensaje' => $mensaje->mensaje,
            'user_id' => $mensaje->user_id,
            'user_name' => $mensaje->user->name,
            'user_image' => $mensaje->user->profile_image,
            'file_url' => $fileUrl ? Storage::url($fileUrl) : null,
            'file_type' => $fileType,
            'file_name' => $fileName,
            'reply_to' => $mensaje->replyTo ? [
                'id' => $mensaje->replyTo->id,
                'mensaje' => $mensaje->replyTo->mensaje,
                'user_name' => $mensaje->replyTo->user->name,
                'user_id' => $mensaje->replyTo->user_id,
            ] : null,
            'created_at' => $mensaje->created_at
        ], 201);

    } catch (\Exception $e) {
        \Log::error('Error en store de ChatMensajeController', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'message' => 'Error al procesar el mensaje: ' . $e->getMessage()
        ], 500);
    }
}


    public function getMensajesEquipo($equipoId)
    {
        $mensajes = ChatMensaje::with('user:id,name,profile_image')
            ->where('equipo_id', $equipoId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($mensaje) {
                return [
                    'id' => $mensaje->id,
                    'mensaje' => $mensaje->mensaje,
                    'user_id' => $mensaje->user_id,
                    'user_name' => $mensaje->user->name,
                    'user_image' => $mensaje->user->profile_image,
                    'file_url' => $mensaje->file_url ? Storage::url($mensaje->file_url) : null,
                    'file_type' => $mensaje->file_type,
                    'file_name' => $mensaje->file_name,
                    'created_at' => $mensaje->created_at
                ];
            });

        return response()->json($mensajes);
    }
}