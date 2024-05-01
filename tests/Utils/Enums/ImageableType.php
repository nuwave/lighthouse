<?php declare(strict_types=1);

namespace Tests\Utils\Enums;

use Tests\Utils\Models\Task;

enum ImageableType: string
{
    case TASK = Task::class;
}
