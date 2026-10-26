<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Rich-text in offerteblokken (Eazyonline-concept): de builder bewerkt velden
 * met een contenteditable en lichte opmaak (vet, groter/kleiner, lijstjes uit
 * geplakte tekst). clean() saneert die HTML server-side bij het opslaan;
 * render() maakt hem veilig zichtbaar in de klantweergave.
 */
class QuoteText
{
    /**
     * Saneer rich-text uit de builder naar veilige HTML: alleen structuur- en
     * opmaak-tags, geen event-handlers, geen javascript:-links en alleen
     * font-size/font-weight als inline-stijl.
     */
    public static function clean(?string $html): string
    {
        $html = (string) $html;

        if (trim($html) === '') {
            return '';
        }

        $html = strip_tags($html, '<p><br><div><h1><h2><h3><h4><ul><ol><li><strong><b><em><i><u><s><span><a>');

        // Event-handlers en javascript:-verwijzingen weghalen.
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/(href|src)\s*=\s*("\s*javascript:[^"]*"|\'\s*javascript:[^\']*\')/i', '', $html) ?? $html;

        // style="" beperken tot font-size en font-weight.
        $html = preg_replace_callback('/\sstyle\s*=\s*"([^"]*)"/i', function (array $match) {
            $keep = [];
            foreach (explode(';', $match[1]) as $rule) {
                if (preg_match('/^\s*(font-size|font-weight)\s*:\s*[a-z0-9.%\s]+$/i', $rule)) {
                    $keep[] = trim($rule);
                }
            }

            return $keep === [] ? '' : ' style="'.implode('; ', $keep).'"';
        }, $html) ?? $html;

        // Alle attributen behalve href (op <a>) en de gesaneerde style strippen.
        $html = preg_replace_callback('/<([a-z][a-z0-9]*)((?:\s[^<>]*)?)>/i', function (array $match) {
            $tag = strtolower($match[1]);
            $attributes = '';

            if ($tag === 'a' && preg_match('/href\s*=\s*"([^"]*)"/i', $match[2], $href)
                && preg_match('#^\s*(https?:|mailto:|tel:|/|\#)#i', $href[1])) {
                $attributes = ' href="'.e($href[1]).'" target="_blank" rel="noopener noreferrer"';
            }

            if (preg_match('/\sstyle="[^"]*"/i', $match[2], $style)) {
                $attributes .= $style[0];
            }

            return '<'.$tag.$attributes.'>';
        }, $html) ?? $html;

        return $html;
    }

    /**
     * Veilige weergave in document en klantview: HTML uit de builder wordt
     * (nogmaals) gesaneerd, platte tekst wordt ge-escaped met regeleindes
     * als <br>.
     */
    public static function render(?string $value): HtmlString
    {
        $value = (string) $value;

        if (preg_match('/<[a-z!\/][\s\S]*>/i', $value) === 1) {
            return new HtmlString(self::clean($value));
        }

        return new HtmlString(nl2br(e($value)));
    }

    /**
     * Platte tekst uit een (mogelijk) rich veld, voor samenvattingen en AI-context.
     */
    public static function plain(?string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }
}
