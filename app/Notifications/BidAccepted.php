<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BidAccepted extends Notification implements ShouldQueue
{
    use Queueable;

    protected $auctionTitle;
    protected $bidAmount;

    /**
     * Create a new notification instance.
     */
    public function __construct($auctionTitle, $bidAmount)
    {
        $this->auctionTitle = $auctionTitle;
        $this->bidAmount = $bidAmount;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('تم قبول مزايدتك!')
            ->line("تم قبول مزايدتك على {$this->auctionTitle}")
            ->line("قيمة المزايدة: {$this->bidAmount} ريال")
            ->action('عرض التفاصيل', url('/profile/bid-history'))
            ->line('شكراً لاستخدامك تطبيقنا!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'تم قبول مزايدتك',
            'message' => "تم قبول مزايدتك على {$this->auctionTitle} بقيمة {$this->bidAmount} ريال",
            'type' => 'bid',
            'auction_title' => $this->auctionTitle,
            'bid_amount' => $this->bidAmount,
        ];
    }
}
