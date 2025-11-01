<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DataColumn;
use App\Models\DataSource;
use App\Services\CsvProcessorService;
use App\Services\TemporaryFileService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DataSourceController extends Controller
{

    /**
     * index
     *
     * @param  Request $request
     * @param  mixed $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $id = NULL)
    {

        $data = [];
        $items = DataSource::query();
        $items->with([
            'columns:data_source_id,name,type',
        ]);

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

        if (isset($request->in) && $request->in) {
            $in = json_decode($request->in);
            if (isset($in->id)) {
                $items->whereIn('id', $in->id);
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
                'updater:id,name',
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
     * @param  Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:128',
            'file' => 'required|string|ends_with:.csv',
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
            $csvValidator = new CsvProcessorService();
            $validateFile = $csvValidator->validateFile(public_path('uploads/' . $data->file));
            if (!isset($validateFile['valid'])) {
                throw new \Exception($validateFile[0]);
            }
            $rows = $csvValidator->countCsvRows(public_path('uploads/' . $data->file), false);
            $item = new DataSource();
            $item->name = $data->name;
            $item->file = $data->file;
            $item->total_rows = $rows;
            $item->save();
            (new TemporaryFileService())->useFile($data->file);

            $schemas = $csvValidator->extractCsvSchema(public_path('uploads/' . $data->file));

            foreach ($schemas as $schema) {
                $dataCol = new DataColumn();
                $dataCol->data_source_id = $item->id;
                $dataCol->name = $schema['name'];
                $dataCol->type = $schema['type'];
                $dataCol->save();
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
     * @param  Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|uuid|exists:data_sources,id',
            'name' => 'required|string|max:128',
            'file' => 'required|string|ends_with:.csv'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'wrong' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = (object) $validator->validated();
        $removeFile = null;

        DB::beginTransaction();
        try {
            $item = DataSource::find($data->id);
            $removeFile = ($item->file != $data->file) ? $item->file : null;
            $item->name = $data->name;
            $item->file = $data->file;
            $item->save();
            (new TemporaryFileService())->useFile($data->file);

            if ($removeFile) {
                $csvValidator = new CsvProcessorService();
                $validateFile = $csvValidator->validateFile(public_path('uploads/' . $data->file));
                if (!isset($validateFile['valid'])) {
                    throw new \Exception($validateFile[0]);
                }
                $rows = $csvValidator->countCsvRows(public_path('uploads/' . $data->file), false);
                $item->total_rows = $rows;
                $item->save();

                $item->columns()->delete();

                $schemas = $csvValidator->extractCsvSchema(public_path('uploads/' . $data->file));
                foreach ($schemas as $schema) {
                    $dataCol = new DataColumn();
                    $dataCol->data_source_id = $item->id;
                    $dataCol->name = $schema['name'];
                    $dataCol->type = $schema['type'];
                    $dataCol->save();
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if ($removeFile) {
            (new TemporaryFileService())->removeFile($removeFile);
        }

        $r = ['status' => Response::HTTP_OK, 'result' => 'ok'];
        return response()->json($r, Response::HTTP_OK);
    }

    /**
     * delete
     *
     * @param  Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request)
    {
        $ids = json_decode($request->getContent());
        foreach (DataSource::whereIn('id', $ids)->get() as $item) {
            (new TemporaryFileService())->removeFile($item->file);
            $item->delete();
        }
        $r = ['status' => Response::HTTP_OK, 'result' => 'ok'];
        return response()->json($r, Response::HTTP_OK);
    }
}
