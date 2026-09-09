<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Enums;

enum WatermarkAlignment: string
{

    case H_LEFT = 'left';
    case H_CENTER = 'center';
    case H_RIGHT = 'right';

    case V_TOP = 'top';
    case V_MIDDLE = 'middle';
    case V_BOTTOM = 'bottom';

    public function isHorizontal(): bool
    {
        return match ($this) {
            self::H_LEFT,
            self::H_CENTER,
            self::H_RIGHT => true,
            default => false,
        };
    }

    public function isVertical(): bool
    {
        return match ($this) {
            self::V_TOP,
            self::V_MIDDLE,
            self::V_BOTTOM => true,
            default => false,
        };
    }
}
