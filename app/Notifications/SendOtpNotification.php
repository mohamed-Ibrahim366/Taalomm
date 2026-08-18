<?php

namespace App\Notifications;

use App\Enums\OtpPurpose;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $code,
        private readonly OtpPurpose $purpose,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = match ($this->purpose) {
            OtpPurpose::EMAIL_VERIFICATION => 'Verify your Taalom account',
            OtpPurpose::PASSWORD_RESET => 'Reset your Taalom password',
            OtpPurpose::EMAIL_CHANGE => 'Verify your new email address',
        };

        $intro = match ($this->purpose) {
            OtpPurpose::EMAIL_VERIFICATION => 'Use the code below to verify your email address.',
            OtpPurpose::PASSWORD_RESET => 'Use the code below to reset your password.',
            OtpPurpose::EMAIL_CHANGE => 'Use the code below to verify your new email address.',
        };

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.otp', [
                'subject' => $subject,
                'intro' => $intro,
                'code' => $this->code,
                'name' => $notifiable->name ?? null,
            ]);
    }
}
