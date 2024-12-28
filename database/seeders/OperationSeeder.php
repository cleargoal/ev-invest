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
                'key'   => 'first',
                'description' => 'Виконується коли ще не було внесків, або попередньо вилучені всі активи.',
            ],
            [
                'title' => 'Купівля машини',
                'key'   => 'buy-car',
                'description' => 'Введення машини в обіг.',
                'car' => true,
            ],
            [
                'title' => 'Продаж машини',
                'key'   => 'sell-car',
                'description' => 'Машина продана, нараховуються прибутки - операція збільшує загальний пул',
                'car' => true,
            ],
            [
                'title' => 'Грошовий внесок',
                'key'   => 'contrib',
                'description' => 'Внесок грошей інвестором. Операцію не використовувати при купівлі авто.',
            ],
            [
                'title' => 'Вилучення грошей',
                'key'   => 'withdraw',
                'description' => 'Вилучення грошей інвестором. Операцію не використовувати при купівлі або продажу авто.',
            ],
            [
                'title' => 'Дохід інвестора',
                'key'   => 'income',
                'description' => 'Нарахований дохід інвестора після продажу авто. Операція проводиться автоматично після продажу авто.',
            ],
            [
                'title' => 'Прибуток компанії від продажу',
                'key'   => 'revenue',
                'description' => 'Нарахований прибуток компанії від продажу авто. Операція проводиться автоматично після продажу авто',
            ],
            [
                'title' => 'Прибуток компанії від оренди',
                'key'   => 'c-leasing',
                'description' => 'Нарахований прибуток компанії від здачі авто в оренду. Операція проводиться автоматично після завершення терміну здачі авто в оренду',
            ],
            [
                'title' => 'Прибуток інвестора від оренди',
                'key'   => 'i-leasing',
                'description' => 'Нарахований прибуток інвестора від здачі авто в оренду після віднімання комісії компанії. Операція проводиться автоматично після завершення терміну здачі авто в оренду',
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
