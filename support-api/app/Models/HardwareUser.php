<?php
// Questa è una tabella pivot per la quale si vogliono registrare le modifiche. quindi si intercettano i metodi.
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class HardwareUser extends Pivot
{
    protected $table = 'hardware_user';

    protected static function boot()
    {
        parent::boot();

        // Aggiunge i log quando si creano o rimuovono associazioni hardware_user

        static::created(function ($model) {
            HardwareUserAuditLog::create([
                'type' => 'created',
                'modified_by' => auth()->id(),
                'hardware_id' => $model->hardware_id,
                'user_id' => $model->user_id,
            ]);
        });

        static::deleted(function ($model) {
            HardwareUserAuditLog::create([
                'type' => 'deleted',
                'modified_by' => auth()->id(),
                'hardware_id' => $model->hardware_id,
                'user_id' => $model->user_id,
            ]);
        });
    }
}