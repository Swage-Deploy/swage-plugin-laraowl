<?php

namespace Swage\LaraOwl;

use App\Models\Site;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The LaraOwl server a site reports to.
 *
 * @property int $site_id
 * @property string $server_url
 * @property string $token
 * @property float|null $sample_requests
 * @property float|null $sample_commands
 * @property float|null $sample_exceptions
 * @property float|null $sample_tasks
 */
#[Fillable(['site_id', 'server_url', 'token', 'sample_requests', 'sample_commands', 'sample_exceptions', 'sample_tasks'])]
#[Hidden(['token'])]
class LaraOwlSite extends Model
{
    protected $table = 'laraowl_sites';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'sample_requests' => 'float',
            'sample_commands' => 'float',
            'sample_exceptions' => 'float',
            'sample_tasks' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public static function for(Site $site): ?self
    {
        return static::query()->firstWhere('site_id', $site->id);
    }
}
