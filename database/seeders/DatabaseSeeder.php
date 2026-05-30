<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
            ],
        );

        collect([
            [
                'icon' => '01',
                'title' => 'Offer clarity',
                'summary' => 'Structure the page around the customer problem, service promise, and next step.',
            ],
            [
                'icon' => '02',
                'title' => 'Reusable sections',
                'summary' => 'Edit once, reuse across pages, and keep the site system small enough to understand.',
            ],
            [
                'icon' => '03',
                'title' => 'Token-driven theme',
                'summary' => 'Adjust color, spacing, radius, and typography without hunting through every artifact.',
            ],
        ])->each(fn (array $service, int $index) => Service::updateOrCreate(
            ['title' => $service['title']],
            [
                ...$service,
                'position' => $index + 1,
                'is_published' => true,
            ],
        ));
    }
}
