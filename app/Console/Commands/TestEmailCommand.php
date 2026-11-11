<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmailCommand extends Command
{
    protected $signature = 'test:email {--email=mohamedsalihusyed@gmail.com}';

    protected $description = 'Test email sending with current SMTP configuration';

    public function handle()
    {
        $testEmail = $this->option('email');

        $this->info('Testing email configuration...');
        $this->info('');
        $this->info('Email Provider: '.config('mail.mailer'));
        $this->info('From Address: '.config('mail.from.address'));
        $this->info('SMTP Host: '.config('mail.mailers.smtp.host'));
        $this->info('SMTP Port: '.config('mail.mailers.smtp.port'));
        $this->info('Encryption: '.config('mail.mailers.smtp.encryption'));
        $this->info('');
        $this->info('Attempting to send test email to: '.$testEmail);
        $this->info('');

        try {
            Mail::raw('This is a test email from Field Visit application.', function ($message) use ($testEmail) {
                $message->to($testEmail)
                    ->subject('Test Email - Field Visit');
            });

            $this->info('✅ SUCCESS! Email sent successfully.');
            $this->info('Check your inbox or spam folder for the test email.');

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ FAILED! Email could not be sent.');
            $this->error('');
            $this->error('Error: '.$e->getMessage());
            $this->error('');
            $this->warn('Troubleshooting tips:');
            $this->warn('1. Verify .env file has correct MAIL_USERNAME and MAIL_PASSWORD');
            $this->warn('2. For Gmail with 2FA, use App Password instead of Gmail password');
            $this->warn('3. Run: php artisan config:clear');
            $this->warn('4. Check firewall/port 587 is not blocked');

            return 1;
        }
    }
}
