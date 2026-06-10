<?php

namespace App\Models;

use Database\Factories\ResearchJobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ResearchJob extends Model
{
    /** @use HasFactory<ResearchJobFactory> */
    use HasFactory;

    /**
     * Assign an opaque UUID on creation so it can serve as the public route key.
     */
    protected static function booted(): void
    {
        static::creating(function (ResearchJob $job): void {
            if (blank($job->uuid)) {
                $job->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Route-model binding resolves jobs by their UUID, keeping sequential integer
     * ids out of public URLs.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'status',
        'current_step',
        'progress',
        'user_input',
        'extracted_params',
        'queries',
        'sources',
        'report',
        'error',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'extracted_params' => 'array',
            'queries' => 'array',
            'sources' => 'array',
            'progress' => 'integer',
        ];
    }

    /**
     * The user that owns the research job.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
