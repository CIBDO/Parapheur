<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CalendarIcsService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarFeedController extends Controller
{
    public function __construct(private readonly CalendarIcsService $ics) {}

    public function ical(Request $request)
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->subDays(7)->startOfDay();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->addDays(60)->endOfDay();

        return $this->ics->response(
            $this->ics->feedForUser($request->user(), $from, $to),
            'agenda-parapheur.ics'
        );
    }
}
