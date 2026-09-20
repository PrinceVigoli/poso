<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Violator;
use App\Models\ViolationType;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo accounts may only be seeded in local or testing environments.');
        }
        // ── Users ──────────────────────────────────────────────
        User::create([
            'name'     => 'Admin User',
            'username' => 'admin',
            'email'    => 'admin@poso.gov.ph',
            'password' => Hash::make('password'),
            'role'     => 'admin',
            'is_active'=> true,
        ]);
        User::create([
            'name'     => 'Juan dela Cruz',
            'username' => 'enforcer3',
            'email'    => 'enforcer3@poso.gov.ph',
            'password' => Hash::make('password'),
            'role'     => 'enforcer',
            'is_active'=> true,
        ]);
        User::create([
            'name'     => 'Maria Santos',
            'username' => 'enforcer4',
            'email'    => 'enforcer4@poso.gov.ph',
            'password' => Hash::make('password'),
            'role'     => 'enforcer',
            'is_active'=> true,
        ]);
        User::create([
            'name'     => 'Pedro Reyes',
            'username' => 'enforcer1',
            'email'    => 'enforcer1@poso.gov.ph',
            'password' => Hash::make('password'),
            'role'     => 'enforcer',
            'is_active'=> true,
        ]);
        User::create([
            'name'     => 'Ana Garcia',
            'username' => 'enforcer2',
            'email'    => 'enforcer2@poso.gov.ph',
            'password' => Hash::make('password'),
            'role'     => 'enforcer',
            'is_active'=> true,
        ]);

        // ── Violation Types (single unified category: Traffic Violation) ──
        $types = array_map(fn ($name) => ['offense_name' => $name, 'category' => 'Traffic Violation', 'fine_amount' => null], ['No Side Mirror', 'No Helmet', 'No Vest']);
        foreach ($types as $type) {
            ViolationType::firstOrCreate(['offense_name' => $type['offense_name']], $type);
        }

        // ── Sample Violators (Luna, Apayao barangays; motorcycle/tricycle only) ──
        $violators = [
            ['full_name' => 'Roberto Bautista', 'license_no' => 'N01-23-456789', 'vehicle_plate' => 'ABC 1234', 'vehicle_type' => 'Motorcycle', 'address' => 'Brgy. Poblacion, Luna, Apayao', 'contact_no' => '09171234567'],
            ['full_name' => 'Lourdes Manalo',   'license_no' => 'N01-23-112233', 'vehicle_plate' => 'XYZ 5678', 'vehicle_type' => 'Tricycle',   'address' => 'Brgy. Dagupan, Luna, Apayao',   'contact_no' => '09281234567'],
            ['full_name' => 'Felix Corpuz',     'license_no' => 'N01-22-998877', 'vehicle_plate' => 'DEF 9012', 'vehicle_type' => 'Tricycle',   'address' => 'Brgy. Bacsay, Luna, Apayao',    'contact_no' => '09391234567'],
            ['full_name' => 'Crisanta Valdez',  'license_no' => 'N01-21-445566', 'vehicle_plate' => 'GHI 3456', 'vehicle_type' => 'Motorcycle', 'address' => 'Brgy. Lappa, Luna, Apayao',     'contact_no' => '09501234567'],
            ['full_name' => 'Danilo Ramos',     'license_no' => 'N01-20-778899', 'vehicle_plate' => 'JKL 7890', 'vehicle_type' => 'Tricycle',   'address' => 'Brgy. Marag, Luna, Apayao',     'contact_no' => '09611234567'],
        ];
        foreach ($violators as $v) {
            Violator::create($v);
        }
    }
}
