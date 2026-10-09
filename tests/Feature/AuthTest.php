<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Administrator Login')
            ->assertSee(route('admin.login.store'), false)
            // Sign-up belongs to the storefront modal; the administrator page
            // must stay a plain sign-in form.
            ->assertDontSee(route('register.store'), false);
    }

    public function test_registration_has_no_standalone_page_but_lives_in_the_auth_modal(): void
    {
        // The sign-up form is a view inside the modal, not a page of its own, so
        // the endpoint answers POST only.
        $this->get('/register')->assertStatus(405);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('register.store'), false)
            ->assertSee('data-auth-switch="register"', false)
            ->assertSee("Don't have an account?", false);
    }

    public function test_guest_can_register_and_is_signed_in_immediately(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect(route('account.show'));

        $user = User::where('email', 'jane@example.com')->firstOrFail();

        $this->assertSame('Jane Doe', $user->name);
        $this->assertTrue($user->isCustomer());
        $this->assertTrue($user->isActive());
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_stores_a_mobile_money_number_in_canonical_form(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '0754 123 456',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect(route('account.show'));

        $user = User::where('email', 'jane@example.com')->firstOrFail();

        $this->assertSame('255754123456', $user->phone);
    }

    public function test_registration_works_without_a_mobile_money_number(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect(route('account.show'));

        $user = User::where('email', 'jane@example.com')->firstOrFail();

        $this->assertNull($user->phone);
    }

    public function test_registration_rejects_a_mobile_money_number_it_cannot_parse(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '12345',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertSessionHasErrors('phone');

        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
    }

    public function test_registration_rejects_a_mobile_money_number_already_on_another_account(): void
    {
        User::factory()->create(['phone' => '255754123456']);

        // The same number typed in a different format is still the same number.
        $this->post(route('register.store'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '0754123456',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertSessionHasErrors('phone');

        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
    }

    public function test_registration_never_provisions_an_administrator(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_SUSPENDED,
        ])->assertRedirect(route('account.show'));

        $user = User::where('email', 'sneaky@example.com')->firstOrFail();

        $this->assertTrue($user->isCustomer());
        $this->assertTrue($user->isActive());
    }

    public function test_registration_rejects_an_email_that_is_already_taken(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('register.store'), [
            'name' => 'Jane Doe',
            'email' => 'taken@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(1, User::where('email', 'taken@example.com')->count());
    }

    public function test_registration_requires_a_matching_password_confirmation(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
    }

    public function test_registration_stores_a_hashed_password(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $user = User::where('email', 'jane@example.com')->firstOrFail();

        $this->assertNotSame('secret-password', $user->password);
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_ajax_registration_failure_returns_json_validation_errors(): void
    {
        $this->postJson(route('register.store'), [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'other',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->assertGuest();
    }

    public function test_new_customer_is_resumed_to_the_protected_page_they_were_after(): void
    {
        $book = Book::factory()->published()->create();
        $this->post(route('cart.add'), ['book_id' => $book->id]);

        // Bounced off checkout: the intended URL is remembered.
        $this->from(route('cart.show'))->get(route('checkout.show'))
            ->assertRedirect(route('cart.show'));

        // Signing up resumes it instead of dropping the customer on /account.
        $this->post(route('register.store'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect(route('checkout.show'));

        $this->get(route('checkout.show'))->assertOk();
    }

    public function test_authenticated_user_cannot_reach_the_registration_endpoint(): void
    {
        $this->actingAs(User::factory()->customer()->create())
            ->post(route('register.store'), [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'password' => 'secret-password',
                'password_confirmation' => 'secret-password',
            ])->assertRedirect(route('home'));
    }

    public function test_admin_can_login_through_the_admin_page_endpoint(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $this->post(route('admin.login.store'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_login_page_rejects_customer_credentials(): void
    {
        $customer = User::factory()->customer()->create([
            'email' => 'customer@example.com',
            'password' => 'password',
        ]);

        $this->from(route('login'))->post(route('admin.login.store'), [
            'email' => 'customer@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        // Valid credentials, but not an administrator: the session must be dropped.
        $this->assertGuest();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(AuthenticatedSessionController::NOT_ADMIN);
    }

    public function test_admin_login_page_rejects_wrong_credentials(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'correct-password',
        ]);

        $this->from(route('login'))->post(route('admin.login.store'), [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_customer_is_bounced_off_the_admin_login_page(): void
    {
        $this->actingAs(User::factory()->customer()->create())
            ->get(route('login'))
            ->assertRedirect(route('home'));
    }

    public function test_customer_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'password',
        ]);

        $this->post(route('login.store'), [
            'email' => 'customer@example.com',
            'password' => 'password',
        ])->assertRedirect(route('account.show'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_customer_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_admin_can_login(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $this->post(route('login.store'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_login_rejects_wrong_credentials(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'correct-password',
        ]);

        $this->post(route('login.store'), [
            'email' => 'customer@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_page_renders_sweetalert_feedback_after_wrong_credentials(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'correct-password',
        ]);

        $this->from(route('login'))->post(route('login.store'), [
            'email' => 'customer@example.com',
            'password' => 'wrong-password',
        ]);

        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertSee('data-swal-flash', false)
            ->assertSee('Login Failed', false)
            ->assertSee('Try Again', false)
            ->assertSee(AuthenticatedSessionController::INVALID_CREDENTIALS)
            ->assertSee('sweetalert.min.js', false)
            ->assertSee('swal-flash.js', false);
    }

    public function test_inactive_account_login_renders_sweetalert_feedback(): void
    {
        User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => 'password',
            'status' => User::STATUS_SUSPENDED,
        ]);

        $this->from(route('login'))->post(route('login.store'), [
            'email' => 'suspended@example.com',
            'password' => 'password',
        ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(AuthenticatedSessionController::INACTIVE_ACCOUNT);

        $this->assertGuest();
    }

    public function test_ajax_login_with_wrong_credentials_returns_json_for_sweetalert(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'correct-password',
        ]);

        $this->postJson(route('login.store'), [
            'email' => 'customer@example.com',
            'password' => 'wrong-password',
        ])
            ->assertStatus(422)
            ->assertJsonPath('title', 'Login Failed')
            ->assertJsonPath('message', AuthenticatedSessionController::INVALID_CREDENTIALS)
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_ajax_login_with_inactive_account_returns_json_for_sweetalert(): void
    {
        User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => 'password',
            'status' => User::STATUS_SUSPENDED,
        ]);

        $this->postJson(route('login.store'), [
            'email' => 'suspended@example.com',
            'password' => 'password',
        ])
            ->assertStatus(422)
            ->assertJsonPath('title', 'Login Failed')
            ->assertJsonPath('message', AuthenticatedSessionController::INACTIVE_ACCOUNT);

        $this->assertGuest();
    }

    public function test_ajax_login_with_correct_credentials_redirects(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'password',
        ]);

        $this->postJson(route('login.store'), [
            'email' => 'customer@example.com',
            'password' => 'password',
        ])->assertStatus(302);

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => 'password',
            'status' => User::STATUS_SUSPENDED,
        ]);

        $this->post(route('login.store'), [
            'email' => 'suspended@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_customer_cannot_access_admin_area(): void
    {
        $this->actingAs(User::factory()->customer()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_admin_area(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_guest_cannot_access_account_area(): void
    {
        // Guests are bounced back into the storefront auth modal, not the
        // administrator login page.
        $this->from(route('cart.show'))->get(route('account.show'))
            ->assertRedirect(route('cart.show'))
            ->assertSessionHas('auth_intended', route('account.show'));
    }

    public function test_guest_checkout_opens_the_auth_modal_instead_of_the_login_page(): void
    {
        $book = Book::factory()->published()->create();
        $this->post(route('cart.add'), ['book_id' => $book->id]);

        $response = $this->from(route('cart.show'))->get(route('checkout.show'));

        $response->assertRedirect(route('cart.show'))
            ->assertSessionHas('auth_intended', route('checkout.show'));

        $this->assertStringNotContainsString('/login', (string) $response->headers->get('Location'));
    }

    public function test_guest_cart_offers_a_modal_checkout_link(): void
    {
        $book = Book::factory()->published()->create();
        $this->post(route('cart.add'), ['book_id' => $book->id]);

        $this->get(route('cart.show'))
            ->assertOk()
            ->assertSee('data-ebs-checkout', false)
            ->assertSee('data-ebs-return', false)
            ->assertSee('data-ebs-auth-open="login"', false);
    }

    public function test_signed_in_customer_checks_out_without_the_modal(): void
    {
        $book = Book::factory()->published()->create();
        $this->actingAs(User::factory()->customer()->create());
        $this->post(route('cart.add'), ['book_id' => $book->id]);

        $this->get(route('cart.show'))
            ->assertOk()
            ->assertSee(route('checkout.show'), false)
            ->assertDontSee('data-ebs-checkout', false);
    }

    public function test_guest_is_resumed_to_checkout_after_signing_in(): void
    {
        $customer = User::factory()->customer()->create([
            'email' => 'customer@example.com',
            'password' => 'password',
        ]);

        $book = Book::factory()->published()->create();
        $this->post(route('cart.add'), ['book_id' => $book->id]);

        // Bounced off checkout: the intended URL is remembered.
        $this->from(route('cart.show'))->get(route('checkout.show'))
            ->assertRedirect(route('cart.show'));

        // Signing in resumes it instead of dropping the customer on /account.
        $this->post(route('login.store'), [
            'email' => 'customer@example.com',
            'password' => 'password',
        ])->assertRedirect(route('checkout.show'));

        $this->assertAuthenticatedAs($customer);
        $this->get(route('checkout.show'))->assertOk();
    }

    public function test_customer_can_access_their_account(): void
    {
        $this->actingAs(User::factory()->customer()->create())
            ->get(route('account.show'))
            ->assertOk();
    }
}