<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        
        \App\Models\User::factory(10)->create();

        \App\Models\User::factory()->create([
            'name' => 'Test User',
            'email' => 'c.perroni@example.com',
        ]);

        // \App\Models\Company::factory(4)->create();

        // \App\Models\TicketType::factory(8)
        //     ->has(\App\Models\Company::factory()->count(3))
        //     ->has(\App\Models\TypeFormFields::factory()->count(5))
        //     ->create();

        \App\Models\Company::factory(4)
            ->has(\App\Models\TicketType::factory(8)
                ->has(\App\Models\TypeFormFields::factory()->count(5))
                ->count(3))
            ->create();
        
    }
}
