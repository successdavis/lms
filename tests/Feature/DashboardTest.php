<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_users_without_a_role_are_sent_to_the_application_portal()
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')->assertRedirect(route('applicant.show'));
    }

    public function test_staff_users_can_visit_the_dashboard()
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('registrar');
        $this->actingAs($user);

        $this->get('/dashboard')->assertOk();
    }
}
