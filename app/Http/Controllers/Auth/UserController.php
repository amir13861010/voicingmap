<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ForgotMail;
use App\Models\ForgotPassword;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
class UserController extends Controller implements HasMiddleware
{
    public $code = null;


    public static function middleware(): array
    {
        return [
            new Middleware('auth', except: ['login', 'register', 'forgotPassword', 'getForgotPassword', 'setForgotPassword', 'googleRedirect', 'googleCallback']),
            //new Middleware('throttle:0,1,1', only: ['forgotPassword']),
        ];
    }

    /**
     * @OA\Post(
     *     path="/auth/register",
     *     tags={"Authentication"},
     *     summary="register",
     *     description="register",
     *     operationId="register",
     *     @OA\Response(
     *         response=200,
     *         description="Success Message",
     *         @OA\JsonContent(ref="#/components/schemas/UserModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="an 'unexpected' error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorModel"),
     *     ),
     *     @OA\RequestBody(
     *         description="tasks input",
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 description="name",
     *                 example="string"
     *             ),
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 description="email",
     *                 example="test@example.com"
     *             ),
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 description="password",
     *                 example="password"
     *             ),
     *             @OA\Property(
     *                 property="password_confirmation",
     *                 type="string",
     *                 description="password_confirmation",
     *                 example="password"
     *             )
     *
     *         )
     *     )
     * )
     *
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|confirmed|min:6',
        ]);
        try {
            $user = User::create($request->all());
            return $this->success($user);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' register error');
            return $this->error('you have error into register');
        }
    }

    /**
     * @OA\Get(
     *     path="/auth/verify-email",
     *     tags={"Authentication"},
     *     summary="verify email",
     *     description="verify email",
     *     @OA\Response(
     *         response=200,
     *         description="Success Message",
     *         @OA\JsonContent(ref="#/components/schemas/SuccessModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="an 'unexpected' error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorModel"),
     *     ),security={{"api_key": {}}}
     * )
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyEmail()
    {
        /** @var User $user */
        $user = Auth::user();
        if ($user->hasVerifiedEmail()) {
            return $this->success('Email already verified.');
        }
        $now = Carbon::now();
        if ($user->code_sent_at) {
            $codeSentAt = Carbon::parse($user->code_sent_at);
            $diffInMinutes = abs($now->diffInMinutes($codeSentAt));

            if ($diffInMinutes < 2) {
                return $this->error('Please wait before requesting a new code.');
            }
        }
        $this->code = random_int(1000, 9999);

        Mail::to($user->email)->send(new \App\Mail\VarificationCode($this->code));

        $user->verification_code = $this->code;
        $user->code_sent_at = $now;
        $user->save();

        Log::info('New code sent at: ' . $user->code_sent_at);

