<?php

namespace App\Mail;

use App\Models\Item;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LowStockAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Item $item)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                '[%s] Low Stock Alert: %s',
                $this->item->branch?->name ?? 'Branch',
                $this->item->name
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.low-stock-alert',
        );
    }
}
