<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestRealMailCommand extends Command
{
    protected $signature = 'mail:test-real {email}';
    protected $description = 'Test sending real email via SMTP';

    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("Testing real email to: {$email}");
        
        try {
            // Temporarily set mail driver to smtp
            config(['mail.default' => 'smtp']);
            
            // Test data
            $eventData = [
                'title' => 'Test Event - Lịch trình du lịch',
                'start' => '2025-08-09 10:00:00',
                'end' => '2025-08-09 12:00:00',
                'description' => 'Đây là event test để kiểm tra chức năng gửi mail thật',
                'location' => 'Hà Nội, Việt Nam'
            ];
            
            $senderData = [
                'name' => 'Test User',
                'email' => 'test@example.com'
            ];
            
            // Send email using EventShareMail
            Mail::to($email)->send(new \App\Mail\EventShareMail($eventData, $senderData));
            
            $this->info('Real email sent successfully!');
            $this->info('Check your inbox and spam folder.');
            
        } catch (\Exception $e) {
            $this->error('Error sending real email: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            
            // Check if it's an authentication error
            if (strpos($e->getMessage(), 'authentication') !== false) {
                $this->error('This might be an authentication issue. Please check your Gmail app password.');
            }
        }
    }
}



