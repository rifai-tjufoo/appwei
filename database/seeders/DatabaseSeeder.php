<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Sender;
use App\Models\User;
use App\Services\AppSettings;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password'),
            ],
        );

        AppSetting::set(AppSettings::WHATSAPP_API_URL, 'https://wa.forfunforlife.com');
        AppSetting::set(AppSettings::WHATSAPP_API_KEY, 'your-api-key-here');

        $senders = collect([
            ['name' => 'Sender 1', 'phone' => '628111111111'],
            ['name' => 'Sender 2', 'phone' => '628222222222'],
        ])->map(fn (array $data) => Sender::query()->updateOrCreate(
            ['phone' => $data['phone']],
            ['name' => $data['name'], 'is_active' => true],
        ));

        $customers = collect([
            ['name' => 'Customer A', 'phone' => '628333333333'],
            ['name' => 'Customer B', 'phone' => '628444444444'],
            ['name' => 'Customer C', 'phone' => '628555555555'],
        ])->map(fn (array $data) => Customer::query()->updateOrCreate(
            ['phone' => $data['phone']],
            ['name' => $data['name']],
        ));

        $group = CustomerGroup::query()->updateOrCreate(
            ['name' => 'Default Group'],
            ['description' => 'Sample customer group'],
        );

        $group->customers()->sync($customers->pluck('id'));
    }
}
