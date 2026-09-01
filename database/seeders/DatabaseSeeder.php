<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            SettingSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            DocumentSequenceSeeder::class,
            ExpenseCategorySeeder::class,
            LocationSeeder::class,
            ProductSeeder::class,
            ContainerCatalogSeeder::class,
            SupplierSeeder::class,
            DepotSeeder::class,
            CarrierSeeder::class,
            DriverSeeder::class,
            VehicleSeeder::class,
            CustomerSeeder::class,
            ContainerSeeder::class,
            NotificationRuleSeeder::class,
        ]);
    }
}
