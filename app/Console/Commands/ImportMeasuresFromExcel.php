<?php

namespace App\Console\Commands;

use App\Services\MeasureImportService;
use Illuminate\Console\Command;

/**
 * CLI-обёртка над {@see MeasureImportService} для первичного наполнения БД из исходного
 * `yukma_dashboard.xlsx` — [[Функциональные требования#4.12 Импорт из Excel]].
 */
class ImportMeasuresFromExcel extends Command
{
    protected $signature = 'measures:import {path : Путь к xlsx-файлу}';

    protected $description = 'Импортировать лист «План» из Excel-файла Action Plan';

    public function handle(MeasureImportService $service): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("Файл не найден: {$path}");

            return self::FAILURE;
        }

        $report = $service->import($path);

        $this->info("Принято новых мероприятий: {$report->accepted}");
        $this->info("Обновлено существующих: {$report->updated}");

        if ($report->credentials) {
            $this->line('');
            $this->info('Сгенерированы учётные данные (показываются один раз):');
            $this->table(
                ['№', 'Логин', 'Пароль'],
                collect($report->credentials)->map(fn ($c) => [$c['measure_number'], $c['login'], $c['password']]),
            );
        }

        if ($report->warnings) {
            $this->line('');
            $this->warn('Предупреждения:');
            foreach ($report->warnings as $warning) {
                $this->warn(" - {$warning}");
            }
        }

        return self::SUCCESS;
    }
}
