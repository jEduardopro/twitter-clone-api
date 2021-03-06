<?php

namespace Tests\Feature\App\Http\Controllers\Retweets;

use App\Events\TweetRetweeted;
use App\Events\UndoRetweet;
use App\Models\Tweet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use Tests\TestCase;

class RetweetsControllerTest extends TestCase
{

	use RefreshDatabase;

	/** @test */
	public function an_authenticated_user_can_retweet_a_tweet()
	{
        Event::fake([TweetRetweeted::class]);

        Broadcast::shouldReceive('socket')->andReturn('socket-id');

		$user = User::factory()->activated()->create();
		$user2 = User::factory()->activated()->create();

		$tweet = Tweet::factory()->create(["user_id" => $user2->id]);

		Passport::actingAs($user);

		$response = $this->postJson("api/retweets", ["tweet_id" => $tweet->uuid]);

		$response->assertSuccessful();

		$this->assertEquals("retweet created successfully", $response->json("message"));

		$this->assertDatabaseHas("retweets", [
			"user_id" => $user->id,
			"tweet_id" => $tweet->id
		]);

        Event::assertDispatched(TweetRetweeted::class, function($event) use ($tweet) {
            $this->assertTrue(!is_null($event->tweetRetweeted));
            $this->assertTrue(!is_null($event->retweetOwner));

            $this->assertTrue($event->tweetRetweeted->is($tweet));

            $this->assertDontBroadcastToCurrentUser($event);
            $this->assertEventChannelType('public', $event);
            $this->assertEventChannelName("tweets.{$tweet->uuid}.retweets", $event);

            return true;
        });
	}


	/** @test */
	public function an_authenticated_user_can_undo_a_retweet_of_a_tweet()
	{
        Event::fake([UndoRetweet::class]);

        Broadcast::shouldReceive('socket')->andReturn('socket-id');

		$user = User::factory()->activated()->create();
		$user2 = User::factory()->activated()->create();

		$tweet = Tweet::factory()->create(["user_id" => $user2->id]);

		$user->retweet($tweet->id);

		Passport::actingAs($user);

		$response = $this->deleteJson("api/retweets/{$tweet->uuid}");

		$response->assertSuccessful();

		$this->assertEquals("retweet deleted successfully", $response->json("message"));

		$this->assertDatabaseMissing("retweets", [
			"user_id" => $user->id,
			"tweet_id" => $tweet->id
		]);

        Event::assertDispatched(UndoRetweet::class, function($event) use ($tweet){
            $this->assertTrue(!is_null($event->tweet));

            $this->assertTrue($event->tweet->is($tweet));

            $this->assertDontBroadcastToCurrentUser($event);
            $this->assertEventChannelType('public', $event);
            $this->assertEventChannelName("tweets.{$tweet->uuid}.retweets", $event);

            return true;
        });
	}

    /** @test */
    public function an_authenticated_user_can_not_undo_retweets_that_are_not_yours()
    {
        $user = User::factory()->activated()->create();
        $user2 = User::factory()->activated()->create();

        $tweet = Tweet::factory()->create(["user_id" => $user->id]);

        $user2->retweet($tweet->id);

        Passport::actingAs($user);

        $response = $this->deleteJson("api/retweets/{$tweet->uuid}");

        $response->assertStatus(403);

        $this->assertEquals("you do not have permission to perform this action", $response->json("message"));
    }

	/** @test */
	public function the_retweet_process_must_fail_if_no_tweet_found()
	{
		$user = User::factory()->activated()->create();

		Passport::actingAs($user);

		$response = $this->postJson("api/retweets", ["tweet_id" => "invalid-tweet-id"]);

		$response->assertStatus(404);

		$this->assertEquals("the tweet you want to retweet does not exist", $response->json("message"));
	}


	/** @test */
	public function the_undo_retweet_process_must_fail_if_no_tweet_found()
	{
		$user = User::factory()->activated()->create();

		Passport::actingAs($user);

		$response = $this->deleteJson("api/retweets/invalid-tweet-id");

		$response->assertStatus(404);

		$this->assertEquals("the retweet you want to undo does not exist", $response->json("message"));
	}

	/** @test */
	public function the_tweet_id_is_required_to_create_a_retweet()
	{
		$user = User::factory()->activated()->create();

		Passport::actingAs($user);

		$this->postJson("api/retweets", ["tweet_id" => null])
			->assertJsonValidationErrorFor("tweet_id");
	}
}
