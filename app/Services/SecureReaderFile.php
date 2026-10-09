<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Delivers a private e-book file to the authenticated owner for online
 * reading.
 *
 * Integrity model:
 *  - The path always originates from a trusted Book record — never from a
 *    query parameter, form field, or request body.
 *  - The path is re-validated as staying inside the private storage root
 *    (server-side string safety) before any stream is opened.
 *  - Every request served by the consuming controller is authorized first
 *    (ownership + paid order). This class performs NO authorization itself;
 *    it only turns a validated private path into an HTTP stream.
 *  - The PDF bytes are read from the private local disk after validation and
 *    returned as a normal response for reliable delivery through XAMPP.
 *  - The response is marked `private, no-store` so authorized bytes can
 *    never be reused for another user's session by a shared cache.
 */
class SecureReaderFile
{
    /**
     * Return a PDF inline for the reader. This is capped by the upload
     * validation limit (10 MB) and avoids XAMPP's empty file-stream responses.
     */
    public function pdf(string $path, string $filename): Response
    {
        $this->assertContainedPath($path);

        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            abort(404);
        }

        $size = $disk->size($path);
        $contents = $disk->get($path);

        if ($size === null || ! is_string($contents) || $contents === '') {
            abort(404, 'The stored PDF could not be read.');
        }

        // Clear any output buffers that might corrupt binary data
        while (ob_get_level()) {
            ob_end_clean();
        }

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) $size,
            'Accept-Ranges' => 'bytes',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        $range = request()->header('Range');
        if ($range && preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
            $start = $matches[1] !== '' ? (int) $matches[1] : 0;
            $end = $matches[2] !== '' ? (int) $matches[2] : ($size - 1);
            if ($start < 0 || $end >= $size || $start > $end) {
                return response('', Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE, [
                    'Content-Range' => 'bytes */'.$size,
                ]);
            }
            $chunk = substr($contents, $start, ($end - $start + 1));
            return response($chunk, Response::HTTP_PARTIAL_CONTENT, array_merge($headers, [
                'Content-Range' => 'bytes '.$start.'-'.$end.'/'.$size,
                'Content-Length' => (string) strlen($chunk),
            ]));
        }

        $response = response($contents, Response::HTTP_OK, $headers);
        return $response;
    }

    /**
     * Defense in depth: the path must be a server-stored relative path inside
     * the private storage root. Absolute paths, escaping segments and empty
     * segments are rejected before anything touches the filesystem.
     */
    private function assertContainedPath(string $path): void
    {
        if ($path === '' || $path === null) {
            abort(404);
        }

        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            abort(404);
        }

        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                abort(404);
            }
        }
    }
}
