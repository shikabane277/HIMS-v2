<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\RecordAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RecordAccessPasswordGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed default record access password
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'record_access_password_hash'],
            ['value' => Hash::make('Hospital@2026!'), 'updated_at' => now()]
        );
    }

    public function test_record_access_prompt_redirects_to_dashboard_because_access_is_in_place(): void
    {
        $user = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($user)->get(route('record-access.prompt'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_submitting_incorrect_password_fails_and_keeps_records_locked(): void
    {
        $user = User::factory()->create(['role' => 'staff']);

        // Form post test
        $response = $this->actingAs($user)->post(route('record-access.verify'), [
            'record_password' => 'wrong-pass',
            'intended' => route('dashboard'),
        ]);

        $response->assertSessionHas('error');
        $this->assertFalse(RecordAccess::isUnlocked());

        // AJAX post test
        $ajaxResponse = $this->actingAs($user)->postJson(route('record-access.unlock'), [
            'password' => 'wrong-pass',
        ]);

        $ajaxResponse->assertStatus(422);
        $ajaxResponse->assertJson(['success' => false]);
        $this->assertFalse(RecordAccess::isUnlocked());
    }

    public function test_submitting_correct_password_unlocks_records(): void
    {
        $user = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($user)->post(route('record-access.verify'), [
            'record_password' => 'Hospital@2026!',
            'intended' => route('dashboard'),
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertTrue(RecordAccess::isUnlocked());

        // Lock again
        $this->actingAs($user)->post(route('record-access.lock'));
        $this->assertFalse(RecordAccess::isUnlocked());

        // In-page AJAX unlock
        $ajaxResponse = $this->actingAs($user)->postJson(route('record-access.unlock'), [
            'password' => 'Hospital@2026!',
        ]);

        $ajaxResponse->assertStatus(200);
        $ajaxResponse->assertJson(['success' => true]);
        $this->assertTrue(RecordAccess::isUnlocked());
    }

    public function test_sensitive_record_views_blur_when_locked_and_unblur_when_unlocked(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        // 1. Locked state: page renders with blur class and overlay
        $this->actingAs($user)->post(route('record-access.lock'));
        $lockedResponse = $this->actingAs($user)->get(route('performance.index'));
        $lockedResponse->assertStatus(200);
        $lockedResponse->assertSee('Confidential Records Protected');
        $lockedResponse->assertSee('<div class="hims-sensitive-records is-blurred">', false);

        $lockedEmployeesResponse = $this->actingAs($user)->get(route('employees.index'));
        $lockedEmployeesResponse->assertStatus(200);
        $lockedEmployeesResponse->assertSee('Confidential Records Protected');
        $lockedEmployeesResponse->assertSee('<div class="hims-sensitive-records is-blurred">', false);

        // 2. Unlocked state: page renders cleanly without blur
        $this->actingAs($user)->postJson(route('record-access.unlock'), [
            'password' => 'Hospital@2026!',
        ]);
        $unlockedResponse = $this->actingAs($user)->get(route('performance.index'));
        $unlockedResponse->assertStatus(200);
        $unlockedResponse->assertDontSee('Confidential Records Protected');
        $unlockedResponse->assertDontSee('<div class="hims-sensitive-records is-blurred">', false);

        $unlockedEmployeesResponse = $this->actingAs($user)->get(route('employees.index'));
        $unlockedEmployeesResponse->assertStatus(200);
        $unlockedEmployeesResponse->assertDontSee('Confidential Records Protected');
        $unlockedEmployeesResponse->assertDontSee('<div class="hims-sensitive-records is-blurred">', false);
    }

    public function test_admin_can_update_record_access_password(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => Hash::make('AdminLoginPassword123!'),
        ]);

        $response = $this->actingAs($admin)->post(route('settings.record-access-password'), [
            'current_admin_password' => 'AdminLoginPassword123!',
            'record_access_password' => 'NewSecretPassword999!',
            'record_access_password_confirmation' => 'NewSecretPassword999!',
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');

        // Verify old password no longer works
        $oldAttempt = $this->actingAs($admin)->postJson(route('record-access.unlock'), [
            'password' => 'Hospital@2026!',
        ]);
        $oldAttempt->assertStatus(422);

        // Verify new password works
        $newAttempt = $this->actingAs($admin)->postJson(route('record-access.unlock'), [
            'password' => 'NewSecretPassword999!',
        ]);
        $newAttempt->assertStatus(200);
        $this->assertTrue(RecordAccess::isUnlocked());
    }
}
