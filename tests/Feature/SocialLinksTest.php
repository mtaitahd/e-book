<?php
namespace Tests\Feature;
use App\Models\SocialLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class SocialLinksTest extends TestCase
{
    use RefreshDatabase;
    public function test_admin_can_save_links_and_footer_uses_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.settings.social.update'), ['facebook' => 'https://facebook.com/example'])->assertSessionHasNoErrors()->assertRedirect();
        $this->get(route('home'))->assertOk()->assertSee('https://facebook.com/example');
        $this->post(route('admin.settings.social.update'), ['facebook' => 'javascript:alert(1)'])->assertSessionHasErrors('facebook');
        $this->assertSame('https://facebook.com/example', SocialLink::find('facebook')->url);
    }
    public function test_customer_cannot_change_social_links(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']))->post(route('admin.settings.social.update'), ['x' => 'https://x.com/example'])->assertForbidden();
    }
}
