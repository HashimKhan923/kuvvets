<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Shift;
use App\Models\Time;

class MarkAbsentDaily extends Command
{
    protected $signature = 'attendance:mark-absent-daily';
    protected $description = 'Mark users Absent after shift ends if they did not time_in today (single database)';

    public function handle()
    {
        $timezone = 'Asia/Karachi';
        $today = Carbon::today($timezone);
        $todayName = $today->format('l'); // Monday, Tuesday, etc.

        $this->info("Running Absent marking for date: {$today->toDateString()} ({$todayName})");

        // Process employees in chunks
        User::where('role_id', 2)->chunk(200, function ($users) use ($today, $todayName, $timezone) {

            foreach ($users as $user) {
                // Load shift
                $shift = Shift::find($user->shift_id);

                if (!$shift) {
                    $this->warn("No shift for User ID {$user->id}, skipping.");
                    continue;
                }

                // Normalize shift days (handle json or array or null)
                $shiftDays = is_array($shift->days) ? $shift->days : json_decode($shift->days, true);
                $shiftDays = (array) ($shiftDays ?: []);

                // If today is not one of the shift days → skip
                if (!in_array($todayName, $shiftDays)) {
                    continue;
                }

                // Build shift start / end datetime for today
                $shiftStart = Carbon::parse($today->format('Y-m-d') . ' ' . $shift->time_from, $timezone);
                $shiftEnd   = Carbon::parse($today->format('Y-m-d') . ' ' . $shift->time_to, $timezone);

                // Handle overnight shifts (ex: 20:00 → 05:00)
                if ($shiftEnd->lessThan($shiftStart)) {
                    $shiftEnd->addDay();
                }

                // If current time < shift end → don't mark absent yet
                if (Carbon::now($timezone)->lt($shiftEnd)) {
                    continue;
                }

                // Check if user already did time_in today
                $exists = Time::where('user_id', $user->id)
                    ->whereDate('time_in', $today->toDateString())
                    ->exists();

                if ($exists) {
                    continue; // user already timed-in — skip
                }

                // Create ABSENT record at midnight of today in configured timezone
                $dateTime = Carbon::parse($today->toDateString() . ' 00:00:00', $timezone);

                DB::transaction(function () use ($user, $dateTime, $today) {
                    Time::create([
                        'user_id'    => $user->id,
                        'time_in'    => $dateTime,
                        'time_out'   => $dateTime,
                        'status'     => 'Absent',
                        'created_at' => $today->copy()->startOfDay(),
                        'updated_at' => $today->copy()->startOfDay(),
                    ]);
                });

                $this->line("Marked Absent → User ID: {$user->id}, Date: {$today->toDateString()}");
            }
        });

        $this->info("🎉 Daily Absent Marking Completed Successfully");
        return 0;
    }
}
