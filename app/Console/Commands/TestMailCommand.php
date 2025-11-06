<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\EventShareMail;

class TestMailCommand extends Command
{
    protected $signature = 'mail:test {email}';
    protected $description = 'Test sending email to specified address';

    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("Testing email to: {$email}");
        
        try {
            // Test data
            $eventData = [
                'title' => 'Test Event - Lịch trình du lịch',
                'start' => '2025-08-09 10:00:00',
                'end' => '2025-08-09 12:00:00',
                'description' => 'Đây là event test để kiểm tra chức năng gửi mail',
                'location' => 'Hà Nội, Việt Nam'
            ];
            
            $senderData = [
                'name' => 'Test User',
                'email' => 'test@example.com'
            ];
            
            // Send email
            Mail::to($email)->send(new EventShareMail($eventData, $senderData));
            
            $this->info('Email sent successfully!');
            $this->info('Check your inbox and spam folder.');
            
        } catch (\Exception $e) {
            $this->error('Error sending email: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}



