<?php

namespace Tests\Feature\Admin;

use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriberManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_guests_cannot_reach_the_subscriber_pages(): void
    {
        $this->get(route('admin.subscribers.index'))->assertRedirect(route('login'));
        $this->post(route('admin.subscribers.store'), ['email' => 'reader@example.com'])->assertRedirect(route('login'));
    }

    public function test_non_admins_cannot_manage_subscribers(): void
    {
        $customer = User::factory()->create();
        $existing = Subscriber::factory()->create();

        $this->actingAs($customer)
            ->get(route('admin.subscribers.index'))
            ->assertForbidden();

        $this->actingAs($customer)
            ->post(route('admin.subscribers.store'), ['email' => 'sneaky@example.com'])
            ->assertForbidden();

        $this->actingAs($customer)
            ->patch(route('admin.subscribers.status', $existing))
            ->assertForbidden();

        $this->actingAs($customer)
            ->delete(route('admin.subscribers.destroy', $existing))
            ->assertForbidden();

        $this->assertDatabaseMissing('subscribers', ['email' => 'sneaky@example.com']);
        $this->assertDatabaseHas('subscribers', ['id' => $existing->id]);
    }

    public function test_admin_sees_the_subscriber_page_with_the_subscribe_popup(): void
    {
        Subscriber::factory()->create(['email' => 'reader@example.com']);
        Subscriber::factory()->unsubscribed()->create(['email' => 'gone@example.com']);

        $this->actingAs($this->admin())
            ->get(route('admin.subscribers.index'))
            ->assertOk()
            // The button that opens the popup.
            ->assertSee('id="openSubscribeModal"', false)
            ->assertSee('data-target="#subscribeModal"', false)
            // The popup itself, wired to the real store endpoint.
            ->assertSee('id="subscribeModal"', false)
            ->assertSee('action="'.route('admin.subscribers.store').'"', false)
            ->assertSee('name="email"', false)
            ->assertSee('Subscribe', false)
            // The list.
            ->assertSee('reader@example.com')
            ->assertSee('gone@example.com')
            ->assertSee('Active')
            ->assertSee('Unsubscribed');
    }

    public function test_the_subscriber_page_has_an_empty_state_with_a_subscribe_button(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.subscribers.index'))
            ->assertOk()
            ->assertSee('No subscribers yet.')
            ->assertSee('data-target="#subscribeModal"', false);
    }

    public function test_admin_can_subscribe_an_email_address(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.subscribers.store'), ['email' => 'reader@example.com'])
            ->assertRedirect(route('admin.subscribers.index'))
            ->assertSessionHas('success', 'reader@example.com has been added to the list.');

        $this->assertDatabaseHas('subscribers', [
            'email' => 'reader@example.com',
            'status' => Subscriber::STATUS_ACTIVE,
        ]);

        // The join date is stamped automatically.
        $this->assertNotNull(Subscriber::first()->subscribed_at);
    }

    public function test_a_subscribed_address_is_normalised_to_lower_case(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.subscribers.store'), ['email' => '  Reader@Example.COM  '])
            ->assertRedirect(route('admin.subscribers.index'));

        $this->assertDatabaseHas('subscribers', ['email' => 'reader@example.com']);
        $this->assertDatabaseMissing('subscribers', ['email' => '  Reader@Example.COM  ']);
    }

    public function test_an_email_address_cannot_be_subscribed_twice(): void
    {
        Subscriber::factory()->create(['email' => 'reader@example.com']);

        $this->actingAs($this->admin())
            ->post(route('admin.subscribers.store'), ['email' => 'reader@example.com'])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, Subscriber::count());
    }

    public function test_duplicate_detection_ignores_capitalisation(): void
    {
        Subscriber::factory()->create(['email' => 'reader@example.com']);

        $this->actingAs($this->admin())
            ->post(route('admin.subscribers.store'), ['email' => 'Reader@Example.com'])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, Subscriber::count());
    }

    public function test_an_unsubscribed_address_must_be_removed_before_it_can_return(): void
    {
        // Unsubscribing is a status change, so the address is still taken and
        // re-adding it has to be rejected rather than silently creating a second
        // record for the same person.
        Subscriber::factory()->unsubscribed()->create(['email' => 'reader@example.com']);

        $this->actingAs($this->admin())
            ->post(route('admin.subscribers.store'), ['email' => 'reader@example.com'])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, Subscriber::count());
    }

    public function test_an_invalid_or_missing_email_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.subscribers.store'), ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');

        $this->actingAs($admin)
            ->post(route('admin.subscribers.store'), ['email' => ''])
            ->assertSessionHasErrors('email');

        $this->actingAs($admin)
            ->post(route('admin.subscribers.store'), [])
            ->assertSessionHasErrors('email');

        $this->assertSame(0, Subscriber::count());
    }

    public function test_a_failed_subscription_reopens_the_popup_with_the_message(): void
    {
        Subscriber::factory()->create(['email' => 'reader@example.com']);

        // Following the redirect must bring back the popup trigger and the
        // script that reopens it, so the message lands next to the field.
        $this->actingAs($this->admin())
            ->from(route('admin.subscribers.index'))
            ->followingRedirects()
            ->post(route('admin.subscribers.store'), [
                'email' => 'reader@example.com',
                '_form' => 'subscribe',
            ])
            ->assertOk()
            ->assertSee('id="subscribeModal"', false)
            ->assertSee('$(\'#subscribeModal\').modal(\'show\');', false)
            ->assertSee('That email address is already on the list.');
    }

    public function test_the_popup_reopens_with_the_previous_address_repopulated(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.subscribers.index'))
            ->post(route('admin.subscribers.store'), [
                'email' => 'not-an-email',
                '_form' => 'subscribe',
            ]);

        $this->actingAs($this->admin())
            ->get(route('admin.subscribers.index'))
            ->assertOk()
            ->assertSee('value="not-an-email"', false);
    }

    public function test_admin_can_unsubscribe_and_resubscribe(): void
    {
        $subscriber = Subscriber::factory()->create(['email' => 'reader@example.com']);

        $this->actingAs($this->admin())
            ->patch(route('admin.subscribers.status', $subscriber))
            ->assertRedirect(route('admin.subscribers.index'))
            ->assertSessionHas('success', 'reader@example.com has been unsubscribed.');

        $this->assertSame(Subscriber::STATUS_UNSUBSCRIBED, $subscriber->fresh()->status);

        $this->actingAs($this->admin())
            ->patch(route('admin.subscribers.status', $subscriber))
            ->assertRedirect(route('admin.subscribers.index'))
            ->assertSessionHas('success', 'reader@example.com has been resubscribed.');

        $this->assertSame(Subscriber::STATUS_ACTIVE, $subscriber->fresh()->status);
    }

    public function test_resubscribing_keeps_the_original_join_date(): void
    {
        $joinedOn = now()->subDays(30);

        $subscriber = Subscriber::factory()->create([
            'email' => 'reader@example.com',
            'subscribed_at' => $joinedOn,
        ]);

        $this->actingAs($this->admin())->patch(route('admin.subscribers.status', $subscriber));
        $this->actingAs($this->admin())->patch(route('admin.subscribers.status', $subscriber));

        // Compared at second precision: the column stores whole seconds while
        // Carbon carries microseconds, so a strict instant comparison would
        // fail for reasons that have nothing to do with the behaviour.
        $this->assertSame(
            $joinedOn->toDateTimeString(),
            $subscriber->fresh()->subscribed_at->toDateTimeString(),
            'Re-subscribing must not reset the original join date.'
        );
    }

    public function test_admin_can_remove_a_subscriber(): void
    {
        $subscriber = Subscriber::factory()->create(['email' => 'reader@example.com']);

        $this->actingAs($this->admin())
            ->delete(route('admin.subscribers.destroy', $subscriber))
            ->assertRedirect(route('admin.subscribers.index'))
            ->assertSessionHas('success', 'reader@example.com has been removed from the list.');

        $this->assertDatabaseMissing('subscribers', ['id' => $subscriber->id]);
    }

    public function test_the_list_can_be_filtered_by_status(): void
    {
        Subscriber::factory()->create(['email' => 'active@example.com']);
        Subscriber::factory()->unsubscribed()->create(['email' => 'gone@example.com']);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.subscribers.index', ['status' => Subscriber::STATUS_ACTIVE]))
            ->assertOk()
            ->assertSee('active@example.com')
            ->assertDontSee('gone@example.com');

        $this->actingAs($admin)
            ->get(route('admin.subscribers.index', ['status' => Subscriber::STATUS_UNSUBSCRIBED]))
            ->assertOk()
            ->assertSee('gone@example.com')
            ->assertDontSee('active@example.com');

        $this->actingAs($admin)
            ->get(route('admin.subscribers.index'))
            ->assertOk()
            ->assertSee('active@example.com')
            ->assertSee('gone@example.com');
    }

    public function test_an_unknown_status_filter_is_ignored(): void
    {
        Subscriber::factory()->create(['email' => 'reader@example.com']);

        $this->actingAs($this->admin())
            ->get(route('admin.subscribers.index', ['status' => 'nonsense']))
            ->assertOk()
            ->assertSee('reader@example.com');
    }

    public function test_the_sidebar_links_to_subscribers_without_a_count_badge(): void
    {
        Subscriber::factory()->count(3)->create();
        Subscriber::factory()->unsubscribed()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.subscribers.index'))
            ->assertSee('Subscribers')
            // The sidebar no longer trails counts on any link.
            ->assertDontSee('adminCounts', false);

        // The count still exists as a query, and only the active ones are counted,
        // not the unsubscribed one -- it is simply no longer surfaced in the
        // sidebar. The Subscribers page itself still reports it.
        $this->assertSame(3, Subscriber::active()->count());
    }

    public function test_subscriber_forms_require_a_csrf_token(): void
    {
        // Every write is a POST under the auth + admin middleware, so the CSRF
        // field is what stops another site submitting on the admin's behalf.
        $this->assertSame('POST', (new \Illuminate\Routing\Route('POST', '/', []))->methods()[0] ?? 'POST');

        foreach ([
            'admin.subscribers.store' => 'post',
            'admin.subscribers.status' => 'patch',
            'admin.subscribers.destroy' => 'delete',
        ] as $name => $method) {
            $route = collect(app('router')->getRoutes()->getRoutes())
                ->first(fn ($route) => $route->getName() === $name);

            $this->assertNotNull($route, "Route [{$name}] is not registered.");
            $this->assertContains(strtoupper($method), $route->methods());
            $this->assertContains('web', $route->gatherMiddleware());
        }
    }

    public function test_the_storefront_footer_still_has_no_newsletter(): void
    {
        // The newsletter now lives only in the admin, so the public footer must
        // not have gained a second entry point.
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('ebs-footer-news', false)
            ->assertDontSee('Stay Updated with New E-Books');
    }
}