        return $this->success(["We have sent the verification code to $user->email"]);
    }

    /**
     * @OA\Post(
     *     path="/auth/email/verify",
     *     tags={"Authentication"},
     *     summary="Verify email address with OTP",
     *     description="Verify the user's email address using the OTP sent to the email.",
     *     operationId="verifyEmailAddress",
     *     @OA\RequestBody(
     *         description="OTP input",
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="otp",
     *                 type="string",
     *                 description="One Time Password sent to the user's email",
     *                 example="1234"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Email verified successfully",
     *         @OA\JsonContent(ref="#/components/schemas/SuccessModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid OTP or email verification failed",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorModel"),
     *     ),
     *     security={{"api_key": {}}}
     * )
     */
    public function verifyEmailAddress(Request $request)
    {
        $request->validate([
            'otp' => 'required|digits:4',
        ]);

        /** @var User $user */
        $user = Auth::user();
        $now = Carbon::now();

        if ($request->otp != $user->verification_code) {
            return $this->error("Incorrect code");
        }

        $codeSentAt = Carbon::parse($user->code_sent_at);
        $diffInMinutes = abs($now->diffInMinutes($codeSentAt));

        if ($diffInMinutes >= 2) {
            return $this->error("The verification code has expired.");
        }

        // Implement email verification logic here, such as marking the email as verified
        $user->markEmailAsVerified();
        $user->verification_code = null; // Remove the used code
        $user->save();

        return $this->success("Email verified successfully");
    }

    /**
     * @OA\Post(
     *     path="/auth/login",
     *     tags={"Authentication"},
     *     summary="login",
     *     description="login",
     *     operationId="login",
     *     @OA\Response(
     *         response=200,
     *         description="Success Message",
     *         @OA\JsonContent(ref="#/components/schemas/SuccessModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="an 'unexpected' error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorModel"),
     *     ),
     *     @OA\RequestBody(
     *         description="tasks input",
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 description="email",
     *                 example="test@example.com"
     *             ),
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 description="password",
     *                 default="null",
     *                 example="password",
     *             )
     *         )
     *     )
     * )
     *
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = request(['email', 'password']);
        if (!$token = auth()->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        return $this->respondWithToken($token);
    }

    /**
     * @OA\Get(
     *     path="/auth/me",
     *     tags={"Authentication"},
     *     summary="my info",
     *     description="my info",
     *     @OA\Response(
     *         response=200,
     *         description="Success Message",
     *         @OA\JsonContent(ref="#/components/schemas/UserModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="an 'unexpected' error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorModel"),
     *     ),security={{"api_key": {}}}
     * )
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return $this->success(auth()->user());
    }

    /**
     * @OA\Post(
     *     path="/auth/logout",
     *     tags={"Authentication"},
     *     summary="logout",
     *     description="logout",
     *     operationId="logout",
     *     @OA\Response(
     *         response=200,
     *         description="Success Message",
     *         @OA\JsonContent(ref="#/components/schemas/SuccessModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="an 'unexpected' error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorModel"),
     *     ),security={{"api_key": {}}}
     * )
     *
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();
        return $this->success(['message' => 'Successfully logged out']);
    }

    /**
     * @OA\Get(
     *     path="/auth/refresh",
     *     tags={"Authentication"},
     *     summary="refresh",
     *     description="refresh a token",
     *     operationId="refresh",
     *     @OA\Response(
     *         response=200,
     *         description="Success Message",
     *         @OA\JsonContent(ref="#/components/schemas/SuccessModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="an 'unexpected' error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorModel"),
     *     ),security={{"api_key": {}}}
     * )
     *
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(Auth::refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::factory()->getTTL() * 60
        ]);
    }

    /**
     * @OA\Post(
     *     path="/auth/change-password",
     *     tags={"Authentication"},
     *     summary="Change user password",
     *     description="Change user password",
     *     @OA\RequestBody(
     *         description="tasks input",
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="current_password",
     *                 type="string",
     *                 description="current password",
     *                 example="******"
     *             ),
     *             @OA\Property(
     *                 property="new_password",
     *                 type="string",
     *                 description="new password",
     *                 example="******",
     *             ),
     *             @OA\Property(
     *                 property="new_password_confirmation",
     *                 type="string",
     *                 description="confirmation your password",
     *                 example="******",
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success Message",
     *         @OA\JsonContent(ref="#/components/schemas/SuccessModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="an 'unexpected' error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorModel"),
     *     ),security={{"api_key": {}}}
     * )
     * change password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|confirmed',
        ]);

        /** @var User $user */
        $user = Auth::user();
        try {
            if (!Hash::check($request->current_password, $user->password)) {
                return $this->error('The current password is incorrect.');
            }
            $user->update(['password' => Hash::make($request->new_password)]);
            return $this->success(['message' => 'Password changed successfully']);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return $this->error('An error occurred while changing the password.');
        }
    }

    /**
     * @OA\Put(
     *     path="/auth/edit/profile",
     *     tags={"Authentication"},
     *     summary="Change user ifon",
     *     description="Change user ifon",
     *     @OA\RequestBody(
     *         description="tasks input",
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 description="name",
     *                 example="test"
     *             ),
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 description="email",
     *                 example="test@example.com",
     *             ),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success Message",
     *         @OA\JsonContent(ref="#/components/schemas/UserModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="an 'unexpected' error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorModel"),
     *     ),security={{"api_key": {}}}
     * )
     * profile
     */
    public function edit(Request $request)
    {
        $request->validate([
            'name' => 'max:255',
            'email' => 'email|unique:users',
        ]);
        try {
            /** @var User $user */
            $user = Auth::user();
            $user->update($request->only(['name','email']));
            return $this->success($user);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return $this->error('An error occurred while updating profile.');
        }
    }

    /**
     * @OA\Post(
     *     path="/auth/upload/avatar",
     *     tags={"Authentication"},
     *     summary="logout",
     *     description="logout",
     *       @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 allOf={
     *                     @OA\Schema(
     *                         @OA\Property(
     *                             description="Item",
     *                             property="file",
     *                             type="string", format="binary"
     *                         )
     *                     )
     *                 }
     *             )
     *         )
     *     ),

     *     @OA\Response(
     *         response=200,
     *         description="Success Message",
     *         @OA\JsonContent(ref="#/components/schemas/SuccessModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="an 'unexpected' error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorModel"),
     *     ),security={{"api_key": {}}}
     * )
     *
     * upload image
     */
    public function UploadAvtar(Request $request)
    {
        $request->validate([
            "file" => "file|max:4000|mimes:jpg,png,svg"
        ]);

        try {
            $filepath = $request->file("file")->store('avatars', 'public');

            /** @var User $user */
            $user = Auth::user();
            if (!is_null($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $user->update(["avatar" => $filepath]);

            return $this->success(['message' => 'Avatar uploaded successfully']);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return $this->error('An error occurred while uploading avatar.');
        }
    }

    /**
     * @OA\Get(
     *     path="/auth/google",
     *     tags={"Authentication"},
     *     summary="redirect to google",
     *     description="redirect to google",
     *     operationId="redirect to google",
     *     @OA\Response(
     *         response=200,
     *         description="Success Message",
     *         @OA\JsonContent(ref="#/components/schemas/SuccessModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="an 'unexpected' error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorModel"),
     *     )
     * )
     *
     * redirect to google.
     *
     * @return \Illuminate\Http\JsonResponse
     */

    public function googleRedirect()
    {
        try {
            return Socialite::driver('google')->redirect();
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return $this->error('An unexpected error occurred', status: 400);
        }
    }

    public function googleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $user = User::where('email', $googleUser->email)->first();

            if ($user) {
                auth()->login($user);
                return $this->success($user);
            }

            $newUser = User::create([
                'name' => $googleUser->name,
                'email' => $googleUser->email,
                'password' => Hash::make(str()->random(16)),
            ]);
            auth()->login($newUser);
            return $this->success($newUser);
        } catch (Exception $e) {
            Log::error('Google callback error: ' . $e->getMessage());
            return $this->error('An error occurred during authentication.');
        }
    }

    /**
    * @OA\Post(
    *     path="/auth/forgotPassword",
    *     tags={"Authentication"},
    *     summary="Forgot user password",
    *     description="Forgot user password",
    *     @OA\RequestBody(
    *         description="tasks input",
    *         required=true,
    *         @OA\JsonContent(
    *             @OA\Property(
    *                 property="email",
    *                 type="string",
    *                 description="email",
    *                 example="test@example.com"
    *             ),
    *         )
    *     ),
    *     @OA\Response(
    *         response=200,
    *         description="Success Message",
    *         @OA\JsonContent(ref="#/components/schemas/SuccessModel"),
    *     ),
    *     @OA\Response(
    *         response=400,
    *         description="an 'unexpected' error",
    *         @OA\JsonContent(ref="#/components/schemas/ErrorModel"),
    *     )
    * )
    * forgot password
    */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            $user = User::whereEmail($request->email)->first();
            if (!$user) {
                sleep(2);
                return $this->success(['message' => 'Password reset link sent to your email']);
            }
            ForgotPassword::whereEmail($user->email)->update(['status'=>1]);
            $token = str()->random(60);
            ForgotPassword::create(
                ['email' => $user->email, 'token' => $token, ]
            );
            Mail::to($user->email)->send(new ForgotMail($user, $token));
            return $this->success(['message' => 'Password reset link sent to your email']);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return $this->error('Something went wrong');
        }
    }

    public function getForgotPassword(string $token)
    {
        return view('mail.forgotForm', ['token' => $token]);
    }

    public function setForgotPassword(Request $request, string $token)
    {
        $request->validate([
            'password'     => 'required|string|min:7|confirmed',
        ]);

        try {
            $forgotPassword = ForgotPassword::whereToken($token)->whereStatus(0)->firstOrFail();
            if (!$forgotPassword) {
                return $this->error('invalid token');
            }

            $user = User::whereEmail($forgotPassword->email)->firstOrFail();

            $forgotPassword->status = 1;
            $forgotPassword->save();

            $user->update(['password' => bcrypt(request('password'))]);
            return $this->success($user);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return $this->error('Password change failed');
        }
    }
}
