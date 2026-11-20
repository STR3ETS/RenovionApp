<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InboundMail;
use App\Services\AttentionService;
use App\Services\InboundMailReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Hier levert de maildienst een bericht af dat op aanvraag@ binnenkwam.
 *
 * Twee vormen worden begrepen: de eigen, eenvoudige vorm (from, subject, text)
 * en die van Postmark (From, Subject, TextBody). Hetzelfde bericht twee keer
 * afleveren geeft één bericht in de aanvraagmail.
 */
class InboundMailWebhookController extends Controller
{
    public function __invoke(Request $request, InboundMailReader $reader): JsonResponse
    {
        $data = [
            'from_email' => $request->input('from') ?? $request->input('FromFull.Email') ?? $request->input('From'),
            'from_name' => $request->input('from_name') ?? $request->input('FromName'),
            'subject' => $request->input('subject') ?? $request->input('Subject'),
            'body' => $request->input('text') ?? $request->input('TextBody') ?? strip_tags((string) ($request->input('html') ?? $request->input('HtmlBody'))),
            'message_id' => $request->input('message_id') ?? $request->input('MessageID'),
        ];

        // "Naam <adres>" komt soms in één veld binnen.
        if (is_string($data['from_email']) && preg_match('/^(.*?)<([^>]+)>$/', trim($data['from_email']), $match) === 1) {
            $data['from_name'] ??= trim($match[1], " \t\"'");
            $data['from_email'] = trim($match[2]);
        }

        $validated = validator($data, [
            'from_email' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:50000'],
            'message_id' => ['nullable', 'string', 'max:255'],
        ])->validate();

        if (filled($validated['message_id'] ?? null)) {
            $existing = InboundMail::firstWhere('message_id', $validated['message_id']);

            if ($existing !== null) {
                return response()->json(['id' => $existing->id, 'duplicate' => true]);
            }
        }

        $mail = InboundMail::create([...$validated, 'source' => InboundMail::SOURCE_MAIL, 'received_at' => now()]);
        $mail->update(['extracted' => $reader->read($mail)]);

        AttentionService::forgetCount();

        return response()->json(['id' => $mail->id], 201);
    }
}
