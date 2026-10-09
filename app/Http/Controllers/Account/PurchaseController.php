<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\DownloadLog;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\ReadingProgress;
use App\Services\PurchaseService;
use App\Services\SecureReaderFile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Customer "My Purchases" area + secure e-book downloads.
 *
 * The customer can only ever reach a purchase (or its file) that they own:
 * the routes require authentication, the PurchasePolicy enforces ownership,
 * and the order must be genuinely PAID. The file itself lives on the private
 * `local` disk (storage/app/private) and is only ever streamed through this
 * controller after every check passes.
 */
class PurchaseController extends Controller
{
    public function __construct(
        private readonly PurchaseService $purchases,
        private readonly SecureReaderFile $reader,
        private readonly OnlineReadingController $onlineReading,
    ) {}

    /**
     * The authenticated customer's purchased books.
     */
    public function index(): View
    {
        $user = auth()->user();

        // Reconcile: any legitimately paid order (already confirmed before
        // Stage 8 shipped, or confirmed moments ago) must surface as an
        // entitlement. createFromPaidOrder is idempotent and cheap.
        foreach ($user->orders()->where('status', Order::STATUS_PAID)->get() as $order) {
            $this->purchases->createFromPaidOrder($order);
        }

        $purchases = $user->purchases()
            ->with(['book.authors', 'order'])
            ->latest('purchased_at')
            ->get();

        return view('account.purchases.index', ['purchases' => $purchases]);
    }

    /**
     * Details for a single purchased book.
     */
    public function show(Purchase $purchase): View
    {
        $this->authorize('view', $purchase);

        $this->purchases->createFromPaidOrder($purchase->order);

        $purchase->load(['book.authors', 'order']);

        return view('account.purchases.show', ['purchase' => $purchase]);
    }

    /**
     * Stream the purchased book's private file to the owning customer.
     *
     * Every gate is checked before the file leaves the private disk:
     * authentication (route middleware), ownership (PurchasePolicy), a
     * genuinely paid order, an existing book record, and a file that exists
     * inside the private storage root. The download name and MIME type are
     * derived server-side from trusted Book metadata.
     */
    public function download(Purchase $purchase): Response
    {
        $this->authorize('download', $purchase);

        $this->purchases->createFromPaidOrder($purchase->order);

        if (! $purchase->order->isPaid()) {
            abort(403, 'This purchase is not associated with a paid order.');
        }

        $book = $purchase->book;
        $path = $book->file_path;

        // The file path always comes from the trusted Book record; there is
        // no user-supplied path parameter anywhere in this flow.
        if ($path === null || $path === '') {
            return $this->unavailable($purchase);
        }

        if (! $this->fileExists($path)) {
            return $this->unavailable($purchase);
        }

        $this->recordDownload($purchase);

        return Storage::disk('local')->download(
            $path,
            $this->downloadFilename($book),
            $this->mimeHeader($book, $path),
        );
    }

    /**
     * Open the e-book reader for a purchased book.
     *
     * A book that ships native chapters opens the HTML reader; anything else
     * falls through to the PDF.js reader. Both paths enforce the identical
     * trust model: authenticated owner (PurchasePolicy) + genuinely paid order
     * + content that actually exists. No page ever references a private
     * filesystem path.
     */
    public function read(Purchase $purchase): View|RedirectResponse
    {
        $this->authorize('read', $purchase);

        $this->purchases->createFromPaidOrder($purchase->order);

        if (! $purchase->order->isPaid()) {
            abort(403, 'This purchase is not associated with a paid order.');
        }

        if ($purchase->book->hasOnlineReading()) {
            return $this->onlineReading->read($purchase);
        }

        return $this->readPdf($purchase);
    }

    /**
     * Force the PDF.js reader. Dual-format books reach this from a "read the
     * PDF instead" link so a customer can always use the downloaded file.
     */
    public function readPdf(Purchase $purchase): View|RedirectResponse
    {
        $this->authorize('read', $purchase);

        $this->purchases->createFromPaidOrder($purchase->order);

        if (! $purchase->order->isPaid()) {
            abort(403, 'This purchase is not associated with a paid order.');
        }

        $book = $purchase->book;

        if ($book === null || $book->file_path === null || $book->file_path === '') {
            return $this->readerUnavailable($purchase);
        }

        if (strtolower((string) $book->file_type) !== 'pdf') {
            return redirect()->route('account.purchases.show', $purchase)
                ->with('error', 'Online reading is currently available for PDF books. You can still download this book.');
        }

        if (! $this->fileExists($book->file_path)) {
            return $this->readerUnavailable($purchase);
        }

        $purchase->loadMissing(['book.authors']);

        $lastPage = (int) ReadingProgress::where('purchase_id', $purchase->id)->value('current_page');

        return view('account.purchases.reader', [
            'purchase' => $purchase,
            'initialPage' => max(1, $lastPage),
        ]);
    }

