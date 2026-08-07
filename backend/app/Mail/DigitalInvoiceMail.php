<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DigitalInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public Order $order;

    public PaymentTransaction $transaction;

    public function __construct(Order $order, PaymentTransaction $transaction)
    {
        $this->order = $order;
        $this->transaction = $transaction;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Digital Invoice - Restaurant',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.digital-invoice',
            with: [
                'order' => $this->order,
                'transaction' => $this->transaction,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
