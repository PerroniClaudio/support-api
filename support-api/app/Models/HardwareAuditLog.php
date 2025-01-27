<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HardwareAuditLog extends Model {

  // Specifica il nome della tabella
  protected $table = 'hardware_audit_log';

  protected $fillable = [
    'modified_by',
    'hardware_id',
    'old_data',
    'new_data',
    'log_subject', //hardware, hardware_user, hardware_company
    'log_type', //create, delete, update, permanent-delete
  ];

  public function author() {
    return $this->belongsTo(User::class, 'modified_by');
  }

  public function user() {
    if($this->log_subject !== 'hardware_user') {
      return null;
    }
    if($this->log_type === 'delete') {
      $oldData = $this->old_data();
      $userId = $oldData['user_id'];
      $user = User::find($userId);
      return $user;
    }
    if($this->log_type === 'create') {
      $newData = $this->new_data();
      $userId = $newData['user_id'];
      $user = User::find($userId);
      return $user;
    }
    return null;
  }

  public function hardware() {
    return $this->belongsTo(Hardware::class);
  }

  public function company() {
    if($this->log_subject !== 'hardware_company') {
      return null;
    }
    if($this->log_type === 'delete') {
      $oldData = $this->old_data();
      $companyId = $oldData['company_id'];
      $company = Company::find($companyId);
      return $company;
    }
    if($this->log_type === 'create') {
      $newData = $this->new_data();
      $companyId = $newData['company_id'];
      $company = Company::find($companyId);
      return $company;
    }
    return null;
  }

  public function oldData() {
    return json_decode($this->old_data, true);
  }

  public function newData() {
    return json_decode($this->new_data, true);
  }

}
