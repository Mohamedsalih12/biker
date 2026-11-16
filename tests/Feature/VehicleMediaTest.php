<?php

namespace Tests\Feature;

use App\Livewire\Admin\VehicleTable;
use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class VehicleMediaTest extends TestCase
{
    public function test_photo_url_uses_the_page_origin_instead_of_a_stale_disk_url(): void
    {
        config(['filesystems.disks.public.url' => 'http://localhost/storage']);
        Vehicle::factory()->create(['image' => 'vehicles/origin.jpg']);

        $this->actingAsUser()->get('http://127.0.0.1:8000/admin/vehicles')
            ->assertSee('src="http://127.0.0.1:8000/storage/vehicles/origin.jpg"', false)
            ->assertDontSee('src="http://localhost/storage/vehicles/origin.jpg"', false);
    }

    public function test_index_shows_photo_before_vehicle_details_with_remove_control(): void
    {
        $vehicle = Vehicle::factory()->create(['image' => 'vehicles/index.jpg']);

        Livewire::actingAs($this->user)->test(VehicleTable::class)
            ->assertSee('src="'.$vehicle->imageUrl().'"', false)
            ->assertSeeInOrder(['data-column="image_preview"', 'data-column="registration_number"'], false)
            ->assertSee('Remove photo')
            ->assertSee('wire:confirm=', false);
    }

    public function test_remove_photo_deletes_only_the_selected_image_and_refreshes_the_grid(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('vehicles/remove.jpg', 'image');
        Storage::disk('public')->put('vehicles/keep.jpg', 'other image');
        $vehicle = Vehicle::factory()->create(['image' => 'vehicles/remove.jpg', 'damages' => 'Existing damage']);
        $other = Vehicle::factory()->create(['image' => 'vehicles/keep.jpg']);

        Livewire::actingAs($this->user)->test(VehicleTable::class)
            ->call('removePhoto', $vehicle->id)->assertHasNoErrors()
            ->assertDontSee('src="'.Storage::disk('public')->url('vehicles/remove.jpg').'"', false)
            ->assertSee('No photo');

        $this->assertModelExists($vehicle);
        $this->assertNull($vehicle->fresh()->image);
        $this->assertSame('Existing damage', $vehicle->fresh()->damages);
        $this->assertSame('vehicles/keep.jpg', $other->fresh()->image);
        Storage::disk('public')->assertMissing('vehicles/remove.jpg');
        Storage::disk('public')->assertExists('vehicles/keep.jpg');
        $this->assertDatabaseHas('activity_log', ['log_name' => 'vehicle', 'event' => 'updated', 'subject_id' => $vehicle->id]);
    }

    public function test_lost_admin_permission_blocks_photo_removal(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('vehicles/protected.jpg', 'image');
        $vehicle = Vehicle::factory()->create(['image' => 'vehicles/protected.jpg']);
        $grid = Livewire::actingAs($this->user)->test(VehicleTable::class);
        $this->user->forceFill(['role' => 'staff'])->save();

        $grid->call('removePhoto', $vehicle->id)->assertForbidden();

        $this->assertSame('vehicles/protected.jpg', $vehicle->fresh()->image);
        Storage::disk('public')->assertExists('vehicles/protected.jpg');
    }

    public function test_cancelled_photo_removal_preserves_the_file_and_reference(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('vehicles/cancel.jpg', 'image');
        $vehicle = Vehicle::factory()->create(['image' => 'vehicles/cancel.jpg']);
        Vehicle::updating(fn (Vehicle $record): bool => false);

        Livewire::actingAs($this->user)->test(VehicleTable::class)
            ->call('removePhoto', $vehicle->id)->assertHasErrors('image');

        $this->assertSame('vehicles/cancel.jpg', $vehicle->fresh()->image);
        Storage::disk('public')->assertExists('vehicles/cancel.jpg');
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'vehicle', 'event' => 'updated']);
    }

    public function test_removing_an_already_missing_photo_is_safe(): void
    {
        Storage::fake('public');
        $vehicle = Vehicle::factory()->create(['image' => 'vehicles/missing.jpg']);

        Livewire::actingAs($this->user)->test(VehicleTable::class)
            ->call('removePhoto', $vehicle->id)->assertHasNoErrors()
            ->call('removePhoto', $vehicle->id)->assertHasNoErrors();

        $this->assertNull($vehicle->fresh()->image);
        $this->assertModelExists($vehicle);
    }

    public function test_storage_delete_failure_keeps_the_photo_reference_and_rolls_back_audit(): void
    {
        $disk = Storage::fake('public');
        $disk->put('vehicles/failure.jpg', 'image');
        $vehicle = Vehicle::factory()->create(['image' => 'vehicles/failure.jpg']);
        $failingDisk = \Mockery::mock($disk)->makePartial();
        $failingDisk->shouldReceive('delete')->with('vehicles/failure.jpg')->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($failingDisk);

        Livewire::actingAs($this->user)->test(VehicleTable::class)
            ->call('removePhoto', $vehicle->id)->assertHasErrors('image');

        $this->assertSame('vehicles/failure.jpg', $vehicle->fresh()->image);
        $disk->assertExists('vehicles/failure.jpg');
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'vehicle', 'event' => 'updated']);
    }

    public function test_photo_and_damage_notes_are_saved_and_displayed(): void
    {
        Storage::fake('public');
        $photo = UploadedFile::fake()->image('vehicle.jpg');
        $damages = "Scratched left panel\nCracked mirror <script>alert(1)</script>";

        $this->actingAsUser()->post(route('xpress.vehicles.store'), [
            'registration_number' => 'PHOTO-001', 'image' => $photo, 'damages' => $damages,
        ])->assertRedirect();

        $vehicle = Vehicle::query()->sole();
        $this->assertSame('vehicles/'.$photo->hashName(), $vehicle->image);
        $this->assertSame($damages, $vehicle->damages);
        Storage::disk('public')->assertExists($vehicle->image);
        $this->get(route('xpress.vehicles.show', $vehicle))->assertSee(Storage::disk('public')->url($vehicle->image))
            ->assertSee($damages)->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('xpress.vehicles.edit', $vehicle))->assertSee('multipart/form-data', false)
            ->assertSee(Storage::disk('public')->url($vehicle->image))->assertSee($damages);
        $this->get(route('xpress.vehicles.create'))->assertSee('multipart/form-data', false)->assertSee('name="images[]"', false)->assertSee('name="damages"', false);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'vehicle', 'event' => 'created', 'subject_id' => $vehicle->id]);
    }

    public function test_photo_is_preserved_without_upload_and_replaced_with_new_upload(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('vehicles/old.jpg', 'old');
        $vehicle = Vehicle::factory()->create(['image' => 'vehicles/old.jpg', 'damages' => 'Old damage']);

        $this->actingAsUser()->put(route('xpress.vehicles.update', $vehicle), [
            'registration_number' => $vehicle->registration_number, 'image' => null, 'damages' => null,
        ])->assertRedirect();

        $this->assertSame('vehicles/old.jpg', $vehicle->fresh()->image);
        $this->assertNull($vehicle->fresh()->damages);
        $photo = UploadedFile::fake()->image('replacement.png');
        $this->post(route('xpress.vehicles.update', $vehicle), [
            '_method' => 'PUT', 'registration_number' => $vehicle->registration_number, 'image' => $photo,
        ])->assertRedirect();

        $this->assertSame('vehicles/'.$photo->hashName(), $vehicle->fresh()->image);
        Storage::disk('public')->assertExists('vehicles/'.$photo->hashName());
        Storage::disk('public')->assertMissing('vehicles/old.jpg');
    }

    public function test_invalid_uploads_and_damage_notes_are_rejected_without_storing_files(): void
    {
        Storage::fake('public');
        $this->actingAsUser();
        foreach ([
            UploadedFile::fake()->create('payload.php', 1, 'text/plain'),
            UploadedFile::fake()->image('huge.jpg')->size(2049),
            UploadedFile::fake()->createWithContent('unsafe.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'vehicles/forged.jpg',
        ] as $image) {
            $this->post(route('xpress.vehicles.store'), ['registration_number' => 'BAD-PHOTO', 'image' => $image])->assertSessionHasErrors('image');
        }
        $this->post(route('xpress.vehicles.store'), ['registration_number' => 'BAD-NOTES', 'damages' => str_repeat('x', 10001)])
            ->assertSessionHasErrors('damages');

        Storage::disk('public')->assertEmpty();
        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_unauthorized_upload_does_not_store_a_photo(): void
    {
        Storage::fake('public');
        $this->actingAs($this->otherUser)->post(route('xpress.vehicles.store'), [
            'registration_number' => 'DENIED-PHOTO', 'image' => UploadedFile::fake()->image('denied.jpg'),
        ])->assertForbidden();

        Storage::disk('public')->assertEmpty();
    }

    public function test_cancelled_save_cleans_up_new_photo_and_preserves_old_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('vehicles/old.jpg', 'old');
        $vehicle = Vehicle::factory()->create(['image' => 'vehicles/old.jpg']);
        Vehicle::updating(fn (Vehicle $record): bool => false);
        $photo = UploadedFile::fake()->image('failed.jpg');

        $this->actingAsUser()->post(route('xpress.vehicles.update', $vehicle), [
            '_method' => 'PUT', 'registration_number' => $vehicle->registration_number, 'image' => $photo,
        ])->assertSessionHasErrors('vehicle');

        $this->assertSame('vehicles/old.jpg', $vehicle->fresh()->image);
        Storage::disk('public')->assertExists('vehicles/old.jpg');
        Storage::disk('public')->assertMissing('vehicles/'.$photo->hashName());
    }

    public function test_grid_searches_damage_notes_and_exports_safe_text_with_image_path(): void
    {
        $vehicle = Vehicle::factory()->create(['image' => 'vehicles/photo.jpg', 'damages' => '=DANGER()']);
        Vehicle::factory()->create(['damages' => 'No scratches']);
        $grid = Livewire::actingAs($this->user)->test(VehicleTable::class)->set('search', 'DANGER')
            ->assertSee($vehicle->registration_number)->assertSee('=DANGER()')
            ->call('exportToXLS')->assertFileDownloaded('vehicles.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'vehicle-media-');

        try {
            file_put_contents($path, base64_decode($grid->effects['download']['content']));
            $workbook = IOFactory::load($path);
            $sheet = $workbook->getActiveSheet();
            $this->assertSame(2, $sheet->getHighestDataRow());
            $this->assertSame('Image Path', $sheet->getCell('N1')->getFormattedValue());
            $this->assertSame('vehicles/photo.jpg', $sheet->getCell('N2')->getFormattedValue());
            $this->assertSame('Damages', $sheet->getCell('O1')->getFormattedValue());
            $this->assertSame("'=DANGER()", $sheet->getCell('O2')->getFormattedValue());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }
}
