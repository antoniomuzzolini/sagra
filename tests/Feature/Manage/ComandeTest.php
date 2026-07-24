<?php

namespace Tests\Feature\Manage;

use App\Enums\OrderItemStatus;
use App\Enums\Role;
use App\Models\Area;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Person;
use App\Models\PersonRole;
use App\Models\Product;
use App\Models\SubArea;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComandeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Person $organizer;

    private Event $event;

    private Area $cucina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->organizer = Person::factory()->organizer()->for($this->tenant)->create();
        $this->event = Event::factory()->for($this->tenant)->create();
        $this->cucina = Area::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'name' => 'Cucina']);
    }

    private function evSession(): array
    {
        return ['current_event_id' => $this->event->id];
    }

    private function lineFor(?Area $area, ?SubArea $sub = null, string $name = 'Panino'): OrderItem
    {
        $order = Order::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id]);

        return $order->items()->create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'unit_price' => 3.5,
            'quantity' => 2,
            'area_id' => $area?->id,
            'sub_area_id' => $sub?->id,
            'status' => OrderItemStatus::Pending,
        ]);
    }

    public function test_a_till_line_starts_pending_and_an_area_less_one_is_served()
    {
        $withArea = Product::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'area_id' => $this->cucina->id]);
        $noArea = Product::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'area_id' => null]);

        $this->actingAs($this->organizer)->withSession($this->evSession())->post('/orders', [
            'items' => [
                ['product_id' => $withArea->id, 'quantity' => 1],
                ['product_id' => $noArea->id, 'quantity' => 1],
            ],
        ])->assertRedirect();

        $items = OrderItem::orderBy('id')->get();
        $this->assertSame(OrderItemStatus::Pending, $items[0]->status);
        // Nothing to prepare without an area: it skips the kitchen queue.
        $this->assertSame(OrderItemStatus::Served, $items[1]->status);
    }

    public function test_the_screen_lists_the_pending_lines_of_the_area()
    {
        $this->lineFor($this->cucina, null, 'Panino');
        $bar = Area::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id]);
        $this->lineFor($bar, null, 'Birra');

        $this->actingAs($this->organizer)->withSession($this->evSession())
            ->get("/comande?area={$this->cucina->id}")
            ->assertInertia(fn ($page) => $page->component('Manage/Comande')
                ->has('items', 1)
                ->where('items.0.name', 'Panino')
            );
    }

    public function test_a_sub_area_screen_only_sees_its_own_lines()
    {
        $griglia = SubArea::factory()->for($this->cucina)->create(['tenant_id' => $this->tenant->id, 'name' => 'Griglia']);
        $friggitoria = SubArea::factory()->for($this->cucina)->create(['tenant_id' => $this->tenant->id, 'name' => 'Friggitoria']);
        $this->lineFor($this->cucina, $griglia, 'Salamella');
        $this->lineFor($this->cucina, $friggitoria, 'Patatine');

        $this->actingAs($this->organizer)->withSession($this->evSession())
            ->get("/comande?area={$this->cucina->id}&sub_area={$griglia->id}")
            ->assertInertia(fn ($page) => $page->has('items', 1)->where('items.0.name', 'Salamella'));
    }

    public function test_a_line_moves_pending_to_ready_to_served()
    {
        $item = $this->lineFor($this->cucina);

        $this->actingAs($this->organizer)->put("/order-items/{$item->id}", ['status' => 'ready'])->assertRedirect();
        $item->refresh();
        $this->assertSame(OrderItemStatus::Ready, $item->status);
        $this->assertNotNull($item->ready_at);

        $this->actingAs($this->organizer)->put("/order-items/{$item->id}", ['status' => 'served'])->assertRedirect();
        $this->assertSame(OrderItemStatus::Served, $item->fresh()->status);
    }

    public function test_served_lines_drop_off_the_screen()
    {
        $item = $this->lineFor($this->cucina);
        $item->update(['status' => OrderItemStatus::Served]);

        $this->actingAs($this->organizer)->withSession($this->evSession())
            ->get("/comande?area={$this->cucina->id}")
            ->assertInertia(fn ($page) => $page->has('items', 0));
    }

    public function test_a_manager_cannot_touch_another_areas_line()
    {
        $bar = Area::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id]);
        $item = $this->lineFor($bar);

        $manager = Person::factory()->for($this->tenant)->create();
        PersonRole::factory()->create([
            'tenant_id' => $this->tenant->id, 'person_id' => $manager->id,
            'event_id' => $this->event->id, 'area_id' => $this->cucina->id, 'role' => Role::AreaManager,
        ]);

        $this->actingAs($manager)->put("/order-items/{$item->id}", ['status' => 'ready'])->assertNotFound();
        $this->assertSame(OrderItemStatus::Pending, $item->fresh()->status);
    }

    public function test_a_plain_volunteer_cannot_reach_the_screen()
    {
        $volunteer = Person::factory()->for($this->tenant)->create();

        $this->actingAs($volunteer)->get('/comande')->assertForbidden();
    }
}
