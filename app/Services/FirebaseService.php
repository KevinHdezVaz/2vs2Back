<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Database;

class FirebaseService
{
    private Database $database;

    public function __construct()
    {
        $factory = (new Factory)->withServiceAccount(base_path(env('FIREBASE_CREDENTIALS')));
        $this->database = $factory->createDatabase();
    }

    /**
     * Crear sesión en Firebase
     */
    public function createSession(array $sessionData): string
    {
        $reference = $this->database->getReference('sessions')->push([
            'session_name' => $sessionData['session_name'],
            'number_of_courts' => $sessionData['number_of_courts'],
            'duration_hours' => $sessionData['duration_hours'],
            'number_of_players' => $sessionData['number_of_players'],
            'session_type' => $sessionData['session_type'],
            'status' => 'pending',
            'created_at' => now()->toIso8601String(),
            'games' => [],
            'players' => []
        ]);

        return $reference->getKey();
    }

    /**
     * Actualizar sesión en Firebase
     */
    public function updateSession(string $firebaseId, array $data): void
    {
        $this->database
            ->getReference("sessions/{$firebaseId}")
            ->update($data);
    }

    /**
     * Sincronizar juego con Firebase
     */
    public function syncGame(string $firebaseId, int $gameId, array $gameData): void
    {
        $this->database
            ->getReference("sessions/{$firebaseId}/games/{$gameId}")
            ->set($gameData);
    }

    /**
     * Actualizar juego específico
     */
    public function updateGame(string $firebaseId, int $gameId, array $data): void
    {
        $this->database
            ->getReference("sessions/{$firebaseId}/games/{$gameId}")
            ->update($data);
    }

    /**
     * Sincronizar estadísticas de jugadores
     */
    public function syncPlayerStats(string $firebaseId, array $playersData): void
    {
        $this->database
            ->getReference("sessions/{$firebaseId}/players")
            ->set($playersData);
    }

    /**
     * Eliminar sesión de Firebase
     */
    public function deleteSession(string $firebaseId): void
    {
        $this->database
            ->getReference("sessions/{$firebaseId}")
            ->remove();
    }

    /**
     * Escuchar cambios en tiempo real (para webhooks/listeners)
     */
    public function listenToSession(string $firebaseId, callable $callback): void
    {
        $this->database
            ->getReference("sessions/{$firebaseId}")
            ->onChange($callback);
    }

    /**
     * Obtener datos de sesión desde Firebase
     */
    public function getSession(string $firebaseId): ?array
    {
        $snapshot = $this->database
            ->getReference("sessions/{$firebaseId}")
            ->getSnapshot();

        return $snapshot->exists() ? $snapshot->getValue() : null;
    }
}