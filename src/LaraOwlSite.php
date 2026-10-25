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
 */
#[Fillable(['site_id', 'server_url', 'token'])]
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
