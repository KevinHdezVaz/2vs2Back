<?php

namespace App\Http\Controllers\API;

use Carbon\Carbon;
use App\Models\Field;
use App\Models\Booking;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class FieldController extends Controller
{
    public function index()
    {
        return response()->json(Field::all());
    }
 
    public function show(Field $field) {
        return $field;
    }


    public function updateBookingStatuses()
{
    // Buscar todas las reservas pendientes que ya pasaron su end_time
    $expiredBookings = Booking::where('status', 'pending')
        ->where('end_time', '<', Carbon::now())
        ->update(['status' => 'completed']);
    
    return response()->json([
        'message' => 'Reservas actualizadas exitosamente',
        'updated_count' => $expiredBookings
    ]);
}


    public function getAvailableHours(Field $field, Request $request)
{
    $request->validate([
        'date' => 'required|date',
    ]);
    
    $date = $request->date;
    $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));
    
    // Obtener los horarios base para ese día
    $availableHours = json_decode($field->available_hours, true);
    $hoursForDay = $availableHours[$dayOfWeek] ?? [];
    
    // Obtener las reservas existentes
    $bookings = Booking::where('field_id', $field->id)
        ->whereDate('start_time', $date)
        ->where('status', '!=', 'cancelled')
        ->get(['start_time']);
        
    $bookedHours = $bookings->map(function($booking) {
        return Carbon::parse($booking->start_time)->format('H:i');
    })->toArray();
    
    // Filtrar las horas disponibles
    $availableHours = array_filter($hoursForDay, function($hour) use ($bookedHours, $date) {
        // Si la hora ya está reservada, no está disponible
        if (in_array($hour, $bookedHours)) {
            return false;
        }
        
        // Si es hoy, verificar si la hora ya pasó
        if (Carbon::parse($date)->isToday()) {
            $hourTime = Carbon::parse($date . ' ' . $hour);
            return $hourTime->isFuture();
        }
        
        return true;
    });
    
    return response()->json([
        'available_hours' => array_values($availableHours),
        'booked_hours' => $bookedHours
    ]);
}


    public function getBookedHours(Field $field, Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);
        
        $date = $request->date;
        
        $bookings = Booking::where('field_id', $field->id)
            ->whereDate('start_time', $date)
            ->where('status', '!=', 'cancelled')
            ->get(['start_time']);
            
        $bookedHours = $bookings->map(function($booking) {
            return Carbon::parse($booking->start_time)->format('H:i');
        });
        
        return response()->json($bookedHours);
    }

    public function syncFieldHours(Field $field, Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);
        
        $date = $request->date;
        $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));
        
        // Obtener horarios base del campo
        $availableHours = json_decode($field->available_hours, true);
        $baseHours = $availableHours[$dayOfWeek] ?? [];
        
        // Obtener reservas existentes
        $bookings = Booking::where('field_id', $field->id)
            ->whereDate('start_time', $date)
            ->where('status', '!=', 'cancelled')
            ->get(['start_time']);
        
        // Mapear las horas reservadas
        $bookedHours = $bookings->map(function($booking) {
            return Carbon::parse($booking->start_time)->format('H:i');
        })->toArray();
        
        // Filtrar horas disponibles
        $availableHours = array_filter($baseHours, function($hour) use ($bookedHours, $date) {
            // Verificar si la hora está reservada
            if (in_array($hour, $bookedHours)) {
                return false;
            }
            
            // Si es hoy, verificar si la hora ya pasó
            if (Carbon::parse($date)->isToday()) {
                return Carbon::parse($date . ' ' . $hour)->isFuture();
            }
            
            return true;
        });
        
        return response()->json([
            'base_hours' => $baseHours,
            'available_hours' => array_values($availableHours),
            'booked_hours' => $bookedHours
        ]);
    }
}