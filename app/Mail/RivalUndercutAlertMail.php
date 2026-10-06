<?php

namespace App\Mail;

use App\Models\Product;
use App\Models\RivalSnapshot;
use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RivalUndercutAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Shop $shop,
        public Product $product,
        public RivalSnapshot $snapshot,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'هشدار: رقیب ارزان‌تر از شما — '.$this->product->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.alerts.rival-undercut',
        );
    }
}
