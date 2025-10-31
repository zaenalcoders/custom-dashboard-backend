<?php

namespace App\Http\Controllers;


use App\Models\User;
use App\Services\FileUploaderService;
use App\Traits\ThrottlesLoginTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    use ThrottlesLoginTrait;

    /**
     * maxAttempts
     *
     * @var int
     */
    protected $maxAttempts = 6;

    /**
     * decayMinutes
     *
     * @var int
     */
    protected $decayMinutes = 3;

    /**
     * username
     *
     * @var string
     */
    protected $username = 'email';

    /**
     * __construct
     *
     * @return void
     */
    function __construct()
    {
        auth()->shouldUse('admin');
    }

    /**
     * login
     *
     * @param  Request $request
     * @return Laravel\Lumen\Http\ResponseFactory::json
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string',
            'password' => 'required|string'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'wrong' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            return $this->sendLockoutResponse($request);
        }

        $user = User::where($this->username, $request->email)->first();
        if (!$user) {
            $this->incrementLoginAttempts($request);
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'wrong' => [
                    'email' => ['Email is incorrect']
                ]
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($user->status != 1) {
            $this->incrementLoginAttempts($request);
            $status = $user->status == 0 ? 'is not active yet' : ($user->status == -1 ? 'is inactivated' : 'status is unknown');
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'wrong' => ['email' => ['Your account ' . $status]]
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (!Hash::check($request->password, $user->password)) {
            $this->incrementLoginAttempts($request);
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'wrong' => [
                    'password' => ['Password is incorrect'],
                ]
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $this->clearLoginAttempts($request);
        if (isset($request->is_refresh) && $request->is_refresh) {
            return $this->refresh($request);
        }
        $token = auth()->login($user);
        return $this->createNewToken((string)$token);
    }

    /**
     * forgotPassword
     *
     * @param  Request $request
     * @return Laravel\Lumen\Http\ResponseFactory::json
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'wrong' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $item = User::where('email', $request->email)->first();
        if (!$item) {
            goto response;
        }
        $item->reset_key = encrypt($request->email);
        $item->save();

        response:
        return response()->json([
            'status' => Response::HTTP_OK,
            'result' => 'success'
        ], Response::HTTP_OK);
    }

    /**
     * resetPassword
     *
     * @param  Request $request
     * @return Laravel\Lumen\Http\ResponseFactory::json
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|exists:users,reset_key',
            'password' => 'required|string|confirmed|min:6',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'wrong' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $item = User::where('reset_key', $request->token)->first();
        $decResetKey = decrypt($request->token);
        if ($decResetKey != $item->email) {
            throw new \Exception('Reset password link is invalid', 422);
        }
        $item->password = Hash::make($request->password);
        $item->reset_key = null;
        $item->save();

        return response()->json([
            'status' => Response::HTTP_OK,
            'result' => 'success'
        ], Response::HTTP_OK);
    }

    /**
     * updateProfilePic
     *
     * @param  Request $request
     * @return Laravel\Lumen\Http\ResponseFactory::json
     */
    public function updateProfilePic(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'file|mimes:jpg,jpeg,png,bmp,webp|max:2048|mimetypes:image/*'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $profile_pic = (new FileUploaderService(true))->save($request->file('image'));
        $user = auth()->user();
        $user->profil_pic = $profile_pic;
        $user->save();

        return response()->json([
            'status' => Response::HTTP_OK,
            'result' => $profile_pic
        ], Response::HTTP_OK);
    }

    /**
     * updateProfile
     *
     * @param  Request $request
     * @return Laravel\Lumen\Http\ResponseFactory::json
     */
    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        $data = json_decode($request->data, true);
        $validator = Validator::make($data, [
            'name' => 'required|string|max:128',
            'email' => 'required|string|unique:users,email,' . $user->id,
            'phone' => 'required|string|unique:users,phone,' . $user->id,
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'wrong' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $data = (object) $validator->validated();
        $user->name = $data->name;
        $user->email = $data->email;
        $user->phone = $data->phone;
        $user->save();

        return $this->profile();
    }

    /**
     * changePassword
     *
     * @param  Request $request
     * @return Laravel\Lumen\Http\ResponseFactory::json
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
            'new_password' => 'required|string|confirmed|min:6'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'wrong' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = auth()->user();
        $data = (object)$validator->validated();
        if (!Hash::check($data->password, $user->password)) {
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'wrong' => [
                    'password' => ['Password is incorrect']
                ]
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (Hash::check($data->new_password, $user->password)) {
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'wrong' => [
                    'new_password' => ['New password can\'t be same as the current password']
                ]
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $user->password = Hash::make($data->new_password);
        $user->save();

        return response()->json([
            'status' => Response::HTTP_OK,
            'result' => 'success'
        ], Response::HTTP_OK);
    }

    /**
     * fcm_token
     *
     * @return void
     */
    public function fcm_token(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'wrong' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $user = auth()->user();
        $user->fcm_token = $request->token;
        $user->save();
        $response = [
            'status' => Response::HTTP_OK,
            'message' => 'Ok'
        ];
        return response()->json($response, Response::HTTP_OK);
    }

    /**
     * logout
     *
     * @return void
     */
    public function logout()
    {
        auth()->logout();
        $response = [
            'status' => Response::HTTP_OK,
            'result' => 'Successfully logged out'
        ];
        return response()->json($response, Response::HTTP_OK);
    }

    /**
     * refresh
     *
     * @param  mixed $request
     * @return void
     */
    public function refresh(Request $request)
    {
        if (!Hash::check($request->password, auth()->user()->password)) {
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'wrong' => ['password_dialog' => ['Password salah']]
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $user = auth()->user();
        $user->last_login_at = Carbon::now();
        $user->timestamps = false;
        if (Hash::needsRehash($user->password)) {
            $user->password = Hash::make($request->password);
        }
        $user->save();
        $response = [
            'status' => Response::HTTP_OK,
            'result' => [
                'access_token' => auth()->refresh(),
                'token_type' => 'Bearer',
                'timeout' => ((int)config('jwt.timeout'))
            ]
        ];
        return response()->json($response, Response::HTTP_OK);
    }

    /**
     * profile
     *
     * @return Laravel\Lumen\Http\ResponseFactory::json
     */
    public function profile()
    {
        $user = auth()->user();
        $userData = [
            'name' => $user->name,
            'initials' => $user->initials,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_pic' => $user->profile_pic,
            'created_at' => $user->created_at,
            'role' => $user->role?->name ?? null
        ];
        $response = [
            'status' => Response::HTTP_OK,
            'result' => $userData,
        ];
        return response()->json($response, Response::HTTP_OK);
    }

    /**
     * createNewToken
     *
     * @param  mixed $token
     * @return Laravel\Lumen\Http\ResponseFactory::json
     */
    protected function createNewToken(string $token)
    {
        $user = auth()->user();
        $user->last_login_at = Carbon::now();
        $user->timestamps = false;
        $user->save();
        $userData = [
            'name' => $user->name,
            'initials' => $user->initials,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_pic' => $user->profile_pic,
            'created_at' => $user->created_at,
            'role' => $user->role?->name ?? null
        ];
        return response()->json([
            'status' => Response::HTTP_OK,
            'result' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'timeout' => ((int)config('jwt.timeout')),
                'user' => $userData
            ]
        ], Response::HTTP_OK);
    }
}
