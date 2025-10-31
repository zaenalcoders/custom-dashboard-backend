<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class DashboardController extends Controller
{
    /**
     * index
     *
     * @return Laravel\Lumen\Http\ResponseFactory::json
     */
    public function index()
    {
        $data = auth()->user()->role->dashboard_access;
        $r = ['status' => Response::HTTP_OK, 'result' => $data];
        return response()->json($r, Response::HTTP_OK);
    }
}
