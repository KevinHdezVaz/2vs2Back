<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use Carbon\Carbon;

class UpdateBookingStatuses extends Command
{
    protected $signature = 'bookings:update-statuses';
    protected $description = 'Update expired booking statuses to completed';

    public function handle()
    {
        $updated = Booking::where('status', 'pending')
            ->where('end_time', '<', Carbon::now())
            ->update(['status' => 'completed']);

        $this->info("Se actualizaron {$updated} reservas.");
    }
}
