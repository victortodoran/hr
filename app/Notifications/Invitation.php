<?php

namespace App\Notifications;

use App\Models\Employee;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class Invitation extends Notification
{
    public function __construct(public readonly string $token) {}

    /** @return array<int, string> */
    public function via(Employee $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(Employee $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('You have been invited to :app', ['app' => config('app.name')]))
            ->line(__('An account has been created for you. Set a password to get started.'))
            ->action(__('Set password'), route('invitation.show', [
                'token' => $this->token,
                'email' => $notifiable->email,
            ]))
            ->line(__('This link expires in 7 days.'));
    }
}
