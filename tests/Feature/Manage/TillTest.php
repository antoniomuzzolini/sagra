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
use App\Models\Till;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TillTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Person $organizer;

    private Event $event;

    private Area $cassa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->organizer = Person::factory()->organizer()->for($this->tenant)->create();
        $this->event = Event::factory()->for($this->tenant)->create();
        $this->cassa = Area::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'name' => 'Cassa']);
    }

    private function evSession(?int $tillId = null): array
    {
        return array_filter(['current_event_id' => $this->event->id, 'current_till_id' => $tillId]);
    }

    private function managerOf(Area $area): Person
    {
        $manager = Person::factory()->for($this->tenant)->create();
        PersonRole::factory()->create([
            'tenant_id' => $this->tenant->id, 'person_id' => $manager->id,
            'event_id' => $this->event->id, 'area_id' => $area->id, 'role' => Role::AreaManager,
        ]);

        return $manager;
    }

    public function test_the_responsabile_of_the_tills_area_creates_and_configures_it()
    {
        $manager = $this->managerOf($this->cassa);

        $this->actingAs($manager)->withSession($this->evSession())->post('/tills', [
            'name' => 'Cassa centrale', 'area_id' => $this->cassa->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $till = Till::firstOrFail();
        $this->assertSame('Cassa centrale', $till->name);
        $this->assertSame($this->event->id, $till->event_id);
    }

    public function test_a_responsabile_of_another_area_cannot_touch_it()
    {
        $bar = Area::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'name' => 'Bar']);
        $till = Till::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'area_id' => $this->cassa->id]);
        $barManager = $this->managerOf($bar);

        $this->actingAs($barManager)->withSession($this->evSession())->put("/tills/{$till->id}", [
            'name' => 'Rubata', 'area_id' => $bar->id,
        ])->assertNotFound();

        $this->actingAs($barManager)->withSession($this->evSession())
            ->put("/tills/{$till->id}/menu", ['products' => []])->assertNotFound();
    }

    public function test_each_reparto_can_run_its_own_till_without_a_cassa_area()
    {
        // The other organisation: no "Cassa" area, one till per reparto.
        $bar = Area::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'name' => 'Bar']);
        $barManager = $this->managerOf($bar);

        $this->actingAs($barManager)->withSession($this->evSession())->post('/tills', [
            'name' => 'Cassa bar', 'area_id' => $bar->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tills', ['name' => 'Cassa bar', 'area_id' => $bar->id]);
    }

    public function test_a_till_without_a_menu_sells_the_whole_listino()
    {
        Product::factory()->count(3)->for($this->event)->create(['tenant_id' => $this->tenant->id]);
        $till = Till::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'area_id' => $this->cassa->id]);

        $this->actingAs($this->organizer)->withSession($this->evSession($till->id))
            ->get('/cassa')->assertInertia(fn ($page) => $page->has('products', 3));
    }

    public function test_two_tills_can_sell_different_menus()
    {
        $panino = Product::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'name' => 'Panino']);
        $birra = Product::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'name' => 'Birra']);

        $tillA = Till::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'area_id' => $this->cassa->id, 'name' => 'Panini']);
        $tillB = Till::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'area_id' => $this->cassa->id, 'name' => 'Bevande']);

        $this->actingAs($this->organizer)->put("/tills/{$tillA->id}/menu", ['products' => [$panino->id]])->assertRedirect();
        $this->actingAs($this->organizer)->put("/tills/{$tillB->id}/menu", ['products' => [$birra->id]])->assertRedirect();

        $this->actingAs($this->organizer)->withSession($this->evSession($tillA->id))->get('/cassa')
            ->assertInertia(fn ($page) => $page->has('products', 1)->where('products.0.name', 'Panino'));

        $this->actingAs($this->organizer)->withSession($this->evSession($tillB->id))->get('/cassa')
            ->assertInertia(fn ($page) => $page->has('products', 1)->where('products.0.name', 'Birra'));
    }

    public function test_an_order_records_the_till_and_refuses_what_it_does_not_sell()
    {
        $panino = Product::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id]);
        $birra = Product::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id]);
        $till = Till::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'area_id' => $this->cassa->id]);
        $till->products()->sync([$panino->id]);

        $session = $this->evSession($till->id);

        $this->actingAs($this->organizer)->withSession($session)->post('/orders', [
            'items' => [['product_id' => $panino->id, 'quantity' => 1]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($till->id, Order::firstOrFail()->till_id);

        // Not on this till's menu.
        $this->actingAs($this->organizer)->withSession($session)->post('/orders', [
            'items' => [['product_id' => $birra->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('items.0.product_id');

        $this->assertSame(1, Order::count());
    }

    public function test_with_no_tills_the_page_still_sells_everything()
    {
        Product::factory()->count(2)->for($this->event)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->organizer)->withSession($this->evSession())->get('/cassa')
            ->assertInertia(fn ($page) => $page->has('products', 2)->where('currentTillId', null));

        $this->actingAs($this->organizer)->withSession($this->evSession())->post('/orders', [
            'items' => [['product_id' => Product::first()->id, 'quantity' => 1]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull(Order::firstOrFail()->till_id);
    }

    public function test_deleting_a_till_keeps_its_orders()
    {
        $till = Till::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'area_id' => $this->cassa->id]);
        $order = Order::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id, 'till_id' => $till->id]);

        $this->actingAs($this->organizer)->delete("/tills/{$till->id}")->assertRedirect();

        $this->assertNotNull($order->fresh());
        $this->assertNull($order->fresh()->till_id);
    }

    public function test_only_the_organizer_owns_a_till_with_no_area()
    {
        $manager = $this->managerOf($this->cassa);

        $this->actingAs($manager)->withSession($this->evSession())
            ->post('/tills', ['name' => 'Senza area', 'area_id' => null])->assertNotFound();

        $this->actingAs($this->organizer)->withSession($this->evSession())
            ->post('/tills', ['name' => 'Senza area', 'area_id' => null])->assertRedirect();
    }

    public function test_the_listino_stays_the_organizers()
    {
        $manager = $this->managerOf($this->cassa);

        $this->actingAs($manager)->withSession($this->evSession())
            ->post('/products', ['name' => 'Panino', 'price' => 3])->assertForbidden();
    }
}
