<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'super-admin',
            'registrar',
            'bursar',
            'admission-officer',
            'dean',
            'hod',
            'exam-officer',
            'lecturer',
            'student',
        ] as $role) {
            Role::findOrCreate($role);
        }
    }
}
