<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 *  Field    Type    Description
 *  id    Integer    Unique identifier for this user or bot. This number may have more than 32 significant bits and some programming languages may have difficulty/silent defects in interpreting it. But it has at most 52 significant bits, so a 64-bit integer or double-precision float type are safe for storing this identifier.
 *  is_bot    Boolean    True, if this user is a bot
 *  first_name    String    User's or bot's first name
 *  last_name    String    Optional. User's or bot's last name
 *  username    String    Optional. User's or bot's username
 *  language_code    String    Optional. IETF language tag of the user's language
 *  is_premium    True    Optional. True, if this user is a Telegram Premium user
 *  added_to_attachment_menu    True    Optional. True, if this user added the bot to the attachment menu
 *  can_join_groups    Boolean    Optional. True, if the bot can be invited to groups. Returned only in getMe.
 *  can_read_all_group_messages    Boolean    Optional. True, if privacy mode is disabled for the bot. Returned only in getMe.
 *  supports_inline_queries    Boolean    Optional. True, if the bot supports inline queries. Returned only in getMe.
 * /
 */

/**
 * @property int $id Unique identifier for this user or bot. This number may have more than 32 significant bits and some programming languages may have difficulty/silent defects in interpreting it. But it has at most 52 significant bits, so a 64-bit integer or double-precision float type are safe for storing this identifier.
 * @property bool $is_bot True, if this user is a bot
 * @property string $first_name  User's or bot's first name
 * @property string|null $last_name Optional. User's or bot's last name
 * @property string|null $username Optional. User's or bot's username
 * @property string|null $language_code Optional. IETF language tag of the user's language
 * @property bool|null $is_premium Optional. True, if this user is a Telegram Premium user
 * @property bool|null $added_to_attachment_menu Optional. True, if this user added the bot to the attachment menu
 * @property bool|null $can_join_groups Optional. True, if the bot can be invited to groups. Returned only in getMe.
 * @property bool|null $can_read_all_group_messages Optional. True, if privacy mode is disabled for the bot. Returned only in getMe.
 * @property bool|null $supports_inline_queries Optional. True, if the bot supports inline queries. Returned only in getMe.
 *
 */

class User extends Model
{

    public $incrementing = false;
    protected $fillable = [
        'id',
        'is_bot',
        'first_name',
        'last_name',
        'username',
        'language_code',
        'is_premium',
        'added_to_attachment_menu',
        'can_join_groups',
        'can_read_all_group_messages',
        'supports_inline_queries',
    ];

    public function chats(): BelongsToMany
    {
        return $this->belongsToMany(Chat::class, 'chat_users', 'user_id', 'chat_id');
    }

    public function getName(): string
    {
        // format: first name + username (if available) + last name (if available)
        $name = $this->first_name;
        if ($this->username) {
            $name .= ' (@' . $this->username . ')';
        }
        if ($this->last_name) {
            $name .= ' ' . $this->last_name;
        }
        return $name;
    }

}
