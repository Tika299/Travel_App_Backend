<?php
namespace Database\Seeders;

use App\Models\Interaction;
use Illuminate\Database\Seeder;

class InteractionsTableSeeder extends Seeder
{
    public function run()
    {
        Interaction::create([
            'user_id' => 2,
            'interactable_type' => 'App\Models\Review',
            'interactable_id' => 1,
            'type' => 'like'
        ]);

        Interaction::create([
            'user_id' => 3,
            'interactable_type' => 'App\Models\Review',
            'interactable_id' => 1,
            'type' => 'comment',
            'content' => 'Mình cũng rất thích khách sạn này!'
        ]);

        Interaction::create([
            'user_id' => 4,
            'interactable_type' => 'App\Models\Itinerary',
            'interactable_id' => 1,
            'type' => 'share'
        ]);

        Interaction::create([
            'user_id' => 1,
            'interactable_type' => 'App\Models\Review',
            'interactable_id' => 3,
            'type' => 'like'
        ]);

        Interaction::create([
            'user_id' => 2,
            'interactable_type' => 'App\Models\Itinerary',
            'interactable_id' => 2,
            'type' => 'comment',
            'content' => 'Lịch trình rất hợp lý, cảm ơn chia sẻ!'
        ]);
    }
}