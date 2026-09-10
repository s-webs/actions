<?php

namespace App\Enums;

enum RiskLevel: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return __('enums.risk_level.'.$this->value);
    }
}
