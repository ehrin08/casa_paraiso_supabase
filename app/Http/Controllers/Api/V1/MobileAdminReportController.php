<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Admin\ReportController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileAdminReportController extends ReportController
{
    public function index(Request $request): JsonResponse
    {
        $this->validateFilters($request);
        $type = $this->type($request);
        $records = $this->query($type, $request)->paginate((int) config('casa.pagination.per_page', 15))->withQueryString();

        return response()->json([
            'type' => $type,
            'types' => self::TYPES,
            'columns' => $this->csvHeader($type),
            'data' => $records->getCollection()->map(fn ($record) => $this->csvRow($type, $record))->values(),
            'summary' => [...$this->summary(), 'revenue' => number_format((float) $this->summary()['revenue'], 2, '.', '')],
            'meta' => ['current_page' => $records->currentPage(), 'last_page' => $records->lastPage(), 'per_page' => $records->perPage(), 'total' => $records->total(), 'from' => $records->firstItem(), 'to' => $records->lastItem()],
        ])->header('Cache-Control', 'no-store');
    }
}
