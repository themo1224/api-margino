<?php

namespace App\Mail;

use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CostProfileStaleAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Shop $shop,
        public int $staleDays,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'هشدار: پروفایل هزینه قدیمی است — '.$this->shop->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.alerts.cost-stale',
        );
    }
}
