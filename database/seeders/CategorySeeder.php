<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['Работа',     '#8b5cf6', 'work'],
            ['Учёба',      '#06b6d4', 'study'],
            ['Программирование', '#6366f1', 'work'],
            ['Отдых',      '#14b8a6', 'rest'],
            ['Прочее',     '#64748b', 'other'],
        ];

        foreach ($items as [$name, $color, $type]) {
            Category::firstOrCreate(
                ['user_id' => null, 'name' => $name],
                ['color' => $color, 'type' => $type]
            );
        }
    }
}