    /**
     * Authorized PDF content endpoint used by the reader.
     *
     * Every PDF.js request (including its range requests) passes the same
     * authorization: ownership + paid order + PDF type + existing private
     * file. No filesystem path is ever exposed; the stream is derived solely
     * from the trusted Book record.
     */
    public function readerFile(Purchase $purchase): Response
    {
        $this->authorize('read', $purchase);

        $this->purchases->createFromPaidOrder($purchase->order);

        if (! $purchase->order->isPaid()) {
            abort(403, 'This purchase is not associated with a paid order.');
        }

        $book = $purchase->book;

        if ($book === null) {
            abort(404, 'This e-book is not available.');
        }

        if (strtolower((string) $book->file_type) !== 'pdf') {
            abort(404, 'Online reading is only supported for PDF books.');
        }

        $path = $book->file_path;

        if ($path === null || $path === '') {
            abort(404, 'This e-book is not available.');
        }

        if (! $this->fileExists($path)) {
            abort(404, 'This e-book is not available.');
        }

        return $this->reader->pdf($path, $this->downloadFilename($book));
    }

    /**
     * Persist the last page the customer reached in their own purchased book.
     */
    public function progress(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorize('progress', $purchase);

        $this->purchases->createFromPaidOrder($purchase->order);

        if (! $purchase->order->isPaid()) {
            abort(403, 'This purchase is not associated with a paid order.');
        }

        $validated = $request->validate([
            'current_page' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        ReadingProgress::updateOrCreate(
            ['purchase_id' => $purchase->id],
            [
                'user_id' => $purchase->user_id,
                'book_id' => $purchase->book_id,
                'current_page' => (int) $validated['current_page'],
            ],
        );

        return response()->json(['saved' => true]);
    }

    /**
     * Friendly error when a readable book is currently unavailable.
     */
    private function readerUnavailable(Purchase $purchase): RedirectResponse
    {
        return redirect()->route('account.purchases.show', $purchase)
            ->with('error', "We're sorry, this e-book is currently unavailable. Please check again later.");
    }

    /**
     * Friendly response when the file is missing/corrupt — never leak the
     * filesystem path.
     */
    private function unavailable(Purchase $purchase): RedirectResponse
    {
        return redirect()->route('account.purchases.index')
            ->with('error', "We're sorry, this file is temporarily unavailable. Please contact support.");
    }

    private function fileExists(string $path): bool
    {
        try {
            return Storage::disk('local')->exists($path);
        } catch (\Throwable) {
            return false;
        }
    }

    private function recordDownload(Purchase $purchase): void
    {
        DownloadLog::create([
            'user_id' => $purchase->user_id,
            'purchase_id' => $purchase->id,
            'book_id' => $purchase->book_id,
            'downloaded_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
        ]);
    }

    /**
     * Server-side MIME for the response, derived from the stored file and
     * the trusted book file_type metadata. Never trusts the browser.
     */
    private function mimeHeader(Book $book, string $path): array
    {
        $mime = null;

        try {
            $detected = Storage::disk('local')->mimeType($path);
            if ($detected !== null && $detected !== 'application/octet-stream') {
                $mime = $detected;
            }
        } catch (\Throwable) {
            $mime = null;
        }

        $mime ??= match (strtolower((string) $book->file_type)) {
            'epub' => 'application/epub+zip',
            'mobi' => 'application/x-mobipocket-ebook',
            default => 'application/pdf',
        };

        return ['Content-Type' => $mime];
    }

    /**
     * Clean, sanitized download filename built from the trusted book title —
     * no path separators can survive the character filter.
     */
    private function downloadFilename(Book $book): string
    {
        $base = trim(preg_replace('/[^A-Za-z0-9]+/u', '-', (string) $book->title), '-');
        $base = $base !== '' ? $base : 'ebook';

        $extension = strtolower(preg_replace('/[^a-z0-9]/', '', (string) $book->file_type));
        $extension = $extension !== '' ? $extension : 'pdf';

        return $base.'.'.$extension;
    }
}
