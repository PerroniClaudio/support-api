<?php

namespace App\Models\Domustart;

use Illuminate\Database\Eloquent\Model;
use App\Models\Ticket;

class DomustartTicket extends Ticket {
    protected $fillable;

    public function __construct(array $attributes = []) {
        // Unisci i campi fillable del genitore con i nuovi campi.
        // Questo va fatto nel costruttore o in un metodo statico come `booted()`
        // per assicurarsi che `parent::$fillable` sia già disponibile.
        $this->fillable = array_merge(parent::$fillable, [
            'is_visible_all_users',
            'is_visible_admin',
        ]);

        parent::__construct($attributes);
    }
}
