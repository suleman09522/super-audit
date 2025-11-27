<?php

namespace SuperAudit\SuperAudit\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AuditLog Model
 * 
 * Represents an audit log entry that tracks database changes.
 * Each log contains information about the table, record, action,
 * user, and the old/new data states.
 */
class AuditLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'super_audit_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'table_name',
        'record_id',
        'action',
        'user_id',
        'url',
        'old_data',
        'new_data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Disable updated_at timestamp as we only need created_at for logs
     */
    const UPDATED_AT = null;

    /**
     * Get the user who performed the action.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\Models\User'), 'user_id');
    }

    /**
     * Scope a query to only include logs for a specific table.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $tableName
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForTable($query, $tableName)
    {
        return $query->where('table_name', $tableName);
    }

    /**
     * Scope a query to only include logs for a specific record.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $recordId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForRecord($query, $recordId)
    {
        return $query->where('record_id', $recordId);
    }

    /**
     * Scope a query to only include logs for a specific action.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $action
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to only include logs by a specific user.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
