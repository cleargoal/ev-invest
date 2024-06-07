<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TotalChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    protected int|float $total;

    /**
     * Create a new message instance.
     * @param int|float $total
     */
    public function __construct($total)
    {
        $this->total = $total;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), 'Freedom Auto інвест калькулятор'),
            subject: 'Змінився розмір інвестиційного пулу',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'vendor.mail.html.layout',
            with: [
                'header' => 'Зміни в інвест пулу',
                'sub_header' => 'Інвест пул змінився',
                'explanation' => 'Нова сума пулу, $:',
                'total' => number_format($this->total, 2, ',', ' '),
                'go_to' => 'Докладніше на сайті',
                'url' => 'https://ev-invest.segment.best/investor'
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
