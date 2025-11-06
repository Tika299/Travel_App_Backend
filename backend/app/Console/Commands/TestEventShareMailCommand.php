<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\EventShareMail;

class TestEventShareMailCommand extends Command
{
    protected $signature = 'mail:test-event-share {email}';
    protected $description = 'Test EventShareMail with detailed logging';

    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("Testing EventShareMail to: {$email}");
        
        // Log trước khi gửi
        Log::info('Starting EventShareMail test', ['email' => $email, 'time' => now()]);
        
        try {
            // Test data giống như trong app
            $eventData = [
                'title' => 'Test Event - Lịch trình du lịch Hà Nội',
                'start' => '2025-08-09 10:00:00',
                'end' => '2025-08-09 12:00:00',
                'description' => 'Đây là event test để kiểm tra chức năng gửi mail từ IPSUM Travel',
                'location' => 'Hà Nội, Việt Nam'
            ];
            
            $senderData = [
                'name' => 'Nguyễn Văn Đại',
                'email' => 'dai1023456@gmail.com'
            ];
            
            Log::info('Event data', $eventData);
            Log::info('Sender data', $senderData);
            
            // Test với EventShareMail
            Mail::to($email)->send(new EventShareMail($eventData, $senderData));
            
            $this->info('EventShareMail sent successfully!');
            Log::info('EventShareMail sent successfully', ['email' => $email, 'time' => now()]);
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            Log::error('EventShareMail test failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'email' => $email,
                'time' => now()
            ]);
        }
        
        $this->info('Check storage/logs/laravel.log for details');
    }
}



