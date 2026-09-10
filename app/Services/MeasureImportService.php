<?php

namespace App\Services;

use App\Enums\EvidenceType;
use App\Enums\RiskLevel;
use App\Models\Direction;
use App\Models\Evidence;
use App\Models\Measure;
use App\Models\Responsible;
use Carbon\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Разовый (и повторный, только координатор) импорт листа «План» —
 * [[Функциональные требования#4.12 Импорт из Excel]]. Читает файл напрямую через
 * PhpSpreadsheet (а не через maatwebsite/excel WithHeadingRow) — заголовки листа
 * кириллические, а `Str::slug()`, которым maatwebsite нормализует заголовки,
 * транслитерирует их в малопредсказуемые латинские ключи; при известном фиксированном
 * порядке столбцов позиционное чтение надёжнее.
 *
 * Импортируются только структурные поля мероприятия (справочные), не факты периода —
 * `%`/статус/следующий шаг/последнее обновление из шаблона не переносятся: `%` появляется
 * только через утверждение проректором ([[Бизнес-правила#Правило 2а]]), а статус — это
 * факт конкретного отчётного периода ([[Модель данных]]: `status` живёт в
 * `measure_period_states`, не в `measures`), которого на момент разового импорта ещё нет.
 */
class MeasureImportService
{
    private const SHEET_NAME = 'План';

    /** Короткие метки направлений из файла → № направления в [[Справочники#Направления плана]]. */
    private const DIRECTION_MAP = [
        'Управление' => 1,
        'ОП' => 2,
        'Оценивание' => 3,
        'Трехъязычие' => 4,
        'Независимая оценка' => 5,
        'НЭ выпускников' => 6,
        'НЭ интернов' => 7,
        'ВОП' => 8,
        'OSCE' => 9,
        'Академическая честность' => 10,
        'Поддержка студентов' => 11,
        'ИИ' => 12,
        'Цифровизация' => 13,
        'Удовлетворенность' => 14,
        'Траектория выпускников' => 15,
        'Прием' => 16,
        'Иностранные студенты' => 17,
        'Англоязычное обучение' => 17,
        'Совместные ОП' => 18,
        'Олимпиады и конкурсы' => 19,
        'ЦУР' => 20,
        'Библиотека' => 21,
        'ППС' => 22,
        'Качество' => 23,
        'Данные' => 24,
        'Кафедры' => 25,
        'Мониторинг' => 26,
        'Итоги года' => 28,
    ];

    /**
     * Канонические полные названия — [[Справочники#Направления плана]]. Используются при
     * доразрешении направления «на лету» (если импорт запущен раньше `DirectionSeeder`),
     * чтобы не заводить направление под краткой меткой файла вместо официального
     * названия.
     */
    private const DIRECTION_NAMES = [
        1 => 'Организационное управление академическим блоком',
        2 => 'Образовательные программы (ОП)',
        3 => 'Компетентностно-ориентированное обучение и оценивание',
        4 => 'Трёхъязычное обучение',
        5 => 'Промежуточная независимая оценка 3 курса',
        6 => 'Независимая экзаменация выпускников бакалавриата',
        7 => 'Независимая экзаменация интернатуры',
        8 => 'ВОП и интернатура: ускоренная трансформация, ПМСП, оценивание, наставничество',
        9 => 'OSCE и практические навыки',
        10 => 'Академическая честность',
        11 => 'Академическая честность и поддержка студентов',
        12 => 'Искусственный интеллект (ИИ)',
        13 => 'Цифровизация и качество данных',
        14 => 'Удовлетворённость обучающихся и работодателей',
        15 => 'Переход выпускников на следующий уровень образования',
        16 => 'Приём и работа с абитуриентами',
        17 => 'Иностранные и англоязычные обучающиеся',
        18 => 'Совместные образовательные программы',
        19 => 'Олимпиады, конференции, конкурсы',
        20 => 'ЦУР и экологическая ответственность',
        21 => 'Библиотека и учебные ресурсы',
        22 => 'Компетенции ППС',
        23 => 'Внутреннее обеспечение качества',
        24 => 'Качество академических данных',
        25 => 'Работа кафедр',
        26 => 'Мониторинг и управленческая отчётность',
        27 => 'Оценивание (распределение оценок, F, апелляции)',
        28 => 'Итоги года и планирование 2027–2028',
    ];

    private const RISK_MAP = [
        'Высокий' => RiskLevel::High,
        'Средний' => RiskLevel::Medium,
        'Низкий' => RiskLevel::Low,
    ];

    /** @var list<string> */
    private array $warnings = [];

    /** @var list<array{measure_number: int, login: string, password: string}> */
    private array $credentials = [];

    public function import(string $filePath): MeasureImportReport
    {
        $this->warnings = [];
        $this->credentials = [];

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheetByName(self::SHEET_NAME);

        if (! $sheet) {
            $this->warnings[] = sprintf('Лист «%s» не найден в файле — импорт остановлен.', self::SHEET_NAME);

            return new MeasureImportReport(0, 0, $this->warnings, []);
        }

        $accepted = 0;
        $updated = 0;
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        for ($row = 2; $row <= $highestRow; $row++) {
            $values = $sheet->rangeToArray("A{$row}:{$highestColumn}{$row}", null, true, false)[0];

            [$number, $directionLabel, $title, $responsibleName, $deadlineRaw, $interimText,
                $reviewedBy, $riskLevelRaw, $controlDateRaw, , , , , $evidenceRaw, $comment] = array_pad($values, 15, null);

            if ($number === null || $title === null) {
                continue;
            }

            $number = (int) $number;
            $title = trim((string) $title);

            $direction = $this->resolveDirection((string) $directionLabel, $title, $number);
            $responsible = $this->resolveResponsible($responsibleName);
            $riskLevel = $this->resolveRiskLevel($riskLevelRaw, $number);

            $deadline = $this->parseDate($deadlineRaw);
            if ($deadlineRaw && ! $deadline) {
                $this->warnings[] = "Мероприятие №{$number}: не удалось разобрать срок «{$deadlineRaw}».";
            }

            if ($controlDateRaw !== null && trim((string) $controlDateRaw) !== '') {
                $this->warnings[] = "Мероприятие №{$number}: колонка «Контрольная дата» больше не используется и проигнорирована.";
            }

            $measure = Measure::updateOrCreate(
                ['number' => $number],
                [
                    'direction_id' => $direction->id,
                    'title' => $title,
                    'responsible_id' => $responsible?->id,
                    'deadline' => $deadline,
                    'interim_monitoring_text' => $interimText !== null ? trim((string) $interimText) : null,
                    'reviewed_by' => $reviewedBy !== null ? trim((string) $reviewedBy) : null,
                    'risk_level' => $riskLevel,
                    'proctor_comment' => $comment !== null && trim((string) $comment) !== '' ? trim((string) $comment) : null,
                ],
            );

            $measure->wasRecentlyCreated ? $accepted++ : $updated++;

            if ($evidenceRaw !== null && trim((string) $evidenceRaw) !== '') {
                Evidence::firstOrCreate([
                    'measure_id' => $measure->id,
                    'path_or_url' => trim((string) $evidenceRaw),
                ], [
                    'type' => EvidenceType::Link,
                    'uploaded_via' => 'admin',
                ]);
            }

            $this->ensureCredential($measure);
        }

        return new MeasureImportReport($accepted, $updated, $this->warnings, $this->credentials);
    }

    private function resolveDirection(string $label, string $title, int $measureNumber): Direction
    {
        $label = trim($label);

        // «Качество» в файле обозначает и направление 23 (внутреннее обеспечение
        // качества), и направление 27 (распределение оценок/апелляции) — различаем по
        // содержанию мероприятия, см. Result задачи task-005 в Obsidian.
        if ($label === 'Качество' && Str::contains(mb_strtolower($title), ['оценок', 'апелляц'])) {
            $number = 27;
        } else {
            $number = self::DIRECTION_MAP[$label] ?? null;
        }

        if ($number === null) {
            $this->warnings[] = "Мероприятие №{$measureNumber}: направление «{$label}» не входит в справочник — заведено как новое, уточнить у координатора.";

            return Direction::firstOrCreate(
                ['name' => $label],
                ['number' => Direction::max('number') + 1, 'in_summary' => false],
            );
        }

        return Direction::firstOrCreate(
            ['number' => $number],
            ['name' => self::DIRECTION_NAMES[$number] ?? $label],
        );
    }

    private function resolveResponsible(?string $name): ?Responsible
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return Responsible::firstOrCreate(['name' => $name]);
    }

    private function resolveRiskLevel(mixed $raw, int $measureNumber): ?RiskLevel
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        if (! isset(self::RISK_MAP[$raw])) {
            $this->warnings[] = "Мероприятие №{$measureNumber}: неизвестный уровень риска «{$raw}».";

            return null;
        }

        return self::RISK_MAP[$raw];
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value));
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Carbon::createFromFormat('d.m.Y', trim((string) $value))->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function ensureCredential(Measure $measure): void
    {
        if ($measure->credential()->exists()) {
            return;
        }

        $generated = app(MeasureCredentialGenerator::class)->generate($measure);

        $this->credentials[] = [
            'measure_number' => $measure->number,
            'login' => $generated['login'],
            'password' => $generated['password'],
        ];
    }
}
