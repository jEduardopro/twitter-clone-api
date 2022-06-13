<?php

namespace App\Http\Controllers\Auth;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterFormRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    public function register(RegisterFormRequest $request)
    {
        if (!$this->existsAleastEmailOrPhoneField()) {
            return response()->json([
                "message" => "Missing email address or phone number"
            ],422);
        }

        $user = new User();

        $user->generateUsername($request->name);
        // $user->encryptPassword($request->password);
        $user->name = $request->name;

        if ($request->filled('email')) {
            $user->email = $request->email;
        }
        if ($request->filled('phone')) {
            $user->phone = $request->phone;
        }

        $user->save();

        $user['token'] = Str::upper( Str::random(6) );

        UserRegistered::dispatch($user);

        DB::table('user_activations')->insert([ 'user_id' => $user->id, 'token' => $user['token'] ]);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone
            ]
        ]);
    }

    private function existsAleastEmailOrPhoneField()
    {
        $request = request();
        if (!$request->filled('email') && !$request->filled('phone')) {
            return false;
        }
        $email = $request->email;
        $phone = $request->phone;
        
        if ($email == "null" && $phone == "null") {
            return false;
        }

        if (empty($email) && empty($phone)) {
            return false;
        }

        return true;
    }
}