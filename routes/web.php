<?php

use App\Http\Controllers\Account\OnlineReadingController as AccountOnlineReadingController;
use App\Http\Controllers\Account\OrderController as AccountOrderController;
use App\Http\Controllers\Account\PurchaseController as AccountPurchaseController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AblinerWebhookController;
use App\Http\Controllers\Admin\AuthorController;
use App\Http\Controllers\Admin\BookChapterController;
use App\Http\Controllers\Admin\BookChapterPreviewController;
use App\Http\Controllers\Admin\BookController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PaymentSettingsController;
use App\Http\Controllers\Admin\SalesReportController;
use App\Http\Controllers\Admin\SubscriberController;
use App\Http\Controllers\Admin\WalletController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BookController as StorefrontBookController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController as StorefrontCategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CustomerPaymentController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Public storefront
Route::get('/', [HomeController::class, 'index'])->name('home');
// Serve files from Laravel's public disk when the host cannot create the
// usual public/storage symlink (for example, a restricted Windows setup).
Route::get('/storage/{path}', function (string $path) {
    $disk = Storage::disk('public');

    abort_unless($disk->exists($path), 404);

    return $disk->response($path);
})->where('path', '.*')->name('storage.public');

Route::get('/books', [StorefrontBookController::class, 'index'])->name('books.index');
Route::get('/books/trending', [StorefrontBookController::class, 'trending'])->name('books.trending');
Route::get('/books/{book:slug}', [StorefrontBookController::class, 'show'])->name('books.show');
// Free preview of a single chapter, for visitors who have not bought the book.
// Only chapters an admin explicitly flagged is_free are reachable here, and only
// for a published book.
Route::get('/books/{book:slug}/preview/{chapter}', [StorefrontBookController::class, 'previewChapter'])->name('books.preview');
Route::get('/categories/{category:slug}', [StorefrontCategoryController::class, 'show'])->name('categories.show');

// Abliner callbacks for both customer payments and admin payouts
// (CSRF-exempt, authenticated by signature).
Route::post('/webhooks/abliner', AblinerWebhookController::class)->name('webhooks.abliner');

// Shopping cart (public until checkout)
Route::prefix('cart')->name('cart.')->group(function () {
    Route::get('/', [CartController::class, 'show'])->name('show');
    Route::post('/add', [CartController::class, 'add'])->name('add');
    Route::post('/remove', [CartController::class, 'remove'])->name('remove');
    Route::post('/clear', [CartController::class, 'clear'])->name('clear');
});

