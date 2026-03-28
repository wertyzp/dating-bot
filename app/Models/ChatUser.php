<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property $id
 * @property $chat_id
 * @property $user_id
 */

class ChatUser extends Model
{
    protected $fillable = [
        'chat_id',
        'user_id',
    ];

}


