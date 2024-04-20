<?php

namespace Tests\Utils\Enums;

enum ImageableType: string
{
    case TASK = \Tests\Utils\Models\Task::class;
}
