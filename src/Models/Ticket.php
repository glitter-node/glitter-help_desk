<?php

namespace Modules\Glitter\HelpDesk\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $table = 'helpdesk_tickets';

    protected $fillable = ['ticket_number', 'user_id', 'create_request_key', 'category_id', 'subject', 'status', 'priority', 'assignee_id', 'first_response_at', 'resolved_at', 'closed_at'];

    protected $hidden = ['create_request_key'];

    protected $casts = ['first_response_at' => 'datetime', 'resolved_at' => 'datetime', 'closed_at' => 'datetime'];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
