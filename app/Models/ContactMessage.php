<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 客服留言模型
 */
class ContactMessage extends Model
{
    protected $fillable = [
        'name',
        'email',
        'company',
        'message',
        'is_read',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }
}
