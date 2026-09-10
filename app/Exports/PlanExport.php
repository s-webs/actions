<?php

namespace App\Exports;

use App\Models\Measure;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Экспорт плана в xlsx, совместимый со структурой исходного листа «План»
 * (см. `MeasureImportService::DIRECTION_MAP` и комментарий там же про реальные
 * заголовки файла) — [[Функциональные требования#4.9 Отчёты, экспорт и архив срезов]].
 * «Последнее обновление»/«Следующий шаг»/«Подтверждающий документ» — факты периода
 * (task-002), на `measures` не хранятся; берём лучшее доступное приближение из текущего
 * состояния, а не оставляем в исходном xlsx-формате несуществующие в модели поля.
 */
class PlanExport implements FromCollection, WithHeadings, WithMapping
{
    private const STATUS_LABELS = [
        'not_started' => 'Не начато',
        'in_progress' => 'В работе',
        'at_risk' => 'Есть риск',
        'overdue' => 'Просрочено',
        'done' => 'Выполнено',
    ];

    public function collection(): Collection
    {
        return Measure::with(['direction', 'responsible', 'stages', 'latestPeriodState'])->orderBy('number')->get();
    }

    public function headings(): array
    {
        return [
            '№', 'Направление', 'Мероприятие', 'Ответственный', 'Срок',
            'Промежуточный контроль', 'Где заслушивается', 'Уровень риска',
            'Статус', '%', 'Следующий шаг', 'Комментарии',
        ];
    }

    public function map($measure): array
    {
        $nextStage = $measure->stages->sortBy('order')->first(fn ($s) => $s->planned_date?->isFuture());

        return [
            $measure->number,
            $measure->direction?->name,
            $measure->title,
            $measure->responsible?->name,
            $measure->deadline?->format('d.m.Y'),
            $measure->interim_monitoring_text,
            $measure->reviewed_by,
            $measure->currentRiskLevel()?->value,
            self::STATUS_LABELS[$measure->currentStatus()->value],
            $measure->percent,
            $nextStage?->title,
            $measure->proctor_comment,
        ];
    }
}
