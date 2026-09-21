<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RequestPhoneNumberMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Lead $lead)
    {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bedankt voor je aanvraag bij Renovion',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.request-phone',
            with: [
                'firstName' => str($this->lead->customer->name)->replace(['Familie ', 'Dhr. ', 'Mevr. '], '')->before(' ')->toString(),
            ],
        );
    }
}
