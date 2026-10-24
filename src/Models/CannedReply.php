<?php

namespace Modules\Glitter\HelpDesk\Models;

use Illuminate\Database\Eloquent\Model;

class CannedReply extends Model
{
    protected $table = 'helpdesk_canned_replies';

    protected $fillable = ['title', 'body', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean'];
}
