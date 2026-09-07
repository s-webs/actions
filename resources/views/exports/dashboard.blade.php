<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Кабинет проректора</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background: #f2f2f2; }
        .kpis { display: table; width: 100%; margin-top: 8px; }
        .kpi { display: table-cell; border: 1px solid #ccc; padding: 6px; text-align: center; width: 16.6%; }
        .kpi .value { font-size: 18px; font-weight: bold; }
        .kpi .label { font-size: 9px; color: #555; }
        h2 { font-size: 13px; margin-top: 16px; margin-bottom: 4px; }
    </style>
</head>
<body>
    <h1>Кабинет проректора — Action Plan ЮКМА 2026–2027</h1>
    <p class="muted">Данные актуальны на {{ $generatedAt }}</p>

    <div class="kpis">
        <div class="kpi"><div class="value">{{ $kpis['total'] }}</div><div class="label">Всего</div></div>
        <div class="kpi"><div class="value">{{ $kpis['done'] }}</div><div class="label">Выполнено</div></div>
        <div class="kpi"><div class="value">{{ $kpis['in_progress'] }}</div><div class="label">В работе</div></div>
        <div class="kpi"><div class="value">{{ $kpis['at_risk'] }}</div><div class="label">Есть риск</div></div>
        <div class="kpi"><div class="value">{{ $kpis['overdue'] }}</div><div class="label">Просрочено</div></div>
        <div class="kpi"><div class="value">{{ $kpis['avg_percent'] }}%</div><div class="label">Средний %</div></div>
    </div>

    <h2>Требует решения проректора ({{ count($needsDecision) }})</h2>
    <table>
        <thead>
            <tr><th>№</th><th>Мероприятие</th><th>Ответственный</th><th>Срок</th><th>%</th><th>Проблема</th></tr>
        </thead>
        <tbody>
            @forelse ($needsDecision as $m)
                <tr>
                    <td>{{ $m['number'] }}</td>
                    <td>{{ $m['title'] }}</td>
                    <td>{{ $m['responsible'] }}</td>
                    <td>{{ $m['deadline'] }}</td>
                    <td>{{ $m['percent'] }}%</td>
                    <td>{{ $m['problem'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Нет мероприятий, требующих решения.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>ТОП-5 рисков месяца</h2>
    <table>
        <thead>
            <tr><th>№</th><th>Мероприятие</th><th>Ответственный</th><th>Срок</th><th>%</th><th>Уровень риска</th></tr>
        </thead>
        <tbody>
            @foreach ($topRisks as $m)
                <tr>
                    <td>{{ $m['number'] }}</td>
                    <td>{{ $m['title'] }}</td>
                    <td>{{ $m['responsible'] }}</td>
                    <td>{{ $m['deadline'] }}</td>
                    <td>{{ $m['percent'] }}%</td>
                    <td>{{ $m['risk_level'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Свод по направлениям</h2>
    <table>
        <thead><tr><th>Направление</th><th>Мероприятий</th><th>Средний %</th></tr></thead>
        <tbody>
            @foreach ($directionSummary as $d)
                <tr><td>{{ $d['name'] }}</td><td>{{ $d['count'] }}</td><td>{{ $d['avg_percent'] }}%</td></tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
