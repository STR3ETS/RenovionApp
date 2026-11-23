<?php

namespace App\Http\Controllers\Os;

use App\Http\Controllers\Controller;
use App\Models\OsFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Foto's en documenten. De app stuurt elk bestand dat in de staat zou
 * belanden (een klusfoto, een bijlage, een handtekening) eerst hierheen en
 * bewaart daarna alleen de link. Gelijke bestanden worden één keer opgeslagen.
 */
class FileController extends Controller
{
    /** Wat er binnen mag: beeld, pdf, office en platte tekst. */
    private const MIMES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic', 'image/svg+xml',
        'application/pdf', 'text/plain', 'text/csv', 'application/json',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip', 'application/octet-stream',
    ];

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:25600'],
            'name' => ['nullable', 'string', 'max:200'],
        ]);

        $upload = $request->file('file');
        $mime = $upload->getMimeType() ?: 'application/octet-stream';
        abort_unless(in_array($mime, self::MIMES, true), 422, 'Dit soort bestand kan niet worden opgeslagen.');

        $hash = hash_file('sha256', $upload->getRealPath());
        $diskPath = 'os/'.substr($hash, 0, 2).'/'.$hash;

        $existing = OsFile::query()->where('disk_path', $diskPath)->first();
        if ($existing) {
            return response()->json(['url' => $existing->url(), 'id' => $existing->id]);
        }

        Storage::disk('local')->putFileAs(dirname($diskPath), $upload, basename($diskPath));

        $file = OsFile::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $request->string('name')->toString() ?: ($upload->getClientOriginalName() ?: 'bestand'),
            'mime' => $mime,
            'size' => $upload->getSize(),
            'disk_path' => $diskPath,
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json(['url' => $file->url(), 'id' => $file->id], 201);
    }

    public function show(Request $request, OsFile $file): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($file->disk_path), 404);

        $inline = str_starts_with($file->mime, 'image/') && $file->mime !== 'image/svg+xml' || $file->mime === 'application/pdf';
        $name = Str::ascii($file->name) ?: 'bestand';

        return Storage::disk('local')->response($file->disk_path, $name, [
            'Content-Type' => $file->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'",
        ], $inline ? 'inline' : 'attachment');
    }
}
