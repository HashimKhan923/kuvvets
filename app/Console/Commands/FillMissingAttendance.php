<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Shift;
use App\Models\Time;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FillMissingAttendance extends Command
{
    protected $signature = 'attendance:fill-missing {--dry-run : Do not write to DB, only show what would be created}';
    protected $description = 'Fill missing attendance records with Absent or Off based on shift days (single database)';

    public function handle()
    {
        $timezone = 'Asia/Karachi';
        $today = Carbon::today($timezone);
        $yesterday = $today->copy()->subDay();

        $this->info("Running fill-missing (up to {$yesterday->toDateString()})");
        $dryRun = $this->option('dry-run');

        // Process employees in chunks to avoid memory issues
        User::where('role_id', 2)
            ->chunkById(200, function ($users) use ($timezone, $yesterday, $dryRun) {

                foreach ($users as $user) {
                    $shift = Shift::find($user->shift_id);

                    if (!$shift) {
                        $this->warn("⚠️ User {$user->id} has no shift assigned, skipping.");
                        continue;
                    }

                    // Normalize shift days (handle json, array or null)
                    $shiftDays = is_array($shift->days) ? $shift->days : json_decode($shift->days, true);
                    $shiftDays = (array) ($shiftDays ?: []);

                    // get first and last attendance by time_in (fall back to created_at if needed)
                    $timeModel = Time::query();

                    $firstRecord = $timeModel->where('user_id', $user->id)
                        ->orderBy('time_in', 'asc')
                        ->first();

                    $lastRecord = $timeModel->where('user_id', $user->id)
                        ->orderBy('time_in', 'desc')
                        ->first();

                    if (!$firstRecord || !$lastRecord) {
                        // nothing to fill if user has no records at all
                        continue;
                    }

                    // Use the date part of time_in as boundaries; ensure we do not fill today
                    $startDate = Carbon::parse($firstRecord->time_in)->startOfDay()->setTimezone($timezone);
                    $endDateFromRecords = Carbon::parse($lastRecord->time_in)->startOfDay()->setTimezone($timezone);

                    // We will fill only up to yesterday (not today)
                    $endDate = $endDateFromRecords->lte($yesterday) ? $endDateFromRecords->copy() : $yesterday->copy();

                    // If start is after end (rare), skip
                    if ($startDate->gt($endDate)) {
                        continue;
                    }

                    $date = $startDate->copy();

                    while ($date->lte($endDate)) {
                        $exists = Time::where('user_id', $user->id)
                            ->whereDate('time_in', $date->toDateString())
                            ->exists();

                        if (!$exists) {
                            $dayName = $date->format('l');
                            $isShiftDay = in_array($dayName, $shiftDays);

                            $dateTime = Carbon::parse($date->format('Y-m-d') . ' 00:00:00', $timezone);

                            $recordData = [
                                'user_id'    => $user->id,
                                'time_in'    => $dateTime,
                                'time_out'   => $dateTime,
                                'status'     => $isShiftDay ? 'Absent' : 'Off',
                                'created_at' => $dateTime,
                                'updated_at' => $dateTime,
                            ];

                            if ($dryRun) {
                                $this->line("[dry-run] Would create for User {$user->id} — {$date->toDateString()} : " . ($isShiftDay ? 'Absent' : 'Off'));
                            } else {
                                // Use transaction per record to be safe (or you can batch them)
                                DB::transaction(function () use ($recordData) {
                                    Time::create($recordData);
                                });
                                $this->info("Created: User {$user->id} → {$date->toDateString()} : " . ($isShiftDay ? 'Absent' : 'Off'));
                            }
                        }

                        $date->addDay();
                    }
                }
            });

        $this->info('✅ Missing attendance filling completed.');
        return 0;
    }
}
