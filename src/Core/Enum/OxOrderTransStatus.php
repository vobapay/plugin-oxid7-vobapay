<?php

namespace Vobapay\Payment\Core\Enum;

class OxOrderTransStatus
{
    public const NOT_FINISHED = 'NOT_FINISHED';
    public const OK = 'OK';
    public const ERROR = 'ERROR';

    public static function getAll(): array
    {
        return [
            self::NOT_FINISHED,
            self::OK,
            self::ERROR
        ];
    }
}
