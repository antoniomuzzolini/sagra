<?php

namespace Tests\Feature\Manage;

use App\Enums\Role;
use App\Models\Area;
use App\Models\Event;
use App\Models\Person;
use App\Models\PersonRole;
use App\Models\Supply;
use App\Models\SupplyAttachment;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupplyAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Person $organizer;

    private Event $event;

    private Area $area;

    private Supply $supply;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->tenant = Tenant::factory()->create();
        $this->organizer = Person::factory()->organizer()->for($this->tenant)->create();
        $this->event = Event::factory()->for($this->tenant)->create();
        $this->area = Area::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id]);
        $this->supply = Supply::factory()->for($this->event)->create([
            'tenant_id' => $this->tenant->id, 'area_id' => $this->area->id,
        ]);
    }

    public function test_an_organizer_uploads_and_downloads_an_attachment()
    {
        $this->actingAs($this->organizer)->post("/supplies/{$this->supply->id}/attachments", [
            'file' => UploadedFile::fake()->create('fattura.pdf', 200, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $attachment = SupplyAttachment::firstOrFail();
        $this->assertSame('fattura.pdf', $attachment->name);
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($this->organizer)->get("/supply-attachments/{$attachment->id}")
            ->assertOk()->assertDownload('fattura.pdf');
    }

    public function test_it_rejects_a_disallowed_file_type()
    {
        $this->actingAs($this->organizer)->post("/supplies/{$this->supply->id}/attachments", [
            'file' => UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, SupplyAttachment::count());
    }

    public function test_deleting_an_attachment_removes_the_file()
    {
        $this->actingAs($this->organizer)->post("/supplies/{$this->supply->id}/attachments", [
            'file' => UploadedFile::fake()->create('nota.pdf', 20, 'application/pdf'),
        ]);
        $attachment = SupplyAttachment::firstOrFail();

        $this->actingAs($this->organizer)->delete("/supply-attachments/{$attachment->id}")->assertRedirect();

        $this->assertNull($attachment->fresh());
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_deleting_the_supply_removes_its_attachment_files()
    {
        $this->actingAs($this->organizer)->post("/supplies/{$this->supply->id}/attachments", [
            'file' => UploadedFile::fake()->create('nota.pdf', 20, 'application/pdf'),
        ]);
        $path = SupplyAttachment::firstOrFail()->path;

        $this->actingAs($this->organizer)->delete("/supplies/{$this->supply->id}");

        Storage::disk('local')->assertMissing($path);
        $this->assertSame(0, SupplyAttachment::count());
    }

    public function test_a_manager_of_another_area_cannot_download()
    {
        $attachment = SupplyAttachment::create([
            'tenant_id' => $this->tenant->id, 'supply_id' => $this->supply->id,
            'path' => 'x/y.pdf', 'name' => 'fattura.pdf', 'mime' => 'application/pdf', 'size' => 1,
        ]);
        Storage::disk('local')->put('x/y.pdf', 'data');

        $otherArea = Area::factory()->for($this->event)->create(['tenant_id' => $this->tenant->id]);
        $stranger = Person::factory()->for($this->tenant)->create();
        PersonRole::factory()->create([
            'tenant_id' => $this->tenant->id, 'person_id' => $stranger->id,
            'event_id' => $this->event->id, 'area_id' => $otherArea->id, 'role' => Role::AreaManager,
        ]);

        $this->actingAs($stranger)->get("/supply-attachments/{$attachment->id}")->assertNotFound();
    }
}
