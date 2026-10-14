<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_view_the_profile_page(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.profile.edit'))
            ->assertOk()
            ->assertSee($admin->name)
            ->assertSee($admin->email);
    }

    public function test_customer_cannot_access_the_admin_profile(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)->get(route('admin.profile.edit'))->assertForbidden();
        $this->actingAs($customer)->put(route('admin.profile.update'))->assertForbidden();
    }

    public function test_admin_can_update_name_email_and_phone(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.profile.update'), [
                'name' => 'New Admin Name',
                'email' => 'new-admin@example.com',
                'phone' => '0713111222',
            ])
            ->assertRedirect(route('admin.profile.edit'))
            ->assertSessionHas('success');

        $admin->refresh();
        $this->assertSame('New Admin Name', $admin->name);
        $this->assertSame('new-admin@example.com', $admin->email);
        $this->assertSame('255713111222', $admin->phone);
    }

    public function test_email_must_be_unique_but_the_admins_own_email_is_allowed(): void
    {
        $admin = $this->admin();
        User::factory()->customer()->create(['email' => 'taken@example.com']);

        $this->actingAs($admin)
            ->put(route('admin.profile.update'), [
                'name' => $admin->name,
                'email' => 'taken@example.com',
            ])
            ->assertSessionHasErrors('email');

        $this->actingAs($admin)
            ->put(route('admin.profile.update'), [
                'name' => $admin->name,
                'email' => $admin->email,
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_admin_can_upload_a_profile_photo(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.profile.update'), [
                'name' => $admin->name,
                'email' => $admin->email,
                'avatar' => UploadedFile::fake()->image('photo.png', 200, 200),
            ])
            ->assertRedirect(route('admin.profile.edit'))
            ->assertSessionHas('success');

        $admin->refresh();
        $this->assertNotNull($admin->avatar);
        $this->assertStringStartsWith('avatars/', $admin->avatar);
        Storage::disk('public')->assertExists($admin->avatar);
    }

    public function test_replacing_a_photo_removes_the_previous_file(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.profile.update'), [
            'name' => $admin->name,
            'email' => $admin->email,
            'avatar' => UploadedFile::fake()->image('first.png', 100, 100),
        ]);

        $oldAvatar = $admin->fresh()->avatar;
        Storage::disk('public')->assertExists($oldAvatar);

        $this->actingAs($admin)->put(route('admin.profile.update'), [
            'name' => $admin->name,
            'email' => $admin->email,
            'avatar' => UploadedFile::fake()->image('second.png', 100, 100),
        ]);

        $newAvatar = $admin->fresh()->avatar;
        $this->assertNotSame($oldAvatar, $newAvatar);
        Storage::disk('public')->assertMissing($oldAvatar);
        Storage::disk('public')->assertExists($newAvatar);
    }

    public function test_invalid_photo_upload_is_rejected(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.profile.update'), [
                'name' => $admin->name,
                'email' => $admin->email,
                'avatar' => UploadedFile::fake()->create('not-an-image.txt', 100),
            ])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($admin->fresh()->avatar);
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.profile.update'), [
                'name' => $admin->name,
                'email' => $admin->email,
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $admin->fresh()->password));
    }

    public function test_password_change_rejects_a_wrong_current_password(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.profile.update'), [
                'name' => $admin->name,
                'email' => $admin->email,
                'current_password' => 'wrong-password',
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $admin->fresh()->password));
    }

    public function test_admin_can_change_their_password_with_the_correct_current_one(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.profile.update'), [
                'name' => $admin->name,
                'email' => $admin->email,
                'current_password' => 'password',
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ])
            ->assertRedirect(route('admin.profile.edit'))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-password', $admin->fresh()->password));
    }

    public function test_updating_without_a_password_keeps_the_current_one(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.profile.update'), [
                'name' => 'Renamed Admin',
                'email' => $admin->email,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('password', $admin->fresh()->password));
    }
}
