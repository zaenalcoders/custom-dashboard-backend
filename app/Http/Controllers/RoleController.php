<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Role;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /**
     * index
     *
     * @param  mixed $request
     * @param  mixed $id
     * @return void
     */
    public function index(Request $request, $id = NULL)
    {

        $data = [];
        $items = Role::query();

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
                $items->orderBy($orderColumn, $orderType);
            } else {
                $items->orderBy('created_at', 'DESC');
            }
            if (isset($request->q) && $request->q) {
                $q = trim($request->q);
                $items->where(function ($query) use ($q) {
                    $query->where('name', 'like', '%' . $q . '%');
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
     * @return void
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:128',
            'access' => 'required|array',
            'access_all_data' => 'required|in:0,1',
            'dashboard' => 'nullable',
            'is_document_control' => 'required|in:0,1',
            'is_engineer' => 'required|in:0,1',
            'is_technician' => 'required|in:0,1',
            'is_pay_to' => 'required|in:0,1',
            'is_po_owner' => 'required|in:0,1',
            'status' => 'required|in:0,1',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'wrong' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = (object) $validator->validated();
        $item = new Role();

        $item->name = $data->name;
        $item->access = $data->access;
        $item->dashboard = $data->dashboard;
        $item->access_all_data = $data->access_all_data;
        $item->is_document_control = $data->is_document_control;
        $item->is_engineer = $data->is_engineer;
        $item->is_technician = $data->is_technician;
        $item->is_pay_to = $data->is_pay_to;
        $item->is_po_owner = $data->is_po_owner;
        $item->status = $data->status;
        $item->save();

        $r = ['status' => Response::HTTP_CREATED, 'result' => 'ok'];
        return response()->json($r, Response::HTTP_CREATED);
    }

    /**
     * update
     *
     * @param  mixed $request
     * @return void
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|uuid|exists:roles,id',
            'name' => 'required|string|max:128',
            'access' => 'required|array',
            'status' => 'required|in:0,1',
            'dashboard' => 'nullable',
            'access_all_data' => 'required|in:0,1',
            'is_document_control' => 'required|in:0,1',
            'is_engineer' => 'required|in:0,1',
            'is_technician' => 'required|in:0,1',
            'is_pay_to' => 'required|in:0,1',
            'is_po_owner' => 'required|in:0,1'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'wrong' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = (object) $validator->validated();
        $item = Role::find($data->id);

        if ($item->access[0] == '*') {
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'Can\'t edit default role'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $item->name = $data->name;
        $item->access = $data->access;
        $item->status = $data->status;
        $item->dashboard = $data->dashboard;
        $item->access_all_data = $data->access_all_data;
        $item->is_document_control = $data->is_document_control;
        $item->is_engineer = $data->is_engineer;
        $item->is_technician = $data->is_technician;
        $item->is_pay_to = $data->is_pay_to;
        $item->is_po_owner = $data->is_po_owner;
        $item->save();
        Cache::delete('userNavigations_' . $item->id);

        $r = ['status' => Response::HTTP_OK, 'result' => 'ok'];
        return response()->json($r, Response::HTTP_OK);
    }

    /**
     * setStatus
     *
     * @param  mixed $request
     * @return void
     */
    public function setStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|uuid|exists:roles,id',
            'status' => 'required|numeric:in:0,1'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'wrong' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = (object) $validator->validated();
        $item = Role::find($data->id);

        if ($item->access[0] == '*') {
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'Can\'t edit default role'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $item->status = $data->status;
        $item->save();
        Cache::delete('userNavigations_' . $item->id);

        $r = ['status' => Response::HTTP_OK, 'result' => 'ok'];
        return response()->json($r, Response::HTTP_OK);
    }

    /**
     * delete
     *
     * @param  mixed $request
     * @return void
     */
    public function delete(Request $request)
    {
        $ids = json_decode($request->getContent());
        foreach (Role::whereIn('id', $ids)->get() as $item) {
            if ($item->access[0] == '*') {
                continue;
            }
            $item->delete();
        }
        $r = ['status' => Response::HTTP_OK, 'result' => 'ok'];
        return response()->json($r, Response::HTTP_OK);
    }
}
