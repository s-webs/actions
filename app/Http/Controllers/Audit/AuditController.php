<?php

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use OwenIt\Auditing\Models\Audit;

/**
 * История изменений — [[Функциональные требования#4.11 Аудит и история изменений]].
 * По каждому изменению фактических полей/справочников/решений по этапам: кто, когда,
 * старое и новое значение (`owen-it/laravel-auditing`, task-019). Журнал доступа
 * (входы/экспорт) — за пределами этой задачи, см. Result в Obsidian.
 */
class AuditController extends Controller
{
    private const MODEL_LABELS = [
        'App\\Models\\Measure' => 'Мероприятие',
        'App\\Models\\MeasureStage' => 'Этап',
        'App\\Models\\StagePeriodUpdate' => 'Отчёт по этапу',
        'App\\Models\\MeasureCredential' => 'Учётные данные',
        'App\\Models\\Direction' => 'Направление',
        'App\\Models\\Responsible' => 'Ответственный',
        'App\\Models\\Unit' => 'Подразделение',
    ];

    public function index(Request $request): Response
    {
        $query = Audit::query()->with('user')->latest();

        $query->when($request->filled('model'), fn ($q) => $q->where('auditable_type', $request->string('model')));

        $audits = $query->paginate(30)->withQueryString();

        $audits->getCollection()->transform(fn (Audit $audit) => [
            'id' => $audit->id,
            'event' => $audit->event,
            'model' => self::MODEL_LABELS[$audit->auditable_type] ?? $audit->auditable_type,
            'auditable_id' => $audit->auditable_id,
            'old_values' => $audit->old_values,
            'new_values' => $audit->new_values,
            'user' => $audit->user instanceof User ? $audit->user->name : ($audit->user ? 'мероприятие (неименной вход)' : null),
            'created_at' => $audit->created_at->format('Y-m-d H:i'),
        ]);

        return Inertia::render('audit/index', [
            'audits' => $audits,
            'models' => self::MODEL_LABELS,
            'filters' => $request->only('model'),
        ]);
    }
}
