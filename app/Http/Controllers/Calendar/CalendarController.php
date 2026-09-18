<?php

namespace App\Http\Controllers\Calendar;

use App\Http\Controllers\Controller;
use App\Models\CalendarFocus;
use App\Models\Measure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Календарь контроля — готовая годовая повестка мониторинга,
 * [[Функциональные требования#4.4 Модуль «Календарь контроля»]]. Связь месяц →
 * мероприятия не задана отдельной сущностью (в [[Модель данных]] её нет) — подтягиваются
 * мероприятия, у которых в этот месяц попадает **дедлайн или плановая дата любого этапа**.
 */
class CalendarController extends Controller
{
    public function index(Request $request): Response
    {
        $measures = Measure::query()->visibleTo($request->user())->with(['latestPeriodState', 'stages'])->get();

        $months = CalendarFocus::orderBy('month')->get()->map(function (CalendarFocus $focus) use ($measures) {
            $linked = $measures->filter(function (Measure $m) use ($focus) {
                $inMonth = fn ($date) => $date && $date->isSameMonth($focus->month) && $date->isSameYear($focus->month);

                return $inMonth($m->deadline)
                    || $m->stages->contains(fn ($stage) => $inMonth($stage->planned_date));
            });

            return [
                'month' => $focus->month->format('Y-m'),
                'focus_text' => $focus->focus_text,
                'review_body' => $focus->review_body,
                'measures' => $linked->map(fn (Measure $m) => [
                    'id' => $m->id,
                    'number' => $m->number,
                    'title' => $m->title,
                    'status' => $m->currentStatus()->value,
                    'percent' => $m->percent,
                ])->values(),
            ];
        });

        return Inertia::render('calendar/index', ['months' => $months->values()]);
    }
}
