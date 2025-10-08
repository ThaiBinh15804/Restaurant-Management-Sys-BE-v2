<?php

namespace Database\Seeders;

use App\Models\DiningTable;
use Illuminate\Database\Seeder;

class DiningTableSeeder extends Seeder
{
    public function run(): void
    {
        $tables = [
            ['id' => 'TABLE1', 'table_number' => 1, 'capacity' => 4, 'is_active' => true],
            ['id' => 'TABLE2', 'table_number' => 2, 'capacity' => 6, 'is_active' => true],
            ['id' => 'TABLE3', 'table_number' => 3, 'capacity' => 2, 'is_active' => true],
        ];

        foreach ($tables as $table) {
            DiningTable::create($table);
        }
    }
}