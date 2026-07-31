<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Sponsor extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_hash',
        'company_name',
        'slug',
        'town',
        'county',
        'postcode',
        'licence_number',
        'organisation_type',
        'routes',
        'rating',
        'status',
        'imported_at',
    ];

    protected $casts = [
        'routes' => 'array',
        'imported_at' => 'datetime',
    ];

    public function getRouteListAttribute(): string
    {
        return implode(', ', $this->routes ?? []);
    }

    public static function makeSlug(string $name, ?string $identity = null): string
    {
        $base = Str::slug($name) ?: 'sponsor';
        $suffix = substr(sha1(Str::lower(trim($name . '|' . ($identity ?? '')))), 0, 10);

        return $base . '-' . $suffix;
    }
}
