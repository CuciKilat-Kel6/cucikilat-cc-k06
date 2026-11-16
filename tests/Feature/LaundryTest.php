<?php

namespace Tests\Feature;

use App\Models\LaundryOrder;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LaundryTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): void
    {
        $this->actingAs(User::factory()->create());
    }

    private function payload(Service $service, array $extra = []): array
    {
        return array_merge(['customer_name' => 'Ryan', 'phone' => '081234567890', 'service_id' => $service->id, 'weight' => '2.1', 'notes' => 'Pisah warna'], $extra);
    }

    public static function weights(): array
    {
        return ['minimum' => ['0.2', 2000, 14000], 'exact minimum' => ['2', 2000, 14000], 'above minimum' => ['2.001', 2500, 17500], 'half kilogram' => ['2.5', 2500, 17500], 'above half' => ['2.501', 3000, 21000]];
    }

    #[DataProvider('weights')]
    public function test_prices_kilograms_with_minimum_and_upward_rounding(string $weight, int $grams, int $total): void
    {
        $this->staff();
        $service = Service::factory()->create();
        $this->post(route('orders.store'), $this->payload($service, ['weight' => $weight, 'total' => 1]))->assertRedirect();
        $this->assertDatabaseHas('laundry_orders', ['billable_grams' => $grams, 'total' => $total, 'unit_price' => 7000]);
    }

    public function test_express_adds_fifty_percent_and_one_day_estimate(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 16)->setTime(12, 0));
        $this->staff();
        $service = Service::factory()->express()->create();
        $this->post(route('orders.store'), $this->payload($service))->assertRedirect();
        $order = LaundryOrder::firstOrFail();
        $this->assertSame(26250, $order->total);
        $this->assertSame('2026-11-17 12:00', $order->due_at->format('Y-m-d H:i'));
    }

    public function test_regular_estimate_is_three_days(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 16)->setTime(12, 0));
        $this->staff();
        $service = Service::factory()->create();
        $this->post(route('orders.store'), $this->payload($service))->assertRedirect();
        $this->assertSame('2026-11-19 12:00', LaundryOrder::firstOrFail()->due_at->format('Y-m-d H:i'));
    }

    public function test_satuan_charges_per_item_without_kilogram_minimum(): void
    {
        $this->staff();
        $service = Service::factory()->satuan()->create();
        $this->post(route('orders.store'), $this->payload($service, ['quantity' => 2, 'weight' => null]))->assertRedirect();
        $this->assertDatabaseHas('laundry_orders', ['quantity' => 2, 'weight_grams' => null, 'total' => 60000]);
    }

    public function test_zero_weight_is_rejected_without_creating_order(): void
    {
        $this->staff();
        $service = Service::factory()->create();
        $this->post(route('orders.store'), $this->payload($service, ['weight' => 0]))->assertSessionHasErrors('weight');
        $this->assertDatabaseCount('laundry_orders', 0);
    }

    public function test_picked_up_order_rejects_edits_status_and_payment_with_409(): void
    {
        $this->staff();
        $order = LaundryOrder::factory()->create(['status' => 'DIAMBIL', 'paid_at' => now()]);
        $this->put(route('orders.update', $order), $this->payload($order->service, ['customer_name' => 'Changed']))->assertStatus(409);
        $this->patch(route('orders.status', $order), ['status' => 'DITERIMA'])->assertStatus(409);
        $this->post(route('orders.pay', $order))->assertStatus(409);
        $this->assertDatabaseHas('laundry_orders', ['id' => $order->id, 'customer_name' => $order->customer_name, 'status' => 'DIAMBIL']);
    }

    public function test_status_advances_one_step_and_rejects_jumps(): void
    {
        $this->staff();
        $order = LaundryOrder::factory()->create();
        $this->patch(route('orders.status', $order), ['status' => 'SIAP_DIAMBIL'])->assertUnprocessable();
        $this->assertDatabaseHas('laundry_orders', ['id' => $order->id, 'status' => 'DITERIMA']);
        $this->patch(route('orders.status', $order), ['status' => 'DICUCI'])->assertRedirect();
        $this->assertDatabaseHas('laundry_orders', ['id' => $order->id, 'status' => 'DICUCI']);
        $this->assertCount(2, $order->fresh()->history);
    }

    public function test_pickup_requires_payment(): void
    {
        $this->staff();
        $order = LaundryOrder::factory()->create(['status' => 'SIAP_DIAMBIL']);
        $this->patch(route('orders.status', $order), ['status' => 'DIAMBIL'])->assertUnprocessable();
        $this->assertDatabaseHas('laundry_orders', ['id' => $order->id, 'status' => 'SIAP_DIAMBIL']);
    }

    public function test_payment_is_idempotent_and_report_uses_payment_day(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 16)->setTime(10, 0));
        $this->staff();
        $order = LaundryOrder::factory()->create(['created_at' => now()->subDay()]);
        $this->post(route('orders.pay', $order))->assertRedirect();
        $original = $order->fresh()->paid_at->toDateTimeString();
        $this->travel(1)->hours();
        $this->post(route('orders.pay', $order))->assertRedirect();
        $this->assertSame($original, $order->fresh()->paid_at->toDateTimeString());
        $this->get(route('report', ['date' => '2026-11-16']))->assertOk()->assertViewHas('paid', fn ($paid) => $paid->count() === 1 && $paid->sum('total') === 14000);
        $this->get(route('report', ['date' => '2026-11-15']))->assertOk()->assertViewHas('paid', fn ($paid) => $paid->isEmpty());
    }

    public function test_service_crud_and_archiving_preserve_order_price(): void
    {
        $this->staff();
        $this->post(route('services.store'), ['name' => 'Reguler Test', 'type' => 'reguler', 'price' => 7000])->assertRedirect();
        $service = Service::where('name', 'Reguler Test')->firstOrFail();
        $order = LaundryOrder::factory()->for($service)->create();
        $this->put(route('services.update', $service), ['name' => 'Reguler Test', 'type' => 'reguler', 'price' => 9000])->assertRedirect();
        $this->assertDatabaseHas('services', ['id' => $service->id, 'price' => 9000]);
        $this->assertSame(14000, $order->fresh()->total);
        $this->delete(route('services.delete', $service))->assertRedirect();
        $this->assertSoftDeleted($service);
        $this->assertModelExists($order);
        $this->get(route('orders.show', $order))->assertOk();
    }

    public function test_edit_recalculates_order_from_original_received_date(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 16)->setTime(12, 0));
        $this->staff();
        $order = LaundryOrder::factory()->create(['created_at' => now()->subDay()]);
        $express = Service::factory()->express()->create();
        $this->put(route('orders.update', $order), $this->payload($express))->assertRedirect();
        $this->assertDatabaseHas('laundry_orders', ['id' => $order->id, 'total' => 26250]);
        $this->assertSame('2026-11-16 12:00', $order->fresh()->due_at->format('Y-m-d H:i'));
    }

    public function test_tracking_displays_progress_without_customer_phone_or_name(): void
    {
        $order = LaundryOrder::factory()->create(['customer_name' => 'PRIVATE_NAME', 'phone' => '081299887766']);
        $this->get(route('track', ['code' => strtolower($order->code)]))->assertOk()->assertSee($order->code)->assertSee('DITERIMA')->assertDontSee('PRIVATE_NAME')->assertDontSee('081299887766');
        $this->get(route('track', ['code' => 'CK-NOTFOUND']))->assertOk()->assertSee('Kodenya belum ketemu.');
    }

    public function test_customer_notes_are_escaped_in_admin_page(): void
    {
        $this->staff();
        $order = LaundryOrder::factory()->create(['notes' => '<script>alert(1)</script>']);
        $this->get(route('orders.show', $order))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_private_pages_redirect_guests_and_login_works(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->post(route('orders.store'), [])->assertRedirect(route('login'));
        $user = User::factory()->create();
        $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_does_not_authenticate(): void
    {
        $user = User::factory()->create();
        $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_staff_pages_render_and_search_filters_orders(): void
    {
        $this->staff();
        $order = LaundryOrder::factory()->create(['customer_name' => 'FindMe']);
        $this->get(route('dashboard', ['q' => 'FindMe']))->assertOk()->assertSee($order->code);
        $this->get(route('dashboard', ['q' => 'Nobody']))->assertOk()->assertDontSee($order->code);
        $this->get(route('orders.create'))->assertOk()->assertSee('TARUH TASNYA');
        $this->get(route('orders.edit', $order))->assertOk()->assertSee('EDIT CUCIAN');
        $this->get(route('services'))->assertOk();
    }

    public function test_health_reports_database_connected(): void
    {
        $this->get('/health')->assertOk()->assertJson(['status' => 'ok', 'database' => 'connected']);
    }

    public function test_health_returns_503_when_database_is_unavailable(): void
    {
        DB::shouldReceive('select')->once()->with('SELECT 1')->andThrow(new \RuntimeException('offline'));
        $this->get('/health')->assertServiceUnavailable()->assertExactJson(['status' => 'error', 'database' => 'disconnected']);
    }

    public function test_paid_order_detail_is_locked_with_409(): void
    {
        $this->staff();
        $order = LaundryOrder::factory()->create(['paid_at' => now()]);

        $this->put(route('orders.update', $order), $this->payload($order->service, ['weight' => '5']))->assertStatus(409);

        $this->assertDatabaseHas('laundry_orders', ['id' => $order->id, 'total' => 14000]);
    }

    public function test_unused_weight_input_does_not_break_satuan_order(): void
    {
        $this->staff();
        $service = Service::factory()->satuan()->create();

        $this->post(route('orders.store'), $this->payload($service, ['weight' => ['invalid'], 'quantity' => 1]))->assertRedirect();

        $this->assertDatabaseHas('laundry_orders', ['quantity' => 1, 'total' => 30000]);
    }

    public function test_seeding_again_preserves_edited_prices_and_archived_services(): void
    {
        $this->seed();
        $service = Service::where('type', 'reguler')->firstOrFail();
        $service->update(['price' => 9000]);
        $service->delete();

        $this->seed();

        $this->assertDatabaseCount('services', 3);
        $this->assertDatabaseHas('services', ['id' => $service->id, 'price' => 9000]);
        $this->assertSoftDeleted($service);
    }
}
