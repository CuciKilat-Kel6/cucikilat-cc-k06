<?php

namespace Tests\Feature;

use App\Models\LaundryOrder;
use App\Models\Service;
use App\Models\User;
use App\Services\OrderReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_requires_staff_login(): void
    {
        $order = LaundryOrder::factory()->create();
        $this->get(route('orders.receipt', $order))->assertRedirect(route('login'));
    }

    public function test_express_receipt_contains_itemized_amounts_and_qr_link(): void
    {
        config(['app.url' => 'https://laundry.example']);
        $this->actingAs(User::factory()->create());
        $order = LaundryOrder::factory()->create(['service_type' => 'express', 'service_name' => 'Express', 'weight_grams' => 2100, 'billable_grams' => 2500, 'unit_price' => 7000, 'total' => 26250]);
        $this->get(route('orders.receipt', $order))->assertOk()->assertSee($order->code)->assertSee('Rp17.500,00')->assertSee('Rp8.750,00')->assertSee('Rp26.250')->assertSee('2,100 kg')->assertSee('2,5 kg')->assertSee('BELUM DIBAYAR')->assertSee('data:image/png;base64,', false)->assertSee('https://laundry.example/lacak?code='.$order->code, false)->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_satuan_receipt_has_item_count_and_no_kilogram_charge(): void
    {
        $this->actingAs(User::factory()->create());
        $order = LaundryOrder::factory()->create(['service_type' => 'satuan', 'quantity' => 2, 'weight_grams' => null, 'billable_grams' => null, 'unit_price' => 30000, 'total' => 60000]);
        $this->get(route('orders.receipt', $order))->assertOk()->assertSee('2 item')->assertSee('Rp60.000')->assertDontSee('Berat tagihan')->assertDontSee('Express +50%');
    }

    public function test_receipt_shows_rupiah_rounding_without_hiding_fractional_charge(): void
    {
        $order = LaundryOrder::factory()->make(['billable_grams' => 2500, 'unit_price' => 1, 'service_type' => 'express', 'total' => 4]);
        $details = app(OrderReceipt::class)->details($order);
        $this->assertSame(2.5, $details['baseAmount']);
        $this->assertSame(1.25, $details['expressAmount']);
        $this->assertSame(0.25, $details['roundingAmount']);
    }

    public function test_receipt_uses_saved_order_price_even_after_service_price_changes(): void
    {
        $this->actingAs(User::factory()->create());
        $order = LaundryOrder::factory()->create();
        $order->service->update(['price' => 99000]);
        $order->service->delete();
        $this->get(route('orders.receipt', $order))->assertOk()->assertSee('Rp14.000')->assertSee('Rp7.000')->assertDontSee('Rp99.000');
    }

    public function test_paid_and_picked_up_order_can_still_print_receipt(): void
    {
        $this->actingAs(User::factory()->create());
        $order = LaundryOrder::factory()->create(['status' => 'DIAMBIL', 'paid_at' => now()]);
        $this->get(route('orders.receipt', $order))->assertOk()->assertSee('LUNAS')->assertDontSee('BELUM DIBAYAR');
        $this->assertDatabaseHas('laundry_orders', ['id' => $order->id, 'status' => 'DIAMBIL']);
    }

    public function test_tracking_link_uses_configured_url_not_untrusted_host_header(): void
    {
        config(['app.url' => 'https://trusted.example']);
        $this->actingAs(User::factory()->create());
        $order = LaundryOrder::factory()->create();
        $this->withHeader('Host', 'spoofed.example')->get(route('orders.receipt', $order))->assertOk()->assertViewHas('trackingUrl', 'https://trusted.example/lacak?code='.$order->code);
    }

    public function test_customer_text_is_escaped_on_receipt(): void
    {
        $this->actingAs(User::factory()->create());
        $order = LaundryOrder::factory()->create(['customer_name' => '<img src=x onerror=alert(1)>', 'notes' => '<script>alert(1)</script>']);
        $this->get(route('orders.receipt', $order))->assertOk()->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)->assertDontSee('<img src=x onerror=alert(1)>', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_receipt_warns_when_qr_points_to_local_domain(): void
    {
        config(['app.url' => 'http://cucikilat.test']);
        $this->actingAs(User::factory()->create());
        $order = LaundryOrder::factory()->create();
        $this->get(route('orders.receipt', $order))->assertOk()->assertSee('HP pelanggan belum bisa membukanya.');
    }

    public function test_same_submission_key_returns_same_order_without_duplicate_charge(): void
    {
        $this->actingAs(User::factory()->create());
        $service = Service::factory()->create();
        $payload = ['submission_key' => (string) Str::uuid(), 'customer_name' => 'Pelanggan', 'phone' => '080000000000', 'service_id' => $service->id, 'weight' => '2.1'];
        $first = $this->post(route('orders.store'), $payload);
        $first->assertRedirect();
        $second = $this->post(route('orders.store'), array_merge($payload, ['weight' => '9']));
        $second->assertRedirect($first->headers->get('Location'));
        $this->assertDatabaseCount('laundry_orders', 1);
        $this->assertDatabaseHas('laundry_orders', ['submission_key' => $payload['submission_key'], 'total' => 17500]);
    }

    public function test_missing_submission_key_does_not_create_an_order(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post(route('orders.store'), [])->assertSessionHasErrors('submission_key');
        $this->assertDatabaseCount('laundry_orders', 0);
    }

    public function test_archived_service_cannot_be_used_for_new_order(): void
    {
        $this->actingAs(User::factory()->create());
        $service = Service::factory()->create();
        $service->delete();
        $this->post(route('orders.store'), ['submission_key' => (string) Str::uuid(), 'customer_name' => 'Pelanggan', 'phone' => '080000000000', 'service_id' => $service->id, 'weight' => '2'])->assertSessionHasErrors('service_id');
        $this->assertDatabaseCount('laundry_orders', 0);
    }
}
