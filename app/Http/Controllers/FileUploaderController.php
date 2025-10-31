<?php

namespace App\Http\Controllers;

use App\Models\TemporaryFile;
use App\Services\FileUploaderService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FileUploaderController extends Controller
{
    /**
     * create
     *
     * @param  Request $request
     * @return void
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:40960',
            'mime_type' => 'required|string|min:1',
            'old_file' => 'nullable|string'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'wrong' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $mimes = $request->file('file')->getMimeType();
        if (!in_array($mimes, explode(',', $request->mime_type))) {
            return response()->json([
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'file type not allowed, only ' . $request->mime_type
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        DB::beginTransaction();
        try {
            $upload = (new FileUploaderService())->save($request->file('file'));
            $item = new TemporaryFile();
            $item->file_name = $upload;
            $item->mime_type = $request->mime_type;
            $item->save();

            if ($request->old_file) {
                TemporaryFile::where('file_name', $request->old_file)->delete();
                if (file_exists(public_path('uploads/' . $request->old_file))) {
                    unlink(public_path('uploads/' . $request->old_file));
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        $r = ['status' => Response::HTTP_CREATED, 'result' => $upload];
        return response()->json($r, Response::HTTP_CREATED);
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
        foreach (TemporaryFile::whereIn('file_name', $ids)->get() as $item) {
            if (file_exists(public_path('uploads/' . $item->file_name))) {
                unlink(public_path('uploads/' . $item->file_name));
            }
            $item->delete();
        }
        $r = ['status' => Response::HTTP_OK, 'result' => 'ok'];
        return response()->json($r, Response::HTTP_OK);
    }
}
