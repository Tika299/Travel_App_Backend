<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TestMailWithLogCommand extends Command
{
    protected $signature = 'mail:test-log {email}';
    protected $description = 'Test email with detailed logging';

    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("Testing email with detailed logging to: {$email}");
        
        // Log trước khi gửi
        Log::info('Starting mail test', ['email' => $email, 'time' => now()]);
        
        try {
            // Kiểm tra cấu hình mail
            $mailConfig = config('mail');
            Log::info('Mail config', $mailConfig);
            
            // Test với mail đơn giản
            Mail::raw('Test email với log chi tiết - ' . now(), function($message) use ($email) {
                $message->to($email)
                        ->subject('Test Email với Log - ' . now())
                        ->from('nguyenvandai896@gmail.com', 'Test User');
            });
            
            $this->info('Email sent successfully!');
            Log::info('Mail sent successfully', ['email' => $email, 'time' => now()]);
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            Log::error('Mail test failed', [
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



