<?php

namespace Modules\Glitter\HelpDesk\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    public $timestamps = false;

    protected $table = 'helpdesk_messages';

    protected $fillable = ['ticket_id', 'user_id', 'request_kind', 'request_key', 'body', 'is_public', 'created_at'];

    protected $hidden = ['request_kind', 'request_key'];

    protected $casts = ['is_public' => 'boolean', 'created_at' => 'datetime'];
}
