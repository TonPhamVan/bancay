<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Synthetic demonstration data is restricted to local/testing.');
        }
        $this->call(DemoDataSeeder::class);
    }
}
