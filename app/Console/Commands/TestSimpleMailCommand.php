<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestSimpleMailCommand extends Command
{
    protected $signature = 'mail:test-simple {email}';
    protected $description = 'Test simple email sending';

    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("Testing simple email to: {$email}");
        
        try {
            // Test với mail đơn giản
            Mail::raw('Test email từ Laravel - ' . now(), function($message) use ($email) {
                $message->to($email)
                        ->subject('Test Email - ' . now())
                        ->from('nguyenvandai896@gmail.com', 'Test User');
            });
            
            $this->info('Simple email sent successfully!');
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->error('File: ' . $e->getFile() . ':' . $e->getLine());
            
            // Log chi tiết
            \Log::error('Mail test failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}



