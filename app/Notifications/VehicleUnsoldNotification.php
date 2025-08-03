<?php

namespace App\Notifications;

use App\Models\Vehicle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VehicleUnsoldNotification extends Notification
{
    use Queueable;

    protected Vehicle $vehicle;
    protected ?string $reason;

    /**
     * Create a new notification instance.
     */
    public function __construct(Vehicle $vehicle, ?string $reason = null)
    {
        $this->vehicle = $vehicle;
        $this->reason = $reason;
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
        return (new MailMessage)
            ->subject('Скасовано продаж авто')
            ->view('vendor.mail.html.vehicle-unsold', [
                'header' => 'Скасовано продаж авто',
                'sub_header' => 'Скасовано продаж авто', 
                'explanation' => $this->vehicle->title,
                'description1' => 'Рік виготовлення: ' . $this->vehicle->produced,
                'description2' => 'пробіг: ' . $this->vehicle->mileage,
                'description3' => 'Причина скасування: ' . ($this->reason ?: 'Не вказано'),
                'amount' => 'Авто повернуто в статус "на продаж"',
                'go_to' => 'Докладніше на сайті',
                'url' => 'https://ev-invest.segment.best/investor/vehicles'
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'vehicle_id' => $this->vehicle->id,
            'vehicle_title' => $this->vehicle->title,
            'reason' => $this->reason,
            'action' => 'vehicle_unsold'
        ];
    }
}
