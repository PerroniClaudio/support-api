<?php
// Questa è una tabella pivot per la quale si vogliono registrare le modifiche. quindi si intercettano i metodi.
namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\Pivot;

class HardwareUser extends Pivot
{
    public $timestamps = true;
    protected $table = 'hardware_user';

    protected static function boot()
    {
        parent::boot();

        // Aggiunge i log quando si creano o rimuovono associazioni hardware_user

        static::creating(function ($model) {
            $model->created_at = Carbon::now();
            $model->updated_at = Carbon::now();
            $model->created_by = $model->created_by ?? auth()->id() ?? null;
            HardwareAuditLog::create([
                'log_subject' => 'hardware_user',
                'log_type' => 'create',
                'modified_by' => auth()->id(),
                'hardware_id' => $model->hardware_id,
                'old_data' => null,
                'new_data' => json_encode(["user_id" => $model->user_id]),
            ]);
        });

        static::deleting(function ($model) {
            HardwareAuditLog::create([
                'log_subject' => 'hardware_user',
                'log_type' => 'delete',
                'modified_by' => auth()->id(),
                'hardware_id' => $model->hardware_id,
                'old_data' => json_encode(["user_id" => $model->user_id]),
                'new_data' => null,
            ]);
        });
    }
}