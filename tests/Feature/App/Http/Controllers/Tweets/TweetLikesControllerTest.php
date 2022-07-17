<?php

namespace Tests\Feature\App\Http\Controllers\Tweets;

use App\Models\Like;
use App\Models\Tweet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use App\Events\ModelLiked;
use App\Events\ModelUnliked;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Broadcast;
use Laravel\Passport\Passport;
use Tests\TestCase;

class TweetLikesControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function an_authenticated_user_can_like_tweets()
    {
        Event::fake([ModelLiked::class]);

        Broadcast::shouldReceive('socket')->andReturn('socket-id');

        $user = User::factory()->activated()->create();
        $user2 = User::factory()->activated()->create();
        Passport::actingAs($user);

        $tweet = Tweet::factory()->create(["user_id" => $user2->id]);

        $response = $this->postJson("api/tweets/{$tweet->uuid}/likes");

        $this->assertEquals("like tweet done", $response->json("message"));

        $this->assertDatabaseCount("likes", 1);
        $this->assertDatabaseHas("likes", [
            "user_id" => $user->id
        ]);

        Event::assertDispatched(ModelLiked::class, function ($event) use ($tweet, $user) {
            $this->assertDontBroadcastToCurrentUser($event);

            $this->assertInstanceOf(get_class($tweet), $event->model);
            $this->assertTrue($event->likeSender->is($user));

            $this->assertEventChannelType('public', $event);
            $this->assertEventChannelName("tweets.{$tweet->uuid}.likes", $event);

            return true;
        });
    }

    /** @test */
    public function a_tweet_can_only_be_liked_by_a_user_once()
    {
        $user = User::factory()->activated()->create();
        Passport::actingAs($user);

        $tweet = Tweet::factory()->create();

        Like::factory()->create([
            "user_id" => $user->id,
            "likeable_id" => $tweet->id,
            "likeable_type" => Tweet::class
        ]);

        $response = $this->postJson("api/tweets/{$tweet->uuid}/likes");

        $this->assertEquals("you already liked this tweet", $response->json("message"));
    }

    /** @test */
    public function an_authenticated_user_can_unlike_tweets()
    {
        Event::fake([ModelUnliked::class]);

        Broadcast::shouldReceive('socket')->andReturn('socket-id');

        $user = User::factory()->activated()->create();
        $user2 = User::factory()->activated()->create();
        Passport::actingAs($user);

        $tweet = Tweet::factory()->create(["user_id" => $user2->id]);

        Like::factory()->create([
            "user_id" => $user->id,
            "likeable_id" => $tweet->id,
            "likeable_type" => Tweet::class
        ]);

        $response = $this->deleteJson("api/tweets/{$tweet->uuid}/likes");

        $this->assertEquals("unlike tweet done", $response->json("message"));

        $this->assertDatabaseCount("likes", 0);
        $this->assertDatabaseMissing("likes", [
            "user_id" => $user->id
        ]);

        Event::assertDispatched(ModelUnliked::class, function ($event) use ($tweet) {
            $this->assertInstanceOf(ShouldBroadcast::class, $event);
            $this->assertEquals(
                'socket-id', // Generated by Broadcast::shouldReceive('socket')->andReturn('socket-id')
                $event->socket,
                'The event ' . get_class($event) . ' must call the method "dontBroadcastToCurrentUser" in the constructor.'
            );
            $this->assertInstanceOf(get_class($tweet), $event->model);
            $this->assertInstanceOf(Channel::class, $event->broadcastOn());
            $this->assertEquals(Channel::class, get_class($event->broadcastOn()));
            $this->assertEquals("tweets.{$tweet->uuid}.likes", $event->broadcastOn()->name);
            return true;
        });
    }

    /** @test */
    public function an_authenticated_user_can_not_unlike_tweets_that_are_not_yours()
    {
        $user = User::factory()->activated()->create();
        $user2 = User::factory()->activated()->create();
        Passport::actingAs($user);

        $tweet = Tweet::factory()->create();

        Like::factory()->create([
            "user_id" => $user2->id,
            "likeable_id" => $tweet->id,
            "likeable_type" => Tweet::class
        ]);

        $response = $this->deleteJson("api/tweets/{$tweet->uuid}/likes");

        $response->assertStatus(403);

        $this->assertEquals("you do not have permission to perform this action", $response->json("message"));
    }

    /** @test */
    public function the_like_process_must_fail_if_no_tweet_found()
    {
        $user = User::factory()->activated()->create();
        Passport::actingAs($user);
        $response = $this->postJson("api/tweets/invalid-uuid/likes")->assertStatus(404);

        $this->assertEquals("the tweet does not exist", $response->json("message"));
    }


    /** @test */
    public function the_unlike_process_must_fail_if_no_tweet_found()
    {
        $user = User::factory()->activated()->create();
        Passport::actingAs($user);
        $response = $this->deleteJson("api/tweets/invalid-uuid/likes")->assertStatus(404);

        $this->assertEquals("the tweet does not exist", $response->json("message"));
    }
}
