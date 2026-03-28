<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property $id
 * @property $type
 * @property $title
 * @property $username
 * @property $first_name
 * @property $last_name
 * @property $created_at
 * @property $updated_at
 * @property Collection<User> $users
 * @property Collection<Chat> $chats
 */
class Chat extends Model
{
    protected $fillable = [
        'id',
        'type',
        'title',
        'username',
        'first_name',
        'last_name',
    ];

    // relation to user over ChatUser
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_users', 'chat_id', 'user_id');
    }

}
