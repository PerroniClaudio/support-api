<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hardware extends Model {
  use SoftDeletes, HasFactory;

  // Specifica il nome della tabella
  protected $table = 'hardware';

  protected $fillable = [
    'make',
    'model',
    'serial_number',
    'company_asset_number',
    'purchase_date',
    'company_id',
    'hardware_type_id',
    'ownership_type',
    'ownership_type_note',
    'notes',
  ];

  protected static function boot()
    {
        parent::boot();

        // Aggiunge un log quando vene creato un nuovo hardware, se company_id è diverso da null
        static::created(function ($model) {
            if ($model->company_id != null) {
                HardwareAuditLog::create([
                  'log_subject' => 'hardware',
                  'log_type' => 'create',
                  'modified_by' => auth()->id(),
                  'hardware_id' => $model->id,
                  'old_data' => null,
                  'new_data' => json_encode($model->toArray()),
                ]);
            }
        });

        // Aggiunge un log quando viene modificato il campo company_id
        static::updating(function ($model) {
          if ($model->isDirty('company_id')) {
              $oldCompanyId = $model->getOriginal('company_id');
              $newCompanyId = $model->company_id;

              $type = $oldCompanyId == null
                  ? 'create'
                  : ($newCompanyId == null
                      ? 'delete'
                      : 'update');

              HardwareAuditLog::create([
                'log_subject' => 'hardware',
                'log_type' => $type,
                'modified_by' => auth()->id(),
                'hardware_id' => $model->id,
                'old_data' => in_array($type, ['delete', 'update']) ? json_encode($model->getOriginal()) : null,
                'new_data' => in_array($type, ['create', 'update']) ? json_encode($model->toArray()) : null,
              ]);
          }
        });
    }

  public function company() {
    return $this->belongsTo(Company::class);
  }

  public function hardwareType() {
    return $this->belongsTo(HardwareType::class);
  }

  // public function users() {
  //   return $this->belongsToMany(User::class);
  // }
  public function users() {
    return $this->belongsToMany(User::class, 'hardware_user')
      ->using(HardwareUser::class)
      ->withPivot('created_by', 'created_at', 'updated_at');
  }

  public function tickets() {
    return $this->belongsToMany(Ticket::class);
  }

}

        