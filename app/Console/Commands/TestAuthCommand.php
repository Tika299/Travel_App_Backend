<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Schedule;
use App\Models\User;

class TestAuthCommand extends Command
{
    protected $signature = 'test:auth {user_id?}';
    protected $description = 'Test authentication with specific user ID';

    public function handle()
    {
        $userId = $this->argument('user_id') ?? 7; // Default to user 7
        
        $this->info("Testing with user ID: {$userId}");
        
        // Kiểm tra user có tồn tại không
        $user = User::find($userId);
        if (!$user) {
            $this->error("User {$userId} not found!");
            return;
        }
        
        $this->info("User found: {$user->name} ({$user->email})");
        
        // Kiểm tra schedules của user
        $schedules = Schedule::where('user_id', $userId)->get();
        $this->info("Total schedules for user {$userId}: {$schedules->count()}");
        
        $schedulesFromToday = Schedule::where('user_id', $userId)
            ->where('start_date', '>=', now()->format('Y-m-d'))
            ->get();
        $this->info("Schedules from today for user {$userId}: {$schedulesFromToday->count()}");
        
        foreach ($schedulesFromToday as $schedule) {
            $this->line("- ID: {$schedule->id}, Name: {$schedule->name}, Start: {$schedule->start_date}");
        }
        
        $this->info('Done!');
    }
}



