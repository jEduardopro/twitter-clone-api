<?php

namespace Database\Seeders;

use App\Models\Tweet;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TweetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Tweet::factory()->count(10)->create([
            "user_id" => rand(5, 20)
        ])->each(function($tweet) {
            $collectionName = "images";
            $randomImage = "image".rand(2,5).".jpg";
            $mediaIdString = Str::random();
            $filename = "{$mediaIdString}.jpg";
            $tweet->addMedia(storage_path("media-demo/{$randomImage}"))
                ->preservingOriginal()
                ->usingFileName($filename)
                ->toMediaCollection($collectionName);
        });
    }
}
