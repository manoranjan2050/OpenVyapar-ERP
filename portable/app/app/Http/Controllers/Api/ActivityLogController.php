<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $cid    = $request->user()->company_id;
        $search = $request->input('search', '');
        $event  = $request->input('event', '');
        $model  = $request->input('model', '');
        $from   = $request->input('from');
        $to     = $request->input('to');
        $perPage = (int) $request->input('per_page', 50);

        $q = Activity::with(['causer'])
            ->where(function ($q) use ($cid) {
                $q->whereJsonContains('properties->company_id', $cid)
                  ->orWhereHas('causer', fn($u) => $u->where('company_id', $cid));
            })
            ->when($search, fn($q) => $q->where(fn($q) => $q
                ->where('description', 'like', "%{$search}%")
                ->orWhere('subject_type', 'like', "%{$search}%")
            ))
            ->when($event, fn($q) => $q->where('event', $event))
            ->when($model, fn($q) => $q->where('subject_type', 'like', "%{$model}%"))
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('created_at', '<=', $to))
            ->orderByDesc('created_at');

        $results = $q->paginate($perPage);

        return response()->json([
            'data'  => $results->items(),
            'total' => $results->total(),
            'pages' => $results->lastPage(),
            'page'  => $results->currentPage(),
        ]);
    }

    public function show(int $id)
    {
        $log = Activity::with('causer')->findOrFail($id);
        return response()->json($log);
    }

    public function stats(Request $request)
    {
        $cid = $request->user()->company_id;

        $base = Activity::whereHas('causer', fn($u) => $u->where('company_id', $cid));

        return response()->json([
            'total'   => $base->count(),
            'today'   => (clone $base)->whereDate('created_at', today())->count(),
            'week'    => (clone $base)->whereBetween('created_at', [now()->startOfWeek(), now()])->count(),
            'by_event'=> (clone $base)->selectRaw('event, count(*) as cnt')->groupBy('event')->pluck('cnt', 'event'),
            'by_model'=> (clone $base)->selectRaw('SUBSTRING_INDEX(subject_type, chr(92), -1) as model, count(*) as cnt')
                ->groupBy('model')->orderByDesc('cnt')->limit(10)->pluck('cnt', 'model'),
        ]);
    }
}
