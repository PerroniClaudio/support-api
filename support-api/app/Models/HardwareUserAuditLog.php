<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HardwareUserAuditLog extends Model {

  // Specifica il nome della tabella
  protected $table = 'hardware_user_audit_log';

  protected $fillable = [
    'type',
    'modified_by',
    'hardware_id',
    'user_id',
  ];

  public function author() {
    return $this->belongsTo(User::class, 'modified_by');
  }

  public function user() {
    return $this->belongsTo(User::class, 'user_id');
  }

  public function hardware() {
    return $this->belongsTo(Hardware::class);
  }

}
