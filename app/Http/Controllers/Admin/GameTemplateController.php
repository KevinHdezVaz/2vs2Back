<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GameTemplateController extends Controller
{
    private $templatesPath = 'game_templates';

    // Credenciales (deben coincidir con SimpleAuth)
    private const USERNAME = 'admin';
    private const PASSWORD = 'picklebracket2024';

    /**
     * Mostrar formulario de login
     */
    public function showLogin()
    {
        // Si ya está autenticado, redirigir al panel
        if (session('authenticated') === true) {
            return redirect()->route('templates.index');
        }
        
        return view('login');
    }

    /**
     * Procesar login
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $username = $request->input('username');
        $password = $request->input('password');

        Log::info('Login attempt', [
            'username' => $username,
            'ip' => $request->ip()
        ]);

        // Validar credenciales
        if ($username === self::USERNAME && $password === self::PASSWORD) {
            session(['authenticated' => true]);
            
            Log::info('Login successful', ['username' => $username]);
            
            return redirect()->route('templates.index')->with('success', '¡Bienvenido!');
        }

        Log::warning('Login failed', [
            'username' => $username,
            'ip' => $request->ip()
        ]);

        return back()
            ->withErrors(['error' => 'Usuario o contraseña incorrectos'])
            ->withInput($request->only('username'));
    }

    /**
     * Cerrar sesión
     */
    public function logout()
    {
        session()->forget('authenticated');
        session()->flush();
        
        return redirect()->route('templates.login')->with('success', 'Sesión cerrada correctamente');
    }

    /**
     * Mostrar el panel con todos los templates
     */
    public function index()
    {
        try {
            $files = Storage::files($this->templatesPath);
            
            $templates = collect($files)->map(function ($file) {
                $filename = basename($file);
                $path = storage_path("app/{$file}");
                
                return [
                    'filename' => $filename,
                    'path' => $file,
                    'size' => $this->formatBytes(filesize($path)),
                    'modified' => date('Y-m-d H:i:s', filemtime($path)),
                    'is_valid' => $this->isValidJson($path)
                ];
            })->sortByDesc('modified')->values();

            return view('templates.index', compact('templates'));
        } catch (\Exception $e) {
            Log::error('Error loading templates', ['error' => $e->getMessage()]);
            return view('templates.index', ['templates' => collect([])]);
        }
    }

    /**
     * Descargar un template específico
     */
    public function download($filename)
    {
        try {
            $path = "{$this->templatesPath}/{$filename}";
            
            if (!Storage::exists($path)) {
                return back()->with('error', 'Template no encontrado');
            }

            return Storage::download($path, $filename);
        } catch (\Exception $e) {
            Log::error('Error downloading template', [
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);
            return back()->with('error', 'Error al descargar el archivo');
        }
    }

    /**
     * Ver contenido de un template
     */
    public function view($filename)
    {
        try {
            $path = "{$this->templatesPath}/{$filename}";
            
            if (!Storage::exists($path)) {
                return back()->with('error', 'Template no encontrado');
            }

            $content = Storage::get($path);
            $data = json_decode($content, true);
            
            return view('templates.view', [
                'filename' => $filename,
                'content' => $content,
                'data' => $data,
                'is_valid' => !is_null($data)
            ]);
        } catch (\Exception $e) {
            Log::error('Error viewing template', [
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);
            return back()->with('error', 'Error al visualizar el archivo');
        }
    }

    /**
     * Subir nuevo template
     */
    public function upload(Request $request)
    {
        $request->validate([
            'template_file' => 'required|file|mimes:json|max:2048'
        ], [
            'template_file.required' => 'Debes seleccionar un archivo',
            'template_file.mimes' => 'El archivo debe ser un JSON válido',
            'template_file.max' => 'El archivo no debe pesar más de 2MB'
        ]);

        try {
            $file = $request->file('template_file');
            
            // Validar que sea un JSON válido
            $content = file_get_contents($file->getRealPath());
            $jsonData = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->with('error', 'El archivo JSON no es válido: ' . json_last_error_msg());
            }

            // Guardar el archivo
            $filename = $file->getClientOriginalName();
            $path = $file->storeAs($this->templatesPath, $filename);

            Log::info('Template uploaded successfully', [
                'filename' => $filename,
                'path' => $path
            ]);

            return back()->with('success', "Template '{$filename}' subido exitosamente");
        } catch (\Exception $e) {
            Log::error('Error uploading template', [
                'error' => $e->getMessage()
            ]);
            return back()->with('error', 'Error al subir el archivo: ' . $e->getMessage());
        }
    }

    /**
     * ✅ NUEVO: Renombrar un template
     */
    public function rename(Request $request, $filename)
    {
        $request->validate([
            'new_name' => 'required|string|regex:/^[\w\-\.]+\.json$/|max:255'
        ], [
            'new_name.required' => 'El nuevo nombre es requerido',
            'new_name.regex' => 'El nombre debe terminar en .json y solo contener letras, números, guiones y puntos',
            'new_name.max' => 'El nombre es muy largo (máximo 255 caracteres)'
        ]);

        try {
            $oldPath = "{$this->templatesPath}/{$filename}";
            $newName = $request->input('new_name');
            $newPath = "{$this->templatesPath}/{$newName}";

            // Verificar que el archivo existe
            if (!Storage::exists($oldPath)) {
                return back()->with('error', 'Template no encontrado');
            }

            // Verificar que el nuevo nombre no exista ya
            if (Storage::exists($newPath) && $filename !== $newName) {
                return back()->with('error', "Ya existe un archivo con el nombre '{$newName}'");
            }

            // Renombrar el archivo
            Storage::move($oldPath, $newPath);

            Log::info('Template renamed successfully', [
                'old_name' => $filename,
                'new_name' => $newName
            ]);

            return redirect()->route('templates.index')
                ->with('success', "Template renombrado de '{$filename}' a '{$newName}' exitosamente");
        } catch (\Exception $e) {
            Log::error('Error renaming template', [
                'filename' => $filename,
                'new_name' => $request->input('new_name'),
                'error' => $e->getMessage()
            ]);
            return back()->with('error', 'Error al renombrar el archivo: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar un template
     */
    public function delete($filename)
    {
        try {
            $path = "{$this->templatesPath}/{$filename}";
            
            if (!Storage::exists($path)) {
                return back()->with('error', 'Template no encontrado');
            }

            Storage::delete($path);

            Log::info('Template deleted successfully', [
                'filename' => $filename
            ]);

            return back()->with('success', "Template '{$filename}' eliminado exitosamente");
        } catch (\Exception $e) {
            Log::error('Error deleting template', [
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);
            return back()->with('error', 'Error al eliminar el archivo');
        }
    }

    /**
     * Verificar si un archivo es un JSON válido
     */
    private function isValidJson($path): bool
    {
        try {
            $content = file_get_contents($path);
            json_decode($content);
            return json_last_error() === JSON_ERROR_NONE;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Formatear bytes a tamaño legible
     */
    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}