<?php

namespace App\Mail;

use App\Models\User;
use App\Os\Invites;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

/**
 * De uitnodiging bij een nieuw account: een link om een eigen wachtwoord te
 * kiezen en in te loggen. Klanten in de u-vorm over hun klantportaal,
 * partners over het partnerportaal, het team over Renovion Groep.
 */
class AccountInvitation extends Mailable
{
    public function __construct(
        public User $account,
        public string $link,
        public string $kind = 'team',
        public bool $again = false,
        public ?string $project = null,
        public ?string $by = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match (true) {
            $this->again && $this->kind === 'klant' => 'Een nieuwe link voor uw klantportaal van Renovion Groep BV',
            $this->again => 'Een nieuwe inloglink voor Renovion Groep BV',
            $this->kind === 'klant' => 'Uw klantportaal van Renovion Groep BV staat klaar',
            $this->kind === 'partner' => 'Je toegang tot het partnerportaal van Renovion Groep BV',
            default => 'Je account bij Renovion Groep BV',
        };

        return new Envelope(
            from: new Address((string) config('mail.from.address'), (string) config('services.renovion.mail_name', 'Renovion Groep BV')),
            replyTo: array_filter([config('services.renovion.reply_to')]),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.invitation',
            text: 'mail.invitation-text',
            with: [
                'naam' => (string) $this->account->name,
                'voornaam' => Str::before((string) $this->account->name, ' '),
                'email' => (string) $this->account->email,
                'loginUrl' => route('login'),
                'dagen' => Invites::DAYS,
                'onderwerp' => $this->envelope()->subject,
            ],
        );
    }
}
