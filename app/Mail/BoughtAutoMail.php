<?php

namespace App\Mail;

use App\Models\Vehicle;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BoughtAutoMail extends Mailable
{
    use Queueable, SerializesModels;

    protected string $cause;
    protected Vehicle $vehicle;

    /**
     * Create a new message instance.
     * @param string $cause
     * @param Vehicle $vehicle
     */
    public function __construct(string $cause, Vehicle $vehicle)
    {
        $this->cause = $cause;
        $this->vehicle = $vehicle;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: 'Компанія придбала наступне авто',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'vendor.mail.html.vehicle-bought',
            with: [
                'header' => 'Придбано авто',
                'sub_header' => 'Придбано авто',
                'explanation' => $this->vehicle->title,
                'description1' => 'Рік виготовлення: ' . $this->vehicle->produced,
                'description2' =>  'пробіг: '  . $this->vehicle->mileage,
                'description3' => 'Ціна купівлі: '. $this->vehicle->cost/100,
                'amount' =>  'план продажу:  ' . $this->vehicle->plan_sale/100,
                'go_to' => 'Докладніше на сайті',
                'url' => 'https://ev-invest.segment.best/investor/vehicles'
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
