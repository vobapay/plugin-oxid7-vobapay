<?php

namespace Vobapay\Payment\Core\Enum;

class OxOrderFolder
{
    public const NEW = 'ORDERFOLDER_NEW';
    public const FINISHED = 'ORDERFOLDER_FINISHED';
    public const PROBLEMS = 'ORDERFOLDER_PROBLEMS';

    public static function getAll(): array
    {
        return [
            self::NEW,
            self::FINISHED,
            self::PROBLEMS
        ];
    }
}
