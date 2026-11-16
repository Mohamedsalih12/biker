<?php

namespace Tests\Feature;

use App\Livewire\Admin\VehicleTable;
use App\Livewire\FleetDashboard;
use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class VehicleGalleryTest extends TestCase
{
    public function test_multiple_uploads_are_saved_with_only_the_first_photo_shown_on_the_vehicle_index(): void
    {
        Storage::fake('public');
        $front = UploadedFile::fake()->image('front.jpg');
        $side = UploadedFile::fake()->image('side.png');

        $this->actingAsUser()->post(route('xpress.vehicles.store'), [
            'registration_number' => 'GALLERY-001', 'images' => [$front, $side],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $vehicle = Vehicle::query()->sole();
        $paths = ['vehicles/'.$front->hashName(), 'vehicles/'.$side->hashName()];
        $this->assertSame($paths, $vehicle->images);
        $this->assertSame($paths[0], $vehicle->image);
        Storage::disk('public')->assertExists($paths);
        foreach (['edit', 'show'] as $action) {
            $this->get(route('xpress.vehicles.'.$action, $vehicle))
                ->assertSee('/storage/'.$paths[0], false)->assertSee('/storage/'.$paths[1], false);
        }
        Livewire::actingAs($this->user)->test(VehicleTable::class)
            ->assertSee('/storage/'.$paths[0], false)->assertDontSee('/storage/'.$paths[1], false);
        Livewire::test(FleetDashboard::class)
            ->assertSee('/storage/'.$paths[0], false)->assertSee('/storage/'.$paths[1], false);
        $this->get(route('xpress.vehicles.create'))->assertSee('name="images[]"', false)
            ->assertSee('multiple', false);
    }

    public function test_uploads_append_to_a_legacy_photo_and_unchanged_updates_preserve_the_gallery(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('vehicles/legacy.jpg', 'old');
        $vehicle = Vehicle::factory()->create(['image' => 'vehicles/legacy.jpg']);
        $photo = UploadedFile::fake()->image('new.webp');

        $this->actingAsUser()->put(route('xpress.vehicles.update', $vehicle), [
            'registration_number' => $vehicle->registration_number, 'images' => [$photo],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->put(route('xpress.vehicles.update', $vehicle), [
            'registration_number' => $vehicle->registration_number,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(['vehicles/legacy.jpg', 'vehicles/'.$photo->hashName()], $vehicle->fresh()->images);
        $this->assertSame('vehicles/legacy.jpg', $vehicle->fresh()->image);
        Storage::disk('public')->assertExists(['vehicles/legacy.jpg', 'vehicles/'.$photo->hashName()]);
    }

    public function test_selected_photos_can_be_replaced_removed_and_added_in_one_save(): void
    {
        Storage::fake('public');
        $paths = ['vehicles/front.jpg', 'vehicles/side.jpg', 'vehicles/back.jpg'];
        foreach ($paths as $path) {
            Storage::disk('public')->put($path, 'old');
        }
        $vehicle = Vehicle::factory()->create(['image' => $paths[0], 'images' => $paths]);
        $replacement = UploadedFile::fake()->image('replacement.jpg');
        $added = UploadedFile::fake()->image('added.png');

        $this->actingAsUser()->put(route('xpress.vehicles.update', $vehicle), [
            'registration_number' => $vehicle->registration_number,
            'replacement_images' => [hash('sha256', $paths[0]) => $replacement],
            'remove_images' => [hash('sha256', $paths[1])],
            'images' => [$added],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(['vehicles/'.$replacement->hashName(), $paths[2], 'vehicles/'.$added->hashName()], $vehicle->fresh()->images);
        $this->assertSame('vehicles/'.$replacement->hashName(), $vehicle->fresh()->image);
        Storage::disk('public')->assertMissing([$paths[0], $paths[1]]);
        Storage::disk('public')->assertExists([$paths[2], 'vehicles/'.$replacement->hashName(), 'vehicles/'.$added->hashName()]);
        $this->assertDatabaseHas('activity_log', ['subject_id' => $vehicle->id, 'log_name' => 'vehicle', 'event' => 'updated']);
    }

    public function test_all_photos_can_be_removed_from_the_form(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('vehicles/only.jpg', 'old');
        $vehicle = Vehicle::factory()->create(['image' => 'vehicles/only.jpg']);

        $this->actingAsUser()->put(route('xpress.vehicles.update', $vehicle), [
            'registration_number' => $vehicle->registration_number,
            'remove_images' => [hash('sha256', 'vehicles/only.jpg')],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame([], $vehicle->fresh()->images);
        $this->assertNull($vehicle->fresh()->image);
        Storage::disk('public')->assertMissing('vehicles/only.jpg');
    }

    public function test_grid_removes_only_the_selected_gallery_photo_and_promotes_the_next_cover(): void
    {
        Storage::fake('public');
        $paths = ['vehicles/front.jpg', 'vehicles/side.jpg'];
        foreach ($paths as $path) {
            Storage::disk('public')->put($path, 'photo');
        }
        $vehicle = Vehicle::factory()->create(['image' => $paths[0], 'images' => $paths]);

        Livewire::actingAs($this->user)->test(VehicleTable::class)
            ->call('removePhoto', $vehicle->id, hash('sha256', $paths[0]))->assertHasNoErrors()
            ->assertSee('/storage/'.$paths[1], false)->assertDontSee('/storage/'.$paths[0], false);

        $this->assertSame([$paths[1]], $vehicle->fresh()->images);
        $this->assertSame($paths[1], $vehicle->fresh()->image);
        Storage::disk('public')->assertMissing($paths[0]);
        Storage::disk('public')->assertExists($paths[1]);
    }

    #[TestWith(['remove_images'])]
    #[TestWith(['replacement_images'])]
    public function test_another_vehicles_photo_cannot_be_modified(string $field): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('vehicles/other.jpg', 'protected');
        $vehicle = Vehicle::factory()->create();
        $other = Vehicle::factory()->create(['image' => 'vehicles/other.jpg']);
        $key = hash('sha256', 'vehicles/other.jpg');
        $value = $field === 'remove_images' ? [$key] : [$key => UploadedFile::fake()->image('bad.jpg')];

        $this->actingAsUser()->put(route('xpress.vehicles.update', $vehicle), [
            'registration_number' => $vehicle->registration_number, $field => $value,
        ])->assertSessionHasErrors($field);

        $this->assertNull($vehicle->fresh()->image);
        $this->assertSame('vehicles/other.jpg', $other->fresh()->image);
        $this->assertSame(['vehicles/other.jpg'], Storage::disk('public')->allFiles());
    }

    public function test_forged_grid_photo_key_does_not_remove_any_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('vehicles/safe.jpg', 'safe');
        $vehicle = Vehicle::factory()->create(['image' => 'vehicles/safe.jpg']);

        Livewire::actingAs($this->user)->test(VehicleTable::class)
            ->call('removePhoto', $vehicle->id, hash('sha256', 'vehicles/other.jpg'))
            ->assertHasErrors('image');

        $this->assertSame('vehicles/safe.jpg', $vehicle->fresh()->image);
        Storage::disk('public')->assertExists('vehicles/safe.jpg');
    }

    public function test_invalid_gallery_uploads_are_rejected_without_saving_any_files(): void
    {
        Storage::fake('public');

        $this->actingAsUser()->post(route('xpress.vehicles.store'), [
            'registration_number' => 'INVALID-GALLERY',
            'images' => [UploadedFile::fake()->image('valid.jpg'), UploadedFile::fake()->create('payload.php', 1, 'text/plain')],
        ])->assertSessionHasErrors('images.1');

        $this->assertDatabaseCount('vehicles', 0);
        Storage::disk('public')->assertEmpty();
    }

    public function test_cancelled_gallery_save_cleans_new_files_and_keeps_originals(): void
    {
        Storage::fake('public');
        $paths = ['vehicles/old.jpg', 'vehicles/keep.jpg'];
        foreach ($paths as $path) {
            Storage::disk('public')->put($path, 'old');
        }
        $vehicle = Vehicle::factory()->create(['image' => $paths[0], 'images' => $paths]);
        Vehicle::updating(fn (Vehicle $record): bool => false);
        $replacement = UploadedFile::fake()->image('new.jpg');
        $added = UploadedFile::fake()->image('added.jpg');

        $this->actingAsUser()->put(route('xpress.vehicles.update', $vehicle), [
            'registration_number' => $vehicle->registration_number,
            'replacement_images' => [hash('sha256', $paths[0]) => $replacement],
            'remove_images' => [hash('sha256', $paths[1])], 'images' => [$added],
        ])->assertSessionHasErrors('vehicle');

        $this->assertSame($paths, $vehicle->fresh()->images);
        Storage::disk('public')->assertExists($paths);
        Storage::disk('public')->assertMissing(['vehicles/'.$replacement->hashName(), 'vehicles/'.$added->hashName()]);
    }

    public function test_gallery_upload_requires_vehicle_management_permission(): void
    {
        Storage::fake('public');

        $this->actingAs($this->otherUser)->post(route('xpress.vehicles.store'), [
            'registration_number' => 'DENIED-GALLERY', 'images' => [UploadedFile::fake()->image('new.jpg')],
        ])->assertForbidden();

        Storage::disk('public')->assertEmpty();
    }

    #[TestWith(['images', 'oversized'])]
    #[TestWith(['images', 'svg'])]
    #[TestWith(['images', 'path'])]
    #[TestWith(['replacement_images', 'oversized'])]
    #[TestWith(['replacement_images', 'svg'])]
    #[TestWith(['replacement_images', 'path'])]
    public function test_each_gallery_file_must_be_a_safe_image_under_two_megabytes(string $field, string $kind): void
    {
        Storage::fake('public');
        $vehicle = Vehicle::factory()->create(['image' => 'vehicles/original.jpg']);
        $key = $field === 'images' ? 0 : hash('sha256', 'vehicles/original.jpg');
        $file = match ($kind) {
            'oversized' => UploadedFile::fake()->image('huge.jpg')->size(2049),
            'svg' => UploadedFile::fake()->createWithContent('unsafe.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'path' => 'vehicles/forged.jpg',
        };

        $this->actingAsUser()->put(route('xpress.vehicles.update', $vehicle), [
            'registration_number' => $vehicle->registration_number, $field => [$key => $file],
        ])->assertSessionHasErrors($field.'.'.$key);

        $this->assertSame('vehicles/original.jpg', $vehicle->fresh()->image);
        Storage::disk('public')->assertEmpty();
    }

    public function test_a_picture_cannot_be_removed_and_replaced_in_the_same_request(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('vehicles/original.jpg', 'old');
        $vehicle = Vehicle::factory()->create(['image' => 'vehicles/original.jpg']);
        $key = hash('sha256', 'vehicles/original.jpg');

        $this->actingAsUser()->put(route('xpress.vehicles.update', $vehicle), [
            'registration_number' => $vehicle->registration_number,
            'replacement_images' => [$key => UploadedFile::fake()->image('new.jpg')], 'remove_images' => [$key],
        ])->assertSessionHasErrors('remove_images');

        $this->assertSame('vehicles/original.jpg', $vehicle->fresh()->image);
        $this->assertSame(['vehicles/original.jpg'], Storage::disk('public')->allFiles());
    }

    public function test_storage_failure_halfway_through_upload_cleans_previously_stored_photos(): void
    {
        $disk = Storage::fake('public');
        $first = UploadedFile::fake()->image('first.jpg');
        $second = UploadedFile::fake()->image('second.jpg');
        $failingDisk = \Mockery::mock($disk)->makePartial();
        $calls = 0;
        $failingDisk->shouldReceive('putFileAs')->andReturnUsing(function (...$arguments) use ($disk, &$calls): string|false {
            $calls++;

            return $calls === 2 ? false : $disk->putFileAs(...$arguments);
        });
        Storage::shouldReceive('disk')->with('public')->andReturn($failingDisk);

        $this->actingAsUser()->post(route('xpress.vehicles.store'), [
            'registration_number' => 'STORAGE-FAILURE', 'images' => [$first, $second],
        ])->assertSessionHasErrors('images.1');

        $this->assertDatabaseCount('vehicles', 0);
        $disk->assertEmpty();
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'vehicle', 'event' => 'created']);
    }

    public function test_legacy_cover_upload_cannot_overwrite_an_individual_replacement(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('vehicles/original.jpg', 'original');
        $vehicle = Vehicle::factory()->create(['image' => 'vehicles/original.jpg']);

        $this->actingAsUser()->put(route('xpress.vehicles.update', $vehicle), [
            'registration_number' => $vehicle->registration_number,
            'image' => UploadedFile::fake()->image('legacy.jpg'),
            'replacement_images' => [hash('sha256', 'vehicles/original.jpg') => UploadedFile::fake()->image('replacement.jpg')],
        ])->assertSessionHasErrors('image');

        $this->assertSame('vehicles/original.jpg', $vehicle->fresh()->image);
        $this->assertSame(['vehicles/original.jpg'], Storage::disk('public')->allFiles());
    }

    public function test_gallery_removal_requires_an_explicit_photo_key(): void
    {
        Storage::fake('public');
        $paths = ['vehicles/front.jpg', 'vehicles/side.jpg'];
        foreach ($paths as $path) {
            Storage::disk('public')->put($path, 'photo');
        }
        $vehicle = Vehicle::factory()->create(['image' => $paths[0], 'images' => $paths]);

        Livewire::actingAs($this->user)->test(VehicleTable::class)
            ->call('removePhoto', $vehicle->id)->assertHasErrors('image');

        $this->assertSame($paths, $vehicle->fresh()->images);
        Storage::disk('public')->assertExists($paths);
    }
}
