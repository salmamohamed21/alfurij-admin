<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmail extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = $this->generateVerificationUrl($notifiable);

        return (new MailMessage)
            ->subject('تفعيل البريد الإلكتروني')
            ->greeting('مرحباً ' . $notifiable->name)
            ->line('شكراً لتسجيلك في موقعنا. يرجى الضغط على الزر أدناه لتفعيل بريدك الإلكتروني.')
            ->action('تفعيل البريد الإلكتروني', $verificationUrl)
            ->line('إذا لم تقم بطلب هذا البريد، يمكنك تجاهله.')
            ->salutation('مع خالص التحية، فريق الموقع');
    }

    /**
     * توليد رابط التحقق المخصص
     */
    protected function generateVerificationUrl($notifiable)
    {
        $hash = sha1($notifiable->email);
        return url('/email/verify/' . $notifiable->id . '/' . $hash);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
