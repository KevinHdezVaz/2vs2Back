<?php
namespace App\Http\Controllers\API;

use Carbon\Carbon;
use App\Models\Field;
use App\Models\Order;
use App\Models\Booking;
use App\Models\DailyMatch;
use Illuminate\Http\Request;
use App\Services\WalletService;
use App\Http\Controllers\Controller;
use App\Services\MercadoPagoService;

class BookingController extends Controller 
{
    protected $mercadoPagoService;
    protected $walletService;

    public function __construct(MercadoPagoService $mercadoPagoService, WalletService $walletService)    
    {
        $this->mercadoPagoService = $mercadoPagoService;
        $this->walletService = $walletService;  
    }

    public function index() 
    {
        $bookings = auth()->user()->bookings()
            ->with('field')
            ->orderBy('start_time', 'desc')
            ->get();
        return response()->json($bookings);
    }

    public function checkPaymentExists($paymentId)
    {
        $booking = Booking::where('payment_id', $paymentId)->first();
        
        return response()->json([
            'exists' => $booking !== null,
            'booking_id' => $booking ? $booking->id : null,
            'message' => $booking ? 'Reserva encontrada con este ID de pago' : 'No se encontró ninguna reserva con este ID de pago'
        ]);
    }

    public function store(Request $request)
{
    $validated = $request->validate([
        'field_id' => 'required|exists:fields,id',
        'date' => 'required|date|after_or_equal:today',
        'start_time' => 'required|date_format:H:i',
        'players_needed' => 'nullable|integer',
        'allow_joining' => 'boolean',
        'payment_id' => 'nullable|string',
        'order_id' => 'nullable|exists:orders,id',
        'use_wallet' => 'boolean',
    ]);

    try {
        // Verificar si ya existe una reserva con este payment_id
        if (!empty($request->input('payment_id'))) {
            $existingBooking = Booking::where('payment_id', $request->input('payment_id'))->first();
            if ($existingBooking) {
                return response()->json($existingBooking->load('field'), 200);
            }
        }

        $field = Field::findOrFail($validated['field_id']);
        
        // Normalizar start_time a H:i:s
        $startTimeRaw = $validated['start_time'];
        if (strlen($startTimeRaw) == 5) { // H:i (ej. 19:00)
            $startTimeRaw .= ':00'; // H:i:s (ej. 19:00:00)
        }
        $scheduleDate = $validated['date'];
        $startTime = $scheduleDate . ' ' . $startTimeRaw;
        $endTime = date('Y-m-d H:i:s', strtotime($startTime . ' +1 hour'));

        \Log::info('Datos de entrada', [
            'field_id' => $field->id,
            'schedule_date' => $scheduleDate,
            'start_time_raw' => $startTimeRaw,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);

        // Buscar el partido en DailyMatch
        $match = DailyMatch::where('field_id', $field->id)
            ->where('schedule_date', $scheduleDate)
            ->where('start_time', $startTimeRaw)
            ->where('status', 'open')
            ->first();

        if ($match) {
            // Verificar si el partido tiene jugadores registrados
            if ($match->player_count > 0) {
                \Log::info('No se puede reservar: el partido tiene jugadores', [
                    'match_id' => $match->id,
                    'player_count' => $match->player_count,
                ]);
                return response()->json([
                    'message' => 'No se puede reservar este partido porque ya tiene jugadores registrados'
                ], 422);
            }

            $match->update(['status' => 'reserved']);
            \Log::info('Partido marcado como reservado', [
                'match_id' => $match->id,
                'booking_id' => $booking->id ?? 'pendiente',
            ]);
        }

        $totalPrice = floatval($field->price_per_match);
        $amountToPay = $totalPrice;
        $paymentMethod = 'mercadopago';
        $paymentId = $request->input('payment_id');

        // Lógica de pago (sin cambios)
        if ($request->input('use_wallet', false)) {
            $wallet = auth()->user()->wallet;
            if ($wallet && $wallet->balance > 0) {
                if ($wallet->balance >= $totalPrice) {
                    $this->walletService->withdraw(auth()->user(), $totalPrice, "Pago de reserva para {$field->name}");
                    $amountToPay = 0;
                    $paymentMethod = 'wallet';
                    $paymentId = null;
                } else {
                    $amountToPay -= $wallet->balance;
                    $this->walletService->withdraw(auth()->user(), $wallet->balance, "Pago parcial con monedero para {$field->name}");
                    $paymentMethod = 'mixed';
                }
            }
        }

        if ($amountToPay > 0) {
            if (empty($request->input('payment_id')) || empty($request->input('order_id'))) {
                return response()->json(['message' => 'Falta payment_id o order_id para pago con MercadoPago'], 422);
            }

            $order = Order::findOrFail($validated['order_id']);
            if ($order->type !== 'booking' || $order->reference_id != $validated['field_id']) {
                return response()->json(['message' => 'Orden inválida para esta reserva'], 422);
            }

            if ($order->payment_id !== $request->input('payment_id') || $order->status !== 'completed') {
                $paymentInfo = $this->mercadoPagoService->getPaymentInfo($request->input('payment_id'));
                if ($paymentInfo['status'] !== 'approved') {
                    return response()->json(['message' => 'El pago aún no ha sido aprobado'], 422);
                }
                $order->update([
                    'payment_id' => $request->input('payment_id'),
                    'status' => 'completed',
                    'payment_details' => array_merge($order->payment_details, ['payment_info' => $paymentInfo]),
                ]);
            }
        }

        // Crear la reserva directamente
        $booking = Booking::create([
            'user_id' => auth()->id(),
            'field_id' => $field->id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'total_price' => $totalPrice,
            'status' => 'confirmed',
            'players_needed' => $validated['players_needed'],
            'allow_joining' => $validated['allow_joining'] ?? false,
            'payment_id' => $paymentId,
            'payment_status' => 'completed',
            'payment_method' => $paymentMethod,
            'daily_match_id' => $match ? $match->id : null,
        ]);

        \Log::info('Booking created successfully', [
            'booking_id' => $booking->id,
            'field_id' => $field->id,
            'start_time' => $startTime,
            'daily_match_id' => $booking->daily_match_id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $booking->load('field'),
        ], 201);
    } catch (\Exception $e) {
        \Log::error('Error creating booking: ' . $e->getMessage(), [
            'exception' => $e->getTraceAsString(),
        ]);
        return response()->json([
            'success' => false,
            'message' => 'Error al crear la reserva',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    public function getAvailableHours(Field $field, Request $request)
    {
        // Validar la fecha
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);
    
        $date = Carbon::parse($request->date);
        $dayOfWeek = strtolower($date->format('l')); // Día de la semana en minúsculas (monday, tuesday, etc.)
    
        \Log::info('Requesting available hours', [
            'field_id' => $field->id,
            'date' => $request->date,
            'day_of_week' => $dayOfWeek
        ]);
    
        // Verificar si la fecha es pasada (excepto hoy)
        if ($date->isPast() && !$date->isToday()) {
            return response()->json(['available_hours' => []]);
        }
    
        // Obtener los partidos 'open' para esta cancha y fecha
        $matches = DailyMatch::where('field_id', $field->id)
            ->where('schedule_date', $date->format('Y-m-d'))
            ->where('status', 'open')
            ->get();
    
        // Extraer los horarios de inicio de los partidos disponibles
        $availableHours = $matches->map(function ($match) {
            return $match->start_time; // Asumimos que start_time es el horario disponible
        })->unique()->values()->all();
    
        \Log::info('Available hours based on open matches', [
            'field_id' => $field->id,
            'date' => $date->format('Y-m-d'),
            'available_hours' => $availableHours,
        ]);
    
        return response()->json(['available_hours' => $availableHours]);
    }

    private function checkAvailability($fieldId, $startTime, $endTime) 
    {
        return !Booking::where('field_id', $fieldId)
            ->where('status', '!=', 'cancelled')
            ->where(function($query) use ($startTime, $endTime) {
                $query->whereBetween('start_time', [$startTime, $endTime])
                    ->orWhereBetween('end_time', [$startTime, $endTime])
                    ->orWhereRaw('? BETWEEN start_time AND end_time', [$startTime]);
            })->exists();
    }

    public function cancel(Booking $booking, Request $request) 
    {
        if ($booking->user_id !== auth()->id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }
    
        if ($booking->status === 'cancelled') {
            return response()->json(['message' => 'La reserva ya está cancelada'], 400);
        }
    
        if ($booking->start_time < now()) {
            return response()->json(['message' => 'No se puede cancelar una reserva pasada'], 400);
        }
    
        // Actualizar la reserva
        $booking->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->input('reason'),
            'payment_status' => 'refunded',
        ]);
    
        // Reembolsar al monedero
        $this->walletService->refundBooking(
            auth()->user(),
            floatval($booking->total_price),
            "Reserva #{$booking->id}"
        );
    
        // Si está vinculado a un partido, revertir su estado a 'open'
        if ($booking->daily_match_id) {
            $match = DailyMatch::find($booking->daily_match_id);
            if ($match && $match->status === 'reserved') {
                $match->update(['status' => 'open']);
                \Log::info('Estado del partido revertido a open tras cancelación', [
                    'match_id' => $match->id,
                    'booking_id' => $booking->id,
                ]);
            }
        }
    
        return response()->json([
            'message' => 'Reserva cancelada y reembolsada al monedero',
            'booking' => $booking,
            'refunded_amount' => $booking->total_price,
        ]);
    }

    public function getActiveReservations()
    {
        $bookings = auth()->user()->bookings()
            ->with('field')
            ->where(function($query) {
                $query->where('status', 'confirmed')
                    ->orWhere('status', 'pending');
            })
            ->where('end_time', '>', now())
            ->orderBy('start_time')
            ->get();
    
        \Log::info('Active Reservations Query:', [
            'user_id' => auth()->id(),
            'bookings' => $bookings->toArray()
        ]);
    
        return response()->json($bookings);
    }

    public function getReservationHistory()
    {
        $bookings = auth()->user()->bookings()
            ->with('field')
            ->where(function($query) {
                $query->where('status', 'completed')
                    ->orWhere('status', 'cancelled')
                    ->orWhere(function($q) {
                        $q->where('end_time', '<', now())
                          ->where('status', '!=', 'pending');
                    });
            })
            ->orderBy('start_time', 'desc')
            ->get();

        return response()->json($bookings);
    }
}