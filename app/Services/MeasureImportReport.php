<?php

namespace App\Services;

/**
 * Результат импорта листа «План» — [[Функциональные требования#4.12 Импорт из Excel]].
 * `credentials` содержит пароли в открытом виде только на время этого ответа — нигде не
 * сохраняются, кроме `password_hash`; повторно посмотреть их можно только через
 * перегенерацию (task-015).
 */
class MeasureImportReport
{
    /**
     * @param  list<string>  $warnings
     * @param  list<array{measure_number: int, login: string, password: string}>  $credentials
     */
    public function __construct(
        public readonly int $accepted,
        public readonly int $updated,
        public readonly array $warnings,
        public readonly array $credentials,
    ) {}

    /**
     * @return array{accepted: int, updated: int, warnings: list<string>, credentials: list<array{measure_number: int, login: string, password: string}>}
     */
    public function toArray(): array
    {
        return [
            'accepted' => $this->accepted,
            'updated' => $this->updated,
            'warnings' => $this->warnings,
            'credentials' => $this->credentials,
        ];
    }
}
