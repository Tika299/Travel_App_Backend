<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Schedule;

class CheckSchedulesCommand extends Command
{
    protected $signature = 'check:schedules';
    protected $description = 'Check schedules in database';

    public function handle()
    {
        $this->info('Checking schedules in database...');
        
        $totalSchedules = Schedule::count();
        $this->info("Total schedules: {$totalSchedules}");
        
        $schedulesWithUser = Schedule::whereNotNull('user_id')->count();
        $this->info("Schedules with user_id: {$schedulesWithUser}");
        
        $schedulesFromToday = Schedule::where('start_date', '>=', now()->format('Y-m-d'))->count();
        $this->info("Schedules from today: {$schedulesFromToday}");
        
        $this->info('Sample schedules:');
        $schedules = Schedule::take(5)->get(['id', 'user_id', 'name', 'start_date', 'end_date']);
        
        foreach ($schedules as $schedule) {
            $this->line("ID: {$schedule->id}, User: {$schedule->user_id}, Name: {$schedule->name}, Start: {$schedule->start_date}");
        }
        
        $this->info('Done!');
    }
}



