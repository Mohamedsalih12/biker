<?php

namespace Tests\Feature\Frontend;

use Tests\TestCase;

class UserControllerTest extends TestCase
{
    public function test_profile_page_displays_name_and_read_only_email_without_photo_fields(): void
    {
        $this->actingAsUser()->get(route('xpress.profile.edit'))
            ->assertOk()
            ->assertSee('name="name"', false)
            ->assertSee('type="email" readonly', false)
            ->assertDontSee('name="phone"', false)
            ->assertDontSee('name="file1"', false);
    }

    public function test_profile_update_changes_only_the_authenticated_users_name(): void
    {
        $email = $this->user->email;
        $otherName = $this->otherUser->name;

        $this->actingAsUser()->from(route('xpress.profile.edit'))
            ->put(route('xpress.profile.update'), [
                'name' => 'Updated Profile',
                'email' => 'changed@example.com',
                'user_id' => $this->otherUser->id,
                'photo_1' => 'retired-photo.jpg',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('xpress.profile.edit'));

        $this->assertSame('Updated Profile', $this->user->fresh()->name);
        $this->assertSame($email, $this->user->fresh()->email);
        $this->assertSame($otherName, $this->otherUser->fresh()->name);
    }

    public function test_profile_update_requires_a_name(): void
    {
        $name = $this->user->name;

        $this->actingAsUser()->put(route('xpress.profile.update'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertSame($name, $this->user->fresh()->name);
    }

    public function test_guest_cannot_update_a_profile(): void
    {
        $this->put(route('xpress.profile.update'), ['name' => 'Changed'])
            ->assertRedirect(route('login'));
    }

    public function test_admin_profile_page_displays_only_basic_profile_fields(): void
    {
        $this->actingAsUser()->get(route('xpress.adminprofile.edit', $this->otherUser))
            ->assertOk()
            ->assertSee($this->otherUser->email)
            ->assertDontSee('name="phone"', false)
            ->assertDontSee('name="file1"', false);
    }

    public function test_admin_profile_update_changes_name_but_not_email(): void
    {
        $email = $this->otherUser->email;

        $this->actingAsUser()->put(route('xpress.adminprofile.update', $this->otherUser), [
            'name' => 'Updated By Admin',
            'email' => 'changed@example.com',
        ])->assertRedirect(route('xpress.users.index'));

        $this->assertSame('Updated By Admin', $this->otherUser->fresh()->name);
        $this->assertSame($email, $this->otherUser->fresh()->email);
    }
}
