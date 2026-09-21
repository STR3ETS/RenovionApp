<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActionSource;
use App\Enums\LeadStatus;
use App\Enums\TimelineEventType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadIntakeController extends Controller
{
    /**
     * Nieuwe websiteaanvragen komen automatisch als lead binnen.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'service' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);

        $lead = DB::transaction(function () use ($validated) {
            $customer = filled($validated['email'] ?? null)
                ? Customer::firstWhere('email', $validated['email'])
                : null;

            if ($customer === null) {
                $customer = Customer::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'postal_code' => $validated['postal_code'] ?? null,
                    'city' => $validated['city'] ?? null,
                ]);
            } elseif (blank($customer->phone) && filled($validated['phone'] ?? null)) {
                $customer->update(['phone' => $validated['phone']]);
            }

            $lead = $customer->leads()->create([
                'service' => $validated['service'] ?? null,
                'description' => $validated['message'] ?? null,
                'source' => ActionSource::Website,
                'status' => LeadStatus::Nieuw,
            ]);

            $customer->recordEvent(
                TimelineEventType::Aanvraag,
                'Websiteaanvraag: '.($lead->service ?? 'algemeen'),
                $lead->description,
                $lead,
                ActionSource::Website,
            );

            AuditLog::record($lead, 'aangemaakt', [], ['status' => LeadStatus::Nieuw->value], ActionSource::Website);

            return $lead;
        });

        return response()->json([
            'id' => $lead->id,
            'status' => $lead->status->value,
        ], 201);
    }
}
