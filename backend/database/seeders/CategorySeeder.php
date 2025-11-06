<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run()
    {
        DB::table('categories')->insert([
            ['name' => 'Món chính'],
            ['name' => 'Món phụ'],
            ['name' => 'Đồ uống'],
            ['name' => 'Tráng miệng'],
            ['name' => 'Ăn vặt'],
        ]);
    }
}