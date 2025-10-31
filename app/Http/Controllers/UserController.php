<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\User;
use App\Models\UserRegion;
use App\Models\UserServiceType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * index
     *
     * @param  mixed $request
     * @param  mixed $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $id = NULL)
    {

        $data = [];
        $items = User::query();
        $items->select('users.*');
        $items->whereNot('users.id', '00000000-0000-0000-0000-000000000000');

        if (isset($request->pay_to) && $request->pay_to) {
            $items->whereHas('role', function ($q) {
                $q->where('is_pay_to', 1);
            });
        } elseif (isset($request->engineer) && $request->engineer) {
            $items->whereHas('role', function ($q) {
                $q->where('is_engineer', 1);
            });
        } elseif (isset($request->technician) && $request->technician) {
            $items->whereHas('role', function ($q) {
                $q->where('is_technician', 1);
            });
        } elseif (isset($request->document_control) && $request->document_control) {
            $items->whereHas('role', function ($q) {
                $q->where('is_document_control', 1);
            });
        } elseif (isset($request->po_owner) && $request->po_owner) {
            $items->whereHas('role', function ($q) {
                $q->where('is_po_owner', 1);
            });
        }

        if (isset($request->region_id) && $request->region_id) {
            $items->whereHas('regions', function ($q) use ($request) {
                $q->where('region_id', $request->region_id);
            });
        }

        if (isset($request->filter) && $request->filter) {
            $filter = json_decode($request->filter, true);
            foreach ($filter as $key => $value) {
                if (is_array($value)) {
                    $items->whereIn($key, $value);
                } else {
                    $items->where($key, $value);
                }
            }
        }

        if ($id == NULL) {
            if (isset($request->order) && $request->order) {
                $orderType = substr($request->order, 0, 1) === '-' ? 'DESC' : 'ASC';
                $orderColumn = ltrim($request->order, '-');

                $tableName = [
                    'role' => ['roles']
                ];

                $_join = explode('.', $orderColumn);
                $column = array_pop($_join);
                $path = implode('.', $_join);

                $joinedTables = [];

                if (array_key_exists($path, $tableName)) {
                    $tables = $tableName[$path];

                    foreach ($tables as $i => $table) {
                        $fromTable = $i === 0 ? 'users' : $tables[$i - 1];
                        $foreignKey = $_join[$i] ?? $table;

                        $joinSignature = "{$fromTable}->{$table}";
                        if (!in_array($joinSignature, $joinedTables)) {
                            $items->leftJoin($table, $fromTable . '.' . $foreignKey . '_id', '=', $table . '.id');
                            $joinedTables[] = $joinSignature;
                        }
                    }

                    $items->orderBy(end($tables) . '.' . $column, $orderType);
                } else {
                    $items->orderBy($orderColumn, $orderType);
                }
            } else {
                $items->orderBy('created_at', 'DESC');
            }
            if (isset($request->q) && $request->q) {
                $q = trim($request->q);
                $items->where(function ($query) use ($q) {
                    $query->orWhere('name', 'like', '%' . $q . '%')
                        ->orWhere('email', 'like', $q . '%')
                        ->orWhere('phone', 'like', '%' . $q . '%')
                        ->orWhereHas('role', function ($query) use ($q) {
                            $query->where('name', 'like', '%' . $q . '%');
                        })
                        ->orWhereHas('regions', function ($query) use ($q) {
                            $query->whereHas('region', function ($query) use ($q) {
                                $query->where('name', 'like', '%' . $q . '%');
                            });
                        })
                        ->orWhereHas('service_types', function ($query) use ($q) {
                            $query->whereHas('service_type', function ($query) use ($q) {
                                $query->where('name', 'like', '%' . $q . '%');
                            });
                        });
                });
            }
            if (isset($request->limit) && ((int) $request->limit) > 0) {
                $data = $items->paginate(((int) $request->limit))->toArray();
            } else {
                $data['data'] = $items->get();
                $data['total'] = count($data['data']);
            }
        } else {
            $items->with([
                'regions.region',
                'service_types.service_type',
                'role:id,name',
                'creator:id,name',
                'updater:id,name'
            ]);
            $data['data'] = $items->where('id', $id)->first();
            $data['total'] = 1;
        }
        $r = ['status' => Response::HTTP_OK, 'result' => $data];
        return response()->json($r, Response::HTTP_OK);
    }

    /**
     * create
     *
     * @param  mixed $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role_id' => 'required|uuid|exists:roles,id',
            'email' => 'required|email|max:64|unique:users,email',
            'phone' => 'required|string|max:64|unique:users,phone',
            'name' => 'required|string|max:128',
            'password' => 'required|string|min:6',
            'status' => 'required|in:0,1,-1',
            'regions' => 'nullable|array',
            'regions.*' => 'uuid|exists:regions,id',
            'service_types' => 'nullable|array',
            'service_types.*' => 'uuid|exists:service_types,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'wrong' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        DB::beginTransaction();
        try {
            $data = (object) $validator->validated();
            $item = new User();
            $item->role_id = $data->role_id;
            $item->name = $data->name;
            $item->email = $data->email;
            $item->phone = $data->phone;
            $item->password = Hash::make($data->password);
            $item->status = $data->status;
            $item->save();

            foreach (($data->regions ?? []) as $region) {
                $userRegion = new UserRegion();
                $userRegion->user_id = $item->id;
                $userRegion->region_id = $region;
                $userRegion->save();
            }

            foreach (($data->service_types ?? []) as $serviceType) {
                $userServiceType = new UserServiceType();
                $userServiceType->user_id = $item->id;
                $userServiceType->service_type_id = $serviceType;
                $userServiceType->save();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        $r = ['status' => Response::HTTP_CREATED, 'result' => 'ok'];
        return response()->json($r, Response::HTTP_CREATED);
    }

    /**
     * update
     *
     * @param  mixed $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|uuid|exists:users,id|not_in:' . auth()->user()->id . ',00000000-0000-0000-0000-000000000000',
            'role_id' => 'required|uuid|exists:roles,id',
            'email' => 'required|email|max:64|unique:users,email,' . $request->id . ',id',
            'phone' => 'required|string|max:18|unique:users,phone,' . $request->id . ',id',
            'name' => 'required|string|max:128',
            'password' => 'nullable|string|min:6',
            'status' => 'required|in:0,1,-1',
            'regions' => 'nullable|array',
            'regions.*' => 'uuid|exists:regions,id',
            'service_types' => 'nullable|array',
            'service_types.*' => 'uuid|exists:service_types,id'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'wrong' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $data = (object) $validator->validated();
        DB::beginTransaction();
        try {
            $item = User::find($data->id);
            $item->role_id = $data->role_id;
            $item->name = $data->name;
            $item->email = $data->email;
            $item->phone = $data->phone;
            if (isset($data->password) && $data->password) {
                $item->password = Hash::make($data->password);
            }
            $item->status = $data->status;
            $item->save();

            $item->regions()->whereNotIn('region_id', $data->regions)->delete();
            foreach (($data->regions ?? []) as $region) {
                $userRegion = UserRegion::where('region_id', $region ?? null)
                    ->where('user_id', $item->id)->firstOrNew();
                $userRegion->user_id = $item->id;
                $userRegion->region_id = $region;
                $userRegion->save();
            }

            $item->service_types()->whereNotIn('service_type_id', $data->service_types)->delete();
            foreach (($data->service_types ?? []) as $serviceType) {
                $userServiceType = UserServiceType::where('service_type_id', $serviceType ?? null)
                    ->where('user_id', $item->id)->firstOrNew();
                $userServiceType->user_id = $item->id;
                $userServiceType->service_type_id = $serviceType;
                $userServiceType->save();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        $r = ['status' => Response::HTTP_OK, 'result' => 'ok'];
        return response()->json($r, Response::HTTP_OK);
    }

    /**
     * setStatus
     *
     * @param  mixed $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function setStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|uuid|exists:users,id|not_in:' . auth()->user()->id . ',00000000-0000-0000-0000-000000000000',
            'status' => 'required|numeric:in:0,1'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'wrong' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = (object) $validator->validated();
        $item = User::find($data->id);
        $item->status = $data->status;
        $item->save();
        $r = ['status' => Response::HTTP_OK, 'result' => 'ok'];
        return response()->json($r, Response::HTTP_OK);
    }

    /**
     * delete
     *
     * @param  mixed $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request)
    {
        $ids = json_decode($request->getContent());
        foreach (User::whereIn('id', $ids)->whereNotIn('id', [auth()->user()->id, '00000000-0000-0000-0000-000000000000'])->get() as $item) {
            if ($item->profile_pic && File::exist(public_path('uploads/') . $item->profile_pic)) {
                File::delete(public_path('uploads/') . $item->profile_pic);
            }
            $item->delete();
        }
        $r = ['status' => Response::HTTP_OK, 'result' => 'ok'];
        return response()->json($r, Response::HTTP_OK);
    }
}
