<?php

namespace Tests\Feature\Payment;

use App\Support\Money;
use App\Support\Phone;

class CurrencyAndValidationTest extends PaymentTestCase
{
    public function test_shop_currency_defaults_to_tzs(): void
    {
        $this->assertSame('TZS', config('shop.currency'));
    }

    public function test_money_to_whole_int_converts_and_rounds_half_up(): void
    {
        $this->assertSame(1500, Money::toWholeInt('1500.00'));
        $this->assertSame(1250, Money::toWholeInt('1250.25'));
        $this->assertSame(1251, Money::toWholeInt('1250.50'));
        $this->assertSame(1251, Money::toWholeInt('1250.75'));
        $this->assertSame(499, Money::toWholeInt('499.49'));
        $this->assertSame(500, Money::toWholeInt('499.50'));
    }

    public function test_money_format_whole_renders_tzs_with_separators(): void
    {
        $this->assertSame('1,500 TZS', Money::formatWhole(1500));
        $this->assertSame('25,000 TZS', Money::formatWhole(25000, 'TZS'));
    }

    public function test_phone_normalizes_tanzanian_variants(): void
    {
        $this->assertSame('255754123456', Phone::normalizeTanzanian('0754123456'));
        $this->assertSame('255754123456', Phone::normalizeTanzanian('+255754123456'));
        $this->assertSame('255754123456', Phone::normalizeTanzanian('754123456'));
        $this->assertSame('255754123456', Phone::normalizeTanzanian('255754123456'));
    }

    public function test_phone_rejects_invalid_numbers(): void
    {
        $this->assertNull(Phone::normalizeTanzanian('075412345'));
        $this->assertNull(Phone::normalizeTanzanian('0994123456'));
        $this->assertNull(Phone::normalizeTanzanian(''));
        $this->assertNull(Phone::normalizeTanzanian(null));
        $this->assertNull(Phone::normalizeTanzanian('abcdefgh'));
    }

    public function test_checkout_creates_tzs_orders(): void
    {
        $book = \App\Models\Book::factory()->published()->create(['price' => '1200.50']);
        $user = $this->customer();

        $this->actingAs($user)->post(route('cart.add'), ['book_id' => $book->id]);
        $this->actingAs($user)->post(route('checkout.store'))->assertRedirect();

        $this->assertSame('TZS', $user->orders()->latest()->first()->currency);
    }

    public function test_payment_page_shows_minimum_notice_below_500_tzs(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user, '450.00');

        $this->actingAs($user)
            ->get(route('account.orders.payments.show', $order))
            ->assertOk()
            ->assertSee('500 TZS minimum');
    }

    public function test_order_in_non_active_currency_cannot_be_paid(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user, '1500.00', 'KES');

        $this->actingAs($user)
            ->get(route('account.orders.payments.show', $order))
            ->assertOk()
            ->assertSee('not available');

        $this->actingAs($user)
            ->post(route('account.orders.payments.store', $order), [
                'network' => 'airtel_money',
                'phone' => '0754123456',
            ])
            ->assertSessionHas('error');
    }

    public function test_payment_page_lists_all_four_networks(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user, '1500.00');

        $response = $this->actingAs($user)
            ->get(route('account.orders.payments.show', $order))
            ->assertOk();

        $response->assertSee('Airtel Money');
        $response->assertSee('M-Pesa');
        $response->assertSee('Mixx by Yas');
        $response->assertSee('Halotel / HaloPesa');
    }
}