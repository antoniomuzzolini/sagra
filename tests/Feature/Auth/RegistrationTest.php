<?php

namespace Tests\Feature\Auth;

use App\Models\Person;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'association' => 'Pro Loco Test',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('tenants', ['name' => 'Pro Loco Test', 'slug' => 'pro-loco-test']);
        $this->assertNotNull(auth()->user()->tenant_id);
    }

    public function test_registration_requires_an_association_name()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('association');
        $this->assertGuest();
    }

    /**
     * D22: anyone able to create an association could reach the accounts of
     * the existing ones (docs/review/03-roadmap.md §1.1), so once one exists
     * sign-up is closed and answers 404.
     */
    public function test_registration_is_closed_once_an_association_exists()
    {
        Tenant::factory()->create();

        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Mallory',
            'association' => 'Finta Sagra',
            'email' => 'mallory@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertSame(1, Tenant::count());
        $this->assertNull(Person::where('email', 'mallory@example.com')->first());
    }

    public function test_registration_can_be_reopened_by_configuration()
    {
        Tenant::factory()->create();
        config(['fieste.registration_enabled' => true]);

        $this->get('/register')->assertOk();
        $this->post('/register', [
            'name' => 'Test User',
            'association' => 'Seconda Associazione',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertSame(2, Tenant::count());
    }

    public function test_registration_attempts_are_throttled()
    {
        // Invalid attempts (no association name) count too, and keep the
        // client a guest so every request reaches the sign-up route.
        $attempt = fn () => $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        foreach (range(1, 5) as $i) {
            $attempt()->assertSessionHasErrors('association');
        }

        $attempt()->assertTooManyRequests();
    }

    public function test_the_sign_up_links_follow_the_registration_state()
    {
        $this->get('/')->assertInertia(fn ($page) => $page->where('canRegister', true));
        $this->get('/login')->assertInertia(fn ($page) => $page->where('canRegister', true));

        Tenant::factory()->create();

        $this->get('/')->assertInertia(fn ($page) => $page->where('canRegister', false));
        $this->get('/login')->assertInertia(fn ($page) => $page->where('canRegister', false));
    }
}
