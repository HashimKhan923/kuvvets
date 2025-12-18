<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Shift;
use App\Models\Time;
use Illuminate\Support\Facades\DB;

class MarkOffDaily extends Command
{
    protected $signature = 'attendance:mark-off-daily';
    protected $description = 'Mark OFF status for users when today is not a shift day (single database)';

    public function handle()
    {
        $timezone = 'Asia/Karachi';
        $today = Carbon::today($timezone);
        $todayName = $today->format('l'); // Sunday, Monday, etc.
        $this->info("Running OFF marking for date: {$today->toDateString()} ({$todayName})");

        // Process employees in chunks to avoid memory issues
        User::where('role_id', 2)
            ->chunk(200, function ($users) use ($today, $todayName, $timezone) {
                foreach ($users as $user) {
                    // Load shift
                    $shift = Shift::find($user->shift_id);

                    // If no shift -> skip
                    if (!$shift) {
                        continue;
                    }

                    // Normalize shift days array (handle json or array)
                    $shiftDays = is_array($shift->days)
                        ? $shift->days
                        : json_decode($shift->days, true);

                    $shiftDays = (array) $shiftDays;

                    // If today IS a shift day, skip (we only mark off when not a shift day)
                    if (in_array($todayName, $shiftDays)) {
                        continue;
                    }

                    // Check if any record exists for today (match by date part of time_in)
                    $exists = Time::where('user_id', $user->id)
                        ->whereDate('time_in', $today->toDateString())
                        ->exists();

                    if ($exists) {
                        continue; // record already exists — skip
                    }

                    // Create OFF record at midnight of today in configured timezone
                    $dateTime = Carbon::parse($today->toDateString() . ' 00:00:00', $timezone);

                    DB::transaction(function () use ($user, $dateTime, $today) {
                        Time::create([
                            'user_id'    => $user->id,
                            'time_in'    => $dateTime,
                            'time_out'   => $dateTime,
                            'status'     => 'Off',
                            'created_at' => $today->copy()->startOfDay(),
                            'updated_at' => $today->copy()->startOfDay(),
                        ]);
                    });

                    $this->line("Marked OFF → User ID: {$user->id}, Date: {$today->toDateString()}");
                }
            });

        $this->info("🎉 OFF Marking Completed Successfully");
        return 0;
    }
}