// Authentication
//
// Customers sign up and sign in from the storefront auth modal: register.store
// creates the account and logs it in, login.store authenticates an existing one.
// There is no standalone /register page -- the form lives inside the modal. The
// /login page is reserved for administrators and posts to a separate endpoint
// that rejects non-admins.
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/admin/login', [AuthenticatedSessionController::class, 'adminStore'])->name('admin.login.store');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/account', [AccountController::class, 'index'])->name('account.show');

    // Checkout requires an authenticated session.
    Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    // Free books skip the cart and the payment entirely: one POST puts the
    // book straight into the customer's library.
    Route::post('/books/{book:slug}/free', [StorefrontBookController::class, 'claimFree'])
        ->name('books.claim-free');

    // Customer's own order history + payment.
    Route::prefix('account/orders')->name('account.orders.')->group(function () {
        Route::get('/', [AccountOrderController::class, 'index'])->name('index');
        Route::get('/{order}', [AccountOrderController::class, 'show'])->name('show');

Route::prefix('{order}/payment')->name('payments.')->group(function () {
    Route::get('/', [CustomerPaymentController::class, 'show'])->name('show');
    Route::get('/status', [CustomerPaymentController::class, 'status'])->name('status');
    Route::post('/', [CustomerPaymentController::class, 'store'])->name('store');
    Route::post('/refresh', [CustomerPaymentController::class, 'refresh'])->name('refresh');
        });
    });

    // Customer's purchased books + secure downloads.
    Route::prefix('account/purchases')->name('account.purchases.')->group(function () {
        Route::get('/', [AccountPurchaseController::class, 'index'])->name('index');
        Route::get('/{purchase}', [AccountPurchaseController::class, 'show'])->name('show');
        Route::get('/{purchase}/download', [AccountPurchaseController::class, 'download'])->name('download');

        // Online reader: protected reader page, authorized PDF content
        // endpoint (each PDF.js/range request is re-authorized), and the
        // owner-scoped reading progress save. No CSRF exceptions added.
        Route::get('/{purchase}/read', [AccountPurchaseController::class, 'read'])->name('read');
        Route::get('/{purchase}/read-pdf', [AccountPurchaseController::class, 'readPdf'])->name('read-pdf');
        Route::get('/{purchase}/reader-file', [AccountPurchaseController::class, 'readerFile'])->name('reader-file');
        Route::post('/{purchase}/progress', [AccountPurchaseController::class, 'progress'])->name('progress');

        // Native HTML reader. Chapter content, resume points and bookmarks are
        // all reached through the purchase, so ownership is decided by
        // PurchasePolicy rather than by anything the client sends.
        Route::prefix('/{purchase}/online')->name('online.')->group(function () {
            Route::get('/', [AccountOnlineReadingController::class, 'read'])->name('read');
            Route::get('/manifest', [AccountOnlineReadingController::class, 'manifest'])->name('manifest');
            Route::get('/chapters/{chapter}', [AccountOnlineReadingController::class, 'chapter'])->name('chapter');
            Route::post('/progress', [AccountOnlineReadingController::class, 'progress'])->name('progress');
            Route::post('/bookmarks', [AccountOnlineReadingController::class, 'storeBookmark'])->name('bookmarks.store');
            Route::delete('/bookmarks/{bookmark}', [AccountOnlineReadingController::class, 'destroyBookmark'])->name('bookmarks.destroy');
        });
    });

    // Admin area: every route requires authentication + the admin role.
    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('books', BookController::class)->only([
            'index', 'create', 'store', 'edit', 'update', 'destroy',
        ]);

        // Permanent deletion is a separate explicit action from archiving:
        // archive keeps order history resolvable, full delete only removes
        // books nobody has ordered (guard-railed inside the controller).
        Route::delete('books/{book}/force-destroy', [BookController::class, 'forceDestroy'])
            ->name('books.force-destroy');

        // Native-reader chapter management, always nested under a book so a
        // chapter can never be edited through the wrong parent.
        Route::prefix('books/{book}')->name('books.')->group(function () {
            Route::get('chapters', [BookChapterController::class, 'index'])->name('chapters.index');
            Route::get('chapters/create', [BookChapterController::class, 'create'])->name('chapters.create');
            Route::post('chapters', [BookChapterController::class, 'store'])->name('chapters.store');
            Route::post('chapters/reorder', [BookChapterController::class, 'reorder'])->name('chapters.reorder');
            Route::get('chapters/{chapter}/edit', [BookChapterController::class, 'edit'])->name('chapters.edit');
            Route::put('chapters/{chapter}', [BookChapterController::class, 'update'])->name('chapters.update');
            Route::delete('chapters/{chapter}', [BookChapterController::class, 'destroy'])->name('chapters.destroy');
            Route::get('chapters/{chapter}/preview', [BookChapterPreviewController::class, 'show'])->name('chapters.preview');
        });

        Route::resource('authors', AuthorController::class)->only([
            'index', 'create', 'store', 'edit', 'update', 'destroy',
        ]);

        // Restore (archived -> active) and permanent deletion are explicit
        // actions separate from archiving, mirroring the books admin. Restore
        // and force-delete are declared after the resource so the resource's
        // {author} routes never shadow them.
        Route::patch('authors/{author}/restore', [AuthorController::class, 'restore'])
            ->name('authors.restore');
        Route::delete('authors/{author}/force-destroy', [AuthorController::class, 'forceDestroy'])
            ->name('authors.force-destroy');

        Route::resource('categories', CategoryController::class)->only([
            'index', 'create', 'store', 'edit', 'update', 'destroy',
        ]);

        Route::patch('categories/{category}/restore', [CategoryController::class, 'restore'])
            ->name('categories.restore');
        Route::delete('categories/{category}/force-destroy', [CategoryController::class, 'forceDestroy'])
            ->name('categories.force-destroy');

        // Customer account management: the admin can see who the store's
        // customers are and remove registrations that never engaged. Deleting
        // is guarded in the controller so an account with any order history
        // can never be wiped (order + purchase records resolve through it).
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/', [AdminOrderController::class, 'index'])->name('index');
            // Each sales view is a real page with its own URL, so the sidebar
            // can link straight to the queue an administrator cares about.
            Route::get('/pending', [AdminOrderController::class, 'pending'])->name('pending');
            Route::get('/paid', [AdminOrderController::class, 'paid'])->name('paid');
            // Bulk deletion of the pending queue. Declared before the {order}
            // routes so a batch action is never read as an order id.
            Route::delete('/bulk-destroy', [AdminOrderController::class, 'bulkDestroy'])->name('bulk-destroy');
            // Deleting orders is allowed for pending only; the controller itself
            // re-checks and refuses anything that already carries a payment.
            Route::delete('/{order}', [AdminOrderController::class, 'destroy'])->name('destroy');
            Route::get('/{order}', [AdminOrderController::class, 'show'])->name('show');
        });

        // The newsletter mailing list. Every write is a POST/PATCH/DELETE under
        // the existing auth + admin middleware and the CSRF field, matching how
        // the rest of the admin area is protected.
        Route::prefix('subscribers')->name('subscribers.')->group(function () {
            Route::get('/', [SubscriberController::class, 'index'])->name('index');
            Route::post('/', [SubscriberController::class, 'store'])->name('store');
            Route::patch('/{subscriber}/status', [SubscriberController::class, 'updateStatus'])
                ->name('status');
            Route::delete('/{subscriber}', [SubscriberController::class, 'destroy'])->name('destroy');
        });

        // Stage 10: sales and revenue reporting, derived only from paid orders
        // and always scoped to one validated, timezone-aware date range.
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/sales', [SalesReportController::class, 'index'])->name('sales');
            Route::get('/sales/export', [SalesReportController::class, 'export'])->name('sales.export');
        });

        // Stage 10.1: administration of the Abliner configuration. Credentials
        // are stored encrypted with APP_KEY in `payment_settings`; every write
        // is a POST under the existing auth + admin middleware and the CSRF
        // field, matching how the rest of the admin area is protected.
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/payments', [PaymentSettingsController::class, 'index'])->name('payments');
            Route::post('/payments', [PaymentSettingsController::class, 'update'])->name('payments.update');
            Route::post('/payments/connection', [PaymentSettingsController::class, 'testConnection'])
                ->name('payments.connection');
            Route::post('/payments/clear/{field}', [PaymentSettingsController::class, 'clear'])
                ->name('payments.clear');
            Route::post('/payments/reset', [PaymentSettingsController::class, 'reset'])
                ->name('payments.reset');
            Route::match(['get', 'post'], '/payments/reveal/{field}', [PaymentSettingsController::class, 'reveal'])
                ->name('payments.reveal');
        });

        // The store's Abliner wallet: live balance, live transaction history,
        // and payouts. The fee quote is read from the provider (GET
        // /withdrawals/preview) and the payout itself is confirmed in the UI
        // before it is sent, because it moves real money irreversibly.
        Route::prefix('wallet')->name('wallet.')->group(function () {
            Route::get('/', [WalletController::class, 'index'])->name('index');
            Route::post('/withdrawals/preview', [WalletController::class, 'preview'])->name('withdrawals.preview');
            Route::post('/withdrawals', [WalletController::class, 'store'])->name('withdrawals.store');
            Route::post('/withdrawals/{withdrawal}/refresh', [WalletController::class, 'refresh'])
                ->name('withdrawals.refresh');
        });
    });
});
