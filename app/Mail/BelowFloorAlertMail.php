<?php

namespace App\Mail;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BelowFloorAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Shop $shop,
        public Product $product,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'هشدار: قیمت زیر کف هزینه — '.$this->product->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.alerts.below-floor',
        );
    }
}
