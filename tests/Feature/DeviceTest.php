<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => 'user', 'timezone' => 'Asia/Almaty']);
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/devices')->assertRedirect('/login');
    }

    public function test_page_opens(): void
    {
        $this->actingAs($this->user())->get('/devices')->assertOk();
    }

    public function test_creating_device_shows_token_once_and_stores_only_hash(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->post('/devices', ['name' => 'Ноутбук']);

        $response->assertRedirect('/devices')->assertSessionHas('new_token');
        $token = session('new_token');
        $this->assertStringStartsWith('amd_', $token);

        $device = Device::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(hash('sha256', $token), $device->token_hash);
        $this->assertStringNotContainsString($token, json_encode($device->getAttributes()));
    }

    public function test_name_is_required(): void
    {
        $this->actingAs($this->user())->post('/devices', [])->assertSessionHasErrors('name');
    }

    public function test_regenerating_token_invalidates_the_old_one(): void
    {
        $user = $this->user();
        $device = Device::create(['user_id' => $user->id, 'name' => 'PC']);
        $old = $device->rotateToken();

        $this->actingAs($user)->post("/devices/{$device->id}/token");
        $new = session('new_token');

        $this->assertNotSame($old, $new);
        $this->postJson('/api/agent/heartbeat', [], ['Authorization' => "Bearer {$old}"])->assertUnauthorized();
        $this->postJson('/api/agent/heartbeat', [], ['Authorization' => "Bearer {$new}"])->assertOk();
    }

    public function test_cannot_touch_foreign_device(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $device = Device::create(['user_id' => $owner->id, 'name' => 'PC']);

        $this->actingAs($other)->post("/devices/{$device->id}/token")->assertForbidden();
        $this->actingAs($other)->delete("/devices/{$device->id}")->assertForbidden();
        $this->assertDatabaseHas('devices', ['id' => $device->id]);
    }

    public function test_deleting_device_removes_its_activity(): void
    {
        $user = $this->user();
        $device = Device::create(['user_id' => $user->id, 'name' => 'PC']);
        ActivityLog::create([
            'user_id' => $user->id, 'device_id' => $device->id, 'app_name' => 'Code',
            'started_at' => now()->subHour(), 'duration_sec' => 60,
        ]);

        $this->actingAs($user)->delete("/devices/{$device->id}");

        $this->assertDatabaseMissing('devices', ['id' => $device->id]);
        $this->assertSame(0, ActivityLog::count());
    }
}
