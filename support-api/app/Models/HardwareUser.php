<?php
// Questa è una tabella pivot per la quale si vogliono registrare le modifiche. quindi si intercettano i metodi.
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class HardwareUser extends Pivot
{
    public $timestamps = true;
    protected $table = 'hardware_user';

    protected static function boot()
    {
        parent::boot();

        // Aggiunge i log quando si creano o rimuovono associazioni hardware_user

        static::created(function ($model) {
            HardwareAuditLog::create([
                'log_subject' => 'hardware_user',
                'log_type' => 'create',
                'modified_by' => auth()->id(),
                'hardware_id' => $model->hardware_id,
                'old_data' => null,
                'new_data' => json_encode($model->toArray()),
            ]);
        });

        static::deleted(function ($model) {
            HardwareAuditLog::create([
                'log_subject' => 'hardware_user',
                'log_type' => 'delete',
                'modified_by' => auth()->id(),
                'hardware_id' => $model->hardware_id,
                'old_data' => json_encode($model->toArray()),
                'new_data' => null,
            ]);
        });
    }
}