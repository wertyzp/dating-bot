<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * $table->id();
 * $table->bigInteger('chat_id');
 * $table->bigInteger('user_id');
 * $table->bigInteger('message_id');
 * $table->text('text');
 * $table->jsonb('data');
 * $table->timestamps();
 *
 * @property int $id
 * @property int $chat_id
 * @property int $user_id
 * @property int $message_id
 * @property string $text
 * @property array $data
 * @property string $created_at
 * @property string $updated_at
 */

class Message extends Model
{
    protected $fillable = [
        'chat_id',
        'user_id',
        'message_id',
        'text',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}
