<?php

namespace App\Support\App;

use App\Enums\UserRole;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\Subcontractor;

/**
 * Lichte badgekleuren voor de bedrijfsschermen van de app (geld, inkoop, mensen en partners). De website
 * houdt de donkere klassen van de modellen; hier dezelfde betekenis in de lichte kleuren van de app.
 *
 * Alleen combinaties die ook in app/Enums staan (appBadgeClasses): de app-build scant die map voor
 * Tailwind (mobile/app/app.css), deze map niet.
 */
class Badges
{
    private const TONES = [
        'gray' => 'bg-gray-200 text-gray-700',
        'muted' => 'bg-gray-100 text-gray-600',
        'blue' => 'bg-steel-100 text-steel-800',
        'brand' => 'bg-brand-100 text-brand-800',
        'amber' => 'bg-amber-100 text-amber-800',
        'green' => 'bg-green-100 text-green-800',
        'red' => 'bg-red-100 text-red-800',
    ];

    public static function tone(string $tone): string
    {
        return self::TONES[$tone] ?? self::TONES['gray'];
    }

    /** @return array{label: string, classes: string} */
    public static function badge(string $label, string $tone): array
    {
        return ['label' => $label, 'classes' => self::tone($tone)];
    }

    public static function purchaseOrder(PurchaseOrder $order): string
    {
        return self::tone(match ($order->status) {
            PurchaseOrder::STATUS_SENT => 'blue',
            PurchaseOrder::STATUS_CONFIRMED => 'brand',
            PurchaseOrder::STATUS_DELIVERED => 'green',
            default => 'gray',
        });
    }

    public static function purchaseInvoice(PurchaseInvoice $invoice): string
    {
        return self::tone(match ($invoice->status) {
            PurchaseInvoice::STATUS_APPROVED => 'blue',
            PurchaseInvoice::STATUS_PAID => 'green',
            PurchaseInvoice::STATUS_DISPUTED => 'red',
            default => 'amber',
        });
    }

    public static function subcontractor(string $status): string
    {
        return self::tone(match ($status) {
            Subcontractor::STATUS_BLOCKED, Subcontractor::STATUS_EXPIRED => 'red',
            Subcontractor::STATUS_INCOMPLETE => 'amber',
            default => 'green',
        });
    }

    /** Staat van een certificaat of dossierstuk (CertificateCatalogue::state). */
    public static function state(string $state): string
    {
        return self::tone(match ($state) {
            'verlopen', 'ontbreekt' => 'red',
            'binnenkort' => 'amber',
            default => 'green',
        });
    }

    /** Het chipje van een geldig certificaat, in de kleur uit CertificateCatalogue::TYPES. */
    public static function chip(string $colour): string
    {
        return self::tone(match ($colour) {
            'brand', 'calc' => 'brand',
            'red' => 'red',
            'green', 'planning', 'klant' => 'green',
            'amber' => 'amber',
            'lead' => 'blue',
            default => 'gray',
        });
    }

    public static function role(UserRole $role): string
    {
        return self::tone(match ($role) {
            UserRole::Sales, UserRole::Inkoper => 'blue',
            UserRole::Calculator, UserRole::Projectleider => 'brand',
            UserRole::Werkvoorbereider => 'amber',
            UserRole::Administratie, UserRole::Klant => 'green',
            UserRole::Uitvoerder => 'muted',
            default => 'gray',
        });
    }

    /** Afwijking tegenover de begroting: rood vanaf vijf procent erboven, oranje zodra het erboven komt. */
    public static function variance(?float $variance): string
    {
        return match (true) {
            $variance === null => self::tone('muted'),
            $variance > 5 => self::tone('red'),
            $variance > 0 => self::tone('amber'),
            default => self::tone('green'),
        };
    }
}
