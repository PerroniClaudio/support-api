<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HardwareCompanyAuditLog extends Model {

  // Specifica il nome della tabella
  protected $table = 'hardware_company_audit_log';

  protected $fillable = [
    'type',
    'modified_by',
    'hardware_id',
    'old_company_id',
    'new_company_id',
  ];

  public function author() {
    return $this->belongsTo(User::class, 'modified_by');
  }

  public function hardware() {
    return $this->belongsTo(Hardware::class);
  }

  public function oldCompany() {
    return $this->belongsTo(Company::class, 'old_company_id');
  }

  public function newCompany() {
    return $this->belongsTo(Company::class, 'new_company_id');
  }

}
