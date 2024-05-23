<?php

namespace Database\Seeders;

use App\Models\Operation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OperationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'title' => '1-й внесок',
                'description' => 'Виконується коли ще не було внесків, або попередньо вилучені всі активи.',
            ],
            [
                'title' => 'Купівля машини',
                'description' => 'Введення машини в обіг - операція збільшує загальний пул',
                'car' => true,
            ],
            [
                'title' => 'Продаж машини',
                'description' => 'Машина продана, гроші вилучаються з обігу',
                'car' => true,
            ],
            [
                'title' => 'Грошовий внесок',
                'description' => 'Внесок грошей, як інвестором, так можливо і оператором. Операцію не використовувати при купівлі авто',
            ],
            [
                'title' => 'Вилучення грошей',
                'description' => 'Вилучення грошей, як інвестором, так можливо і оператором. Операцію не використовувати при купівлі авто',
            ],
            [
                'title' => 'Доход інвестора',
                'description' => 'Нарахований доход іевестора після продажу авто. Операція проводиться автоматично після продажу авто',
            ],
            [
                'title' => 'Комісія компанії',
                'description' => 'Нарахована комісія компанії після продажу авто. Операція проводиться автоматично після продажу авто',
            ],
        ];

        foreach ($data as $item) {
            $new = new Operation();
            foreach ($item as $field => $value) {
                $new->$field = $value;
            }
            $new->save();
        }

    }
}
