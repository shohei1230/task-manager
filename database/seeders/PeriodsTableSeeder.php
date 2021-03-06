<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class PeriodsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
      DB::table('periods')->insert([
        'start' => '2021-06-01',
        'end' => '2021-06-30',
        'user_id' => 1,
      ]);
    }
}