<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffAttendanceScanRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = ['staff_profile_id', 'attendance_date', 'qr_bucket', 'scanned_at', 'expires_at', 'status', 'resolution', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['attendance_date' => 'date', 'scanned_at' => 'datetime', 'expires_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function staffProfile() { return $this->belongsTo(StaffProfile::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
