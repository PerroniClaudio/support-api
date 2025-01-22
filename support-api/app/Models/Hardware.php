<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hardware extends Model {
  use HasFactory;

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
                HardwareCompanyAuditLog::create([
                    'type' => 'create',
                    'hardware_id' => $model->id,
                    'new_company_id' => $model->company_id,
                    'modified_by' => auth()->id(),
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

                HardwareCompanyAuditLog::create([
                    'type' => $type,
                    'hardware_id' => $model->id,
                    'old_company_id' => $oldCompanyId,
                    'new_company_id' => $newCompanyId,
                    'modified_by' => auth()->id(),
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

  public function users() {
    return $this->belongsToMany(User::class);
  }

}

        