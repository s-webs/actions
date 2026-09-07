<?php

namespace App\Enums;

enum SubmittedVia: string
{
    case Admin = 'admin';
    case MeasureSession = 'measure_session';
}
