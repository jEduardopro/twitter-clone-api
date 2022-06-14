<?php

namespace Tests\Feature\App\Http\Controllers\Auth;

use App\Events\UserRegistered;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_user_can_register_with_email()
    {
        Event::fake();
        $payload = $this->userValidData(['phone' => null]);

        $this->assertDatabaseCount('user_activations', 0);
        $response = $this->postJson('api/auth/register', $payload);

        $response->assertSuccessful()
            ->assertExactJson([
                "message" => "begin verification",
                "description" => "signup_with_email",
                "email" => $payload["email"]
            ]);

        Event::assertDispatched(UserRegistered::class);

        $this->assertDatabaseHas('users', $payload);
        $this->assertDatabaseCount('user_activations', 1);
    }

    /** @test */
    public function a_user_can_register_with_phone()
    {
        Event::fake();

        $payload = $this->userValidData(['email' => null]);

        $this->assertDatabaseCount('user_activations', 0);
        $response = $this->postJson('api/auth/register', $payload);

        $response->assertSuccessful()
            ->assertExactJson([
                "message" => "begin verification",
                "description" => "signup_with_phone",
                "phone" => env('PHONE_NUMBER_VALIDATED_TEST')
            ]);

        Event::assertDispatched(UserRegistered::class);

        $this->assertDatabaseHas('users', [
            'email' => null,
            'phone' => env('PHONE_NUMBER_VALIDATED_TEST')
        ]);
        $this->assertDatabaseCount('user_activations', 1);
    }


    /** @test */
    public function the_name_is_required()
    {
        $this->postJson('api/auth/register', $this->userValidData(["name" => null]))
            ->assertJsonValidationErrorFor('name');
    }

    /** @test */
    public function the_email_must_be_a_valid_email_address()
    {
        $this->postJson('api/auth/register', $this->userValidData(["email" => "invalid_email_address"]))
            ->assertJsonValidationErrorFor('email');
    }

    /** @test */
    public function the_email_must_be_unique()
    {
        User::factory()->create(["email" => "test_email@gmail.com"]);
        $this->postJson('api/auth/register', $this->userValidData(["email" => "test_email@gmail.com"]))
            ->assertJsonValidationErrorFor('email');
    }

    /** @test */
    public function the_phone_must_be_phone_number_valid()
    {
        $this->postJson('api/auth/register', $this->userValidData(["phone" => '863863']))
            ->assertJsonValidationErrorFor('phone');
    }

    /** @test */
    public function the_phone_must_be_unique()
    {
        User::factory()->create($this->userValidData(["email" => null]));
        $this->postJson('api/auth/register', $this->userValidData(["email" => null]))
            ->assertJsonValidationErrorFor('phone');
    }

    /** @test */
    public function the_request_must_have_at_least_an_email_or_a_phone()
    {
        $this->postJson('api/auth/register', $this->userValidData(["phone" => null, "email" => null]))
            ->assertStatus(422);
    }
}