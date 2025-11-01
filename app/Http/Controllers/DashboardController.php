<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Dashboard;
use App\Services\CsvProcessorService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DashboardController extends Controller
{

    /**
     * getList
     *
     * @param  Request $request
     * @param  mixed $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getList(Request $request, $id = NULL)
    {

        $data = [];
        $items = Dashboard::query();
        $items->with([
            'creator:id,name',
            'updater:id,name',
            'source',
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
            $data['data'] = $items->where('id', $id)->first();
            $data['total'] = 1;
        }
        $r = ['status' => Response::HTTP_OK, 'result' => $data];
        return response()->json($r, Response::HTTP_OK);
    }

    /**
     * getChart
     *
     * @param  mixed $request
     * @param  mixed $id
     * @return void
     */
    public function getChart($id)
    {
        $item = Dashboard::where('id', $id)
            ->select([
                'id',
                'data_source_id',
                'chart_type',
                'name',
                'config'
            ])
            ->with(['source'])
            ->firstOrFail();

        $csvPath = public_path('uploads/' . $item->source->file);
        $cacheKey = "chart_{$id}_" . md5($csvPath . filemtime($csvPath));

        if (Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }

        $csvParser = new CsvProcessorService();
        $records = $csvParser->getData($csvPath);
        $data = [
            'item' => $item
        ];
        if ($item->chart_type == 'table') {
            $data['records'] = $records;
        } else {
            $data['chart'] = $this->buildChartData($item, $records, $item->config);
        }
        return response()->json([
            'status' => Response::HTTP_OK,
            'result' => $data

        ], Response::HTTP_OK);
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
            'data_source_id' => 'required|uuid|exists:data_sources,id',
            'name' => 'required|string|max:128',
            'chart_type' => 'required|string',
            'config' => 'nullable|array'
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

            $item = new Dashboard();
            $item->data_source_id = $data->data_source_id;
            $item->chart_type = $data->chart_type;
            $item->name = $data->name;
            $item->config = $data->config;
            $item->save();

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
            'id' => 'required|uuid|exists:dashboards,id',
            'data_source_id' => 'required|uuid|exists:data_sources,id',
            'name' => 'required|string|max:128',
            'config' => 'nullable|array'
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
            $item = Dashboard::find($data->id);
            $item->data_source_id = $data->data_source_id;
            $item->name = $data->name;
            $item->config = $data->config;
            $item->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
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
        Dashboard::whereIn('id', $ids)->delete();
        $r = ['status' => Response::HTTP_OK, 'result' => 'ok'];
        return response()->json($r, Response::HTTP_OK);
    }

    /**
     * buildChartData
     *
     * @param  mixed $dashboard
     * @param  mixed $records
     * @param  mixed $config
     * @return array{error: string, type: mixed, datasets: array, labels: array}
     */
    private function buildChartData($dashboard, $records, $config)
    {
        $type = strtolower($dashboard->chart_type);

        switch ($type) {
            case 'pie':
            case 'doughnut':
            case 'polararea':
                return $this->buildCategoryChart($dashboard, $records, $config);

            case 'bar':
            case 'line':
            case 'radar':
                return $this->buildXYChart($dashboard, $records, $config);

            case 'scatter':
            case 'bubble':
                return $this->buildScatterChart($dashboard, $records, $config);

            default:
                return ['error' => "Unsupported chart type: $type"];
        }
    }

    /**
     * buildCategoryChart
     *
     * @param  mixed $dashboard
     * @param  mixed $records
     * @param  mixed $config
     * @return array{type: mixed, labels: array, datasets: array{label: mixed, data: float[], backgroundColor: mixed}[]}
     */
    private function buildCategoryChart($dashboard, $records, $config)
    {
        $labelKey = $config['label'] ?? null;
        $valueKey = $config['value'] ?? null;
        $labels = [];
        $values = [];

        foreach ($records as $row) {
            $labels[] = $row[$labelKey] ?? '';
            $values[] = (float) ($row[$valueKey] ?? 0);
        }

        return [
            'type' => $dashboard->chart_type,
            'labels' => $labels,
            'datasets' => [[
                'label' => $dashboard->title,
                'data' => $values,
                'backgroundColor' => $config['style']['colors'] ?? $this->generateColors(count($labels))
            ]]
        ];
    }

    /**
     * buildXYChart
     *
     * @param  mixed $dashboard
     * @param  mixed $records
     * @param  mixed $config
     * @return array{type: mixed, labels: array, datasets: array}
     */
    private function buildXYChart($dashboard, $records, $config)
    {
        $xKey = $config['x'] ?? null;
        $yKey = $config['y'] ?? null;
        $groupBy = $config['group_by'] ?? null;
        $datasets = [];
        $labels = [];

        if ($groupBy) {
            $grouped = [];
            foreach ($records as $row) {
                $group = $row[$groupBy] ?? 'Unknown';
                $x = $row[$xKey] ?? '';
                $y = (float) ($row[$yKey] ?? 0);
                $grouped[$group][$x] = $y;
            }

            $labels = collect($grouped)->flatMap(fn($g) => array_keys($g))->unique()->values()->toArray();
            $colors = $this->generateColors(count($grouped));
            $i = 0;

            foreach ($grouped as $group => $data) {
                $datasets[] = [
                    'label' => $group,
                    'data' => array_map(fn($label) => $data[$label] ?? 0, $labels),
                    'backgroundColor' => $colors[$i],
                    'borderColor' => $colors[$i],
                    'fill' => $dashboard->chart_type === 'line' ? false : true,
                ];
                $i++;
            }
        } else {
            foreach ($records as $row) {
                $labels[] = $row[$xKey] ?? '';
                $datasets[0]['data'][] = (float) ($row[$yKey] ?? 0);
            }
            $datasets[0]['label'] = $dashboard->title;
            $datasets[0]['backgroundColor'] = $config['style']['colors'][0] ?? '#4e73df';
            $datasets[0]['borderColor'] = $config['style']['colors'][0] ?? '#4e73df';
        }

        return [
            'type' => $dashboard->chart_type,
            'labels' => $labels,
            'datasets' => $datasets
        ];
    }

    /**
     * buildScatterChart
     *
     * @param  mixed $dashboard
     * @param  mixed $records
     * @param  mixed $config
     * @return array{type: mixed, datasets: array{label: mixed, data: array{x: float, y: float, r: float|int}[], backgroundColor: mixed}[]}
     */
    private function buildScatterChart($dashboard, $records, $config)
    {
        $datasets = [[
            'label' => $dashboard->title,
            'data' => []
        ]];

        foreach ($records as $row) {
            $datasets[0]['data'][] = [
                'x' => (float) ($row[$config['x']] ?? 0),
                'y' => (float) ($row[$config['y']] ?? 0),
                'r' => isset($config['r']) ? (float) ($row[$config['r']] ?? 5) : 5
            ];
        }

        $datasets[0]['backgroundColor'] = $config['style']['colors'][0] ?? '#36A2EB';

        return [
            'type' => $dashboard->chart_type,
            'datasets' => $datasets
        ];
    }

    /**
     * generateColors
     *
     * @param  mixed $count
     * @return array
     */
    private function generateColors($count)
    {
        $baseColors = [
            '#4e73df',
            '#1cc88a',
            '#36b9cc',
            '#f6c23e',
            '#e74a3b',
            '#858796',
            '#5a5c69',
            '#2e59d9',
            '#17a673',
            '#2c9faf'
        ];
        return array_slice(array_merge($baseColors, $baseColors), 0, $count);
    }
}
