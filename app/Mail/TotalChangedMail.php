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
    protected string $cause;
    protected int $amount;

    /**
     * Create a new message instance.
     * @param float|int $total
     */
    public function __construct(float|int $total, string $cause, int $amount)
    {
        $this->total = $total;
        $this->cause = $cause;
        $this->amount = $amount;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: 'Змінився розмір інвестиційного пулу',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'vendor.mail.html.pool-changed',
            with: [
                'header' => 'Зміни в інвест пулу',
                'sub_header' => 'Інвест пул змінився',
                'explanation' => 'Нова сума пулу, $: ',
                'total' => number_format($this->total, 2, ',', ' '),
                'description1' => 'Причина зміни: ',
                'description2' => $this->cause,
                'amount' => $this->amount,
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
