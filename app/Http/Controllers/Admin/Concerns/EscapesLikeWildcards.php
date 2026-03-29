<?php

namespace App\Http\Controllers\Admin\Concerns;

trait EscapesLikeWildcards
{
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
