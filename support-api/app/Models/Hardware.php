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
    'is_exclusive_use',
  ];

  protected static function boot()
    {
        parent::boot();
        // è stato deciso di tenere i log delle assegnazioni, nel caso dell'azienda si deve intercettare il company_id nell'hardware.
        
        // Aggiunge un log quando viene creato un nuovo hardware
        static::created(function ($model) {
            // if ($model->company_id != null) {
                HardwareAuditLog::create([
                  'log_subject' => 'hardware',
                  'log_type' => 'create',
                  'modified_by' => auth()->id(),
                  'hardware_id' => $model->id,
                  'old_data' => null,
                  'new_data' => json_encode($model->toArray()),
                ]);
            // }
        });

        // Aggiunge un log quando viene modificato un hardware
        static::updating(function ($model) {
          
          $model->updated_at = now();

          HardwareAuditLog::create([
            'log_subject' => 'hardware',
            'log_type' => 'update',
            'modified_by' => auth()->id(),
            'hardware_id' => $model->id,
            'old_data' => json_encode($model->getOriginal()),
            'new_data' => json_encode($model->toArray()),
          ]);

          // if ($model->isDirty('company_id')) {
          //     $oldCompanyId = $model->getOriginal('company_id');
          //     $newCompanyId = $model->company_id;
          //     $type = $oldCompanyId == null
          //         ? 'create'
          //         : ($newCompanyId == null
          //             ? 'delete'
          //             : 'update');
          //     HardwareAuditLog::create([
          //       'log_subject' => 'hardware',
          //       'log_type' => $type,
          //       'modified_by' => auth()->id(),
          //       'hardware_id' => $model->id,
          //       'old_data' => in_array($type, ['delete', 'update']) ? json_encode($model->getOriginal()) : null,
          //       'new_data' => in_array($type, ['create', 'update']) ? json_encode($model->toArray()) : null,
          //     ]);
          // }
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
      ->withPivot('created_by', 'responsible_user_id', 'created_at', 'updated_at');
  }

  public function tickets() {
    return $this->belongsToMany(Ticket::class);
  }

}

        