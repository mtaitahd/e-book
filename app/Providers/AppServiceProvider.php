<?php

namespace App\Providers;

use App\Events\OrderPaid;
use App\Events\PaymentUnsuccessful;
use App\Listeners\SendPaymentUnsuccessfulNotice;
use App\Listeners\SendPurchaseReceipt;
use App\Models\Category;
use App\Models\Purchase;
use App\Models\User;
use App\Policies\PurchasePolicy;
use App\Services\Abliner\AblinerApiClient;
use App\Services\Abliner\AblinerControlNumberService;
use App\Services\Abliner\AblinerPaymentService;
use App\Services\Abliner\AblinerSignatureVerifier;
use App\Services\Abliner\AblinerWalletService;
use App\Services\Abliner\AblinerWebhookService;
use App\Services\Abliner\AblinerWithdrawalService;
use App\Services\PurchaseService;
use App\Settings\PaymentProviderConfig;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped, not singleton: the provider configuration can be changed by an
        // administrator from the payment settings page, so it must be re-read
        // per request rather than captured once per process. Under FPM this is
        // identical to a singleton; under a persistent worker it is the
        // difference between picking up a rotated credential and silently
        // signing with the old one.
        $this->app->scoped(PaymentProviderConfig::class);

        $this->app->scoped(AblinerSignatureVerifier::class, fn ($app) => new AblinerSignatureVerifier(
            $app->make(PaymentProviderConfig::class),
        ));

        // One client owns the base URL, the bearer token, request signing, the
        // retry policy and error mapping, so every service below authenticates
        // in exactly the same way.
        $this->app->scoped(AblinerApiClient::class, fn ($app) => new AblinerApiClient(
            $app->make(PaymentProviderConfig::class),
            $app->make(AblinerSignatureVerifier::class),
        ));

        $this->app->scoped(AblinerPaymentService::class, fn ($app) => new AblinerPaymentService(
            $app->make(AblinerApiClient::class),
            $app->make(PaymentProviderConfig::class),
        ));

        $this->app->scoped(AblinerControlNumberService::class, fn ($app) => new AblinerControlNumberService(
            $app->make(AblinerApiClient::class),
            $app->make(AblinerPaymentService::class),
        ));

        $this->app->scoped(AblinerWalletService::class, fn ($app) => new AblinerWalletService(
            $app->make(AblinerApiClient::class),
        ));

        $this->app->scoped(AblinerWithdrawalService::class, fn ($app) => new AblinerWithdrawalService(
            $app->make(AblinerApiClient::class),
            $app->make(AblinerPaymentService::class),
        ));

        $this->app->scoped(AblinerWebhookService::class, fn ($app) => new AblinerWebhookService(
            $app->make(AblinerPaymentService::class),
            $app->make(PaymentProviderConfig::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('access-admin', fn (User $user) => $user->isAdmin());

        Gate::policy(Purchase::class, PurchasePolicy::class);

        // Purchase creation is bootstrapped on demand; bind explicitly so the
        // Order::saved observer resolves through the container.
        $this->app->singleton(PurchaseService::class);

        // Stage 9 customer email. Both listeners defer delivery to after commit,
        // so a rollback never produces a receipt, and both are idempotent via
        // the unique (order_id, type) ledger. Registered explicitly rather than
        // by convention so the wiring is visible where the rest of the
        // application services are bound.
        Event::listen(OrderPaid::class, SendPurchaseReceipt::class);
        Event::listen(PaymentUnsuccessful::class, SendPaymentUnsuccessfulNotice::class);

        Paginator::useBootstrapFour();

        // Storefront navigation data shared with the marketplace header.
        View::composer(['layouts.storefront.app', 'layouts.customer.app'], function ($view) {
            $view->with([
                'navCategories' => Category::query()
                    ->where('status', Category::STATUS_ACTIVE)
                    ->withCount(['books' => fn ($query) => $query->published()])
                    ->orderBy('name')
                    ->get(),
            ]);
        });

        // The admin sidebar used to carry per-section count badges, which meant
        // seven COUNT queries on every admin page. The badges are gone, so the
        // composer that fed them is gone too.
    }
}
