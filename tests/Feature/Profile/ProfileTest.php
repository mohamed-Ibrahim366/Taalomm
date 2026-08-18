<?php

namespace Tests\Feature\Profile;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\PasswordChanged;
use App\Events\ProfileUpdated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    // ---- Helpers ----

    private function activeUser(UserRole $role = UserRole::STUDENT): User
    {
        return User::factory()->create([
            'role'              => $role,
            'status'            => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    // ---- GET /api/profile ----

    public function test_authenticated_user_can_view_profile(): void
    {
        $user = $this->activeUser();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'role', 'status'],
            ]);
    }

    public function test_unauthenticated_user_cannot_view_profile(): void
    {
        $this->getJson('/api/profile')->assertUnauthorized();
    }

    // ---- PUT /api/profile ----

    public function test_user_can_update_name_and_phone(): void
    {
        Event::fake([ProfileUpdated::class]);

        $user = $this->activeUser();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', [
                'name'  => 'Updated Name',
                'phone' => '+1234567890',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Profile updated successfully.')
            ->assertJsonPath('user.name', 'Updated Name')
            ->assertJsonPath('user.phone', '+1234567890');

        $this->assertDatabaseHas('users', [
            'id'    => $user->id,
            'name'  => 'Updated Name',
            'phone' => '+1234567890',
        ]);

        Event::assertDispatched(ProfileUpdated::class);
    }

    public function test_profile_update_requires_valid_data(): void
    {
        $user = $this->activeUser();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', ['name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    // ---- POST /api/profile/photo ----

    public function test_user_can_upload_profile_photo(): void
    {
        Storage::fake('public');
        Event::fake([ProfileUpdated::class]);

        $user = $this->activeUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/photo', [
                'photo' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Photo uploaded successfully.')
            ->assertJsonStructure(['user' => ['photo_url']]);

        $this->assertNotNull($user->fresh()->photo_path);
        Event::assertDispatched(ProfileUpdated::class);
    }

    public function test_photo_upload_rejects_non_image_files(): void
    {
        $user = $this->activeUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/photo', [
                'photo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_photo_upload_rejects_oversized_files(): void
    {
        $user = $this->activeUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/photo', [
                'photo' => UploadedFile::fake()->image('big.jpg')->size(3000), // 3 MB > 2 MB limit
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    // ---- DELETE /api/profile/photo ----

    public function test_user_can_delete_profile_photo(): void
    {
        Storage::fake('public');
        Event::fake([ProfileUpdated::class]);

        $user = $this->activeUser();
        $path = UploadedFile::fake()->image('avatar.jpg')->store('photos/users', 'public');
        $user->update(['photo_path' => $path]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/profile/photo')
            ->assertOk()
            ->assertJsonPath('message', 'Photo removed successfully.');

        $this->assertNull($user->fresh()->photo_path);
        Storage::disk('public')->assertMissing($path);
    }

    // ---- PUT /api/profile/password ----

    public function test_user_can_change_password(): void
    {
        Event::fake([PasswordChanged::class]);

        $user = $this->activeUser();
        $user->update(['password' => Hash::make('OldPass@123')]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile/password', [
                'current_password'      => 'OldPass@123',
                'password'              => 'NewPass@456',
                'password_confirmation' => 'NewPass@456',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Password changed successfully.');

        $this->assertTrue(Hash::check('NewPass@456', $user->fresh()->password));
        Event::assertDispatched(PasswordChanged::class);
    }

    public function test_user_cannot_change_password_with_wrong_current_password(): void
    {
        $user = $this->activeUser();
        $user->update(['password' => Hash::make('RealPass@123')]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile/password', [
                'current_password'      => 'WrongPass@123',
                'password'              => 'NewPass@456',
                'password_confirmation' => 'NewPass@456',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_new_password_must_meet_strength_requirements(): void
    {
        $user = $this->activeUser();
        $user->update(['password' => Hash::make('OldPass@123')]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile/password', [
                'current_password'      => 'OldPass@123',
                'password'              => 'weakpassword', // no uppercase, no number, no symbol
                'password_confirmation' => 'weakpassword',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    // ---- PUT /api/profile/email ----

    public function test_user_can_initiate_email_change(): void
    {
        $user = $this->activeUser();
        $user->update(['password' => Hash::make('Current@123')]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile/email', [
                'email'    => 'new@example.com',
                'password' => 'Current@123',
            ])
            ->assertOk()
            ->assertJsonStructure(['message']);
    }

    public function test_email_change_requires_unique_new_email(): void
    {
        $existing = $this->activeUser();
        $user     = $this->activeUser();
        $user->update(['password' => Hash::make('Current@123')]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile/email', [
                'email'    => $existing->email,
                'password' => 'Current@123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_email_change_requires_different_email(): void
    {
        $user = $this->activeUser();
        $user->update(['password' => Hash::make('Current@123')]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile/email', [
                'email'    => $user->email, // same as current
                'password' => 'Current@123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
