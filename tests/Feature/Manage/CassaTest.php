<?php

namespace Tests\Feature\Manage;

use App\Enums\Role;
use App\Models\Area;
use App\Models\Event;
use App\Models\Order;
use App\Models\Person;
use App\Models\PersonRole;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CassaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Person $organizer;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->organizer = Person::factory()->organizer()->for($this->tenant)->create();
        $this->event = Event::factory()->for($this->tenant)->create();
    }

    private function evSession(): array
    {
        return ['current_event_id' => $this->event->id];
    }

    public function test_an_organizer_adds_a_product()
    {
        $area = Area::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->organizer)->withSession($this->evSession())->post('/products', [
            'name' => 'Panino', 'price' => 3.5, 'area_id' => $area->id, 'active' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', ['name' => 'Panino', 'event_id' => $this->event->id, 'area_id' => $area->id]);
    }

    public function test_a_manager_cannot_manage_the_listino()
    {
        $manager = Person::factory()->for($this->tenant)->create();
        PersonRole::factory()->create([
            'tenant_id' => $this->tenant->id, 'person_id' => $manager->id,
            'event_id' => $this->event->id, 'area_id' => Area::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id])->id,
            'role' => Role::AreaManager,
        ]);

        $this->actingAs($manager)->withSession($this->evSession())->post('/products', ['name' => 'X', 'price' => 1])->assertForbidden();
    }

    public function test_staff_ring_up_an_order_and_the_total_is_computed()
    {
        $panino = Product::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'price' => 3.50]);
        $birra = Product::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'price' => 4.00]);

        $this->actingAs($this->organizer)->withSession($this->evSession())->post('/orders', [
            'items' => [
                ['product_id' => $panino->id, 'quantity' => 2],
                ['product_id' => $birra->id, 'quantity' => 1],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame('11.00', $order->total); // 2*3.50 + 4.00
        $this->assertSame(1, $order->number);
        $this->assertTrue($order->paid);
        $this->assertSame($this->organizer->id, $order->created_by);
        $this->assertCount(2, $order->items);
    }

    public function test_order_lines_snapshot_the_product()
    {
        $panino = Product::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'name' => 'Panino', 'price' => 3.50]);

        $this->actingAs($this->organizer)->withSession($this->evSession())->post('/orders', [
            'items' => [['product_id' => $panino->id, 'quantity' => 1]],
        ]);

        $panino->update(['name' => 'Panino grande', 'price' => 5]);

        $item = Order::firstOrFail()->items->first();
        $this->assertSame('Panino', $item->name); // snapshot, not the new name
        $this->assertSame('3.50', $item->unit_price);
    }

    public function test_order_numbers_are_sequential_per_event()
    {
        $p = Product::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id]);

        foreach (range(1, 3) as $_) {
            $this->actingAs($this->organizer)->withSession($this->evSession())->post('/orders', [
                'items' => [['product_id' => $p->id, 'quantity' => 1]],
            ]);
        }

        $this->assertSame([1, 2, 3], Order::orderBy('number')->pluck('number')->all());
    }

    public function test_a_product_of_another_event_is_rejected()
    {
        $foreign = Product::factory()->for(Event::factory()->for($this->tenant))->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->organizer)->withSession($this->evSession())->post('/orders', [
            'items' => [['product_id' => $foreign->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('items.0.product_id');

        $this->assertSame(0, Order::count());
    }

    public function test_paid_can_be_toggled()
    {
        $order = Order::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'paid' => true]);

        $this->actingAs($this->organizer)->put("/orders/{$order->id}", ['paid' => false])->assertRedirect();

        $this->assertFalse($order->fresh()->paid);
    }

    public function test_a_plain_volunteer_cannot_reach_the_till()
    {
        $volunteer = Person::factory()->for($this->tenant)->create();

        $this->actingAs($volunteer)->get('/cassa')->assertForbidden();
        $this->actingAs($volunteer)->post('/orders', ['items' => []])->assertForbidden();
    }
}
