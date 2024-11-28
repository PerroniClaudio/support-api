<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypeFormFields extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_type_id',
        'field_name',
        'field_type',
        'field_label',
        'required',
        'description',
        'placeholder',
        'default_value',
        'options',
        'validation',
        'validation_message',
        'help_text',
        'order',
    ];

    public function ticketType() {
        return $this->belongsTo(TicketType::class);
    }
}
