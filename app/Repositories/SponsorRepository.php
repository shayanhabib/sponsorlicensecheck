<?php

namespace App\Repositories;

use App\Models\Sponsor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SponsorRepository
{
    public function search(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = Sponsor::query();
        $term = trim((string) ($filters['q'] ?? ''));

        if ($term !== '') {
            $query->where(function (Builder $builder) use ($term): void {
                $builder
                    ->whereFullText(['company_name', 'town', 'county', 'postcode', 'licence_number', 'organisation_type'], $term)
                    ->orWhere('company_name', 'like', '%' . addcslashes($term, '%_') . '%')
                    ->orWhere('town', 'like', '%' . addcslashes($term, '%_') . '%')
                    ->orWhere('county', 'like', '%' . addcslashes($term, '%_') . '%')
                    ->orWhere('postcode', 'like', '%' . addcslashes($term, '%_') . '%');
            });
        }

        foreach (['company' => 'company_name', 'town' => 'town', 'county' => 'county', 'rating' => 'rating', 'status' => 'status'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, 'like', '%' . addcslashes((string) $filters[$filter], '%_') . '%');
            }
        }

        if (! empty($filters['route'])) {
            $query->whereJsonContains('routes', (string) $filters['route']);
        }

        $sort = (string) ($filters['sort'] ?? 'alphabetical');

        return $query
            ->orderBy($sort === 'newest' ? 'imported_at' : 'company_name', $sort === 'newest' ? 'desc' : 'asc')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function statistics(): array
    {
        return Cache::remember('statistics', 300, fn (): array => [
            'total_sponsors' => Sponsor::count(),
            'total_routes' => (int) DB::table('sponsors')
                ->selectRaw('COUNT(DISTINCT sponsor_routes.route_name) AS route_count')
                ->fromRaw('sponsors, JSON_TABLE(routes, "$[*]" COLUMNS(route_name VARCHAR(255) PATH "$")) sponsor_routes')
                ->value('route_count'),
            'latest_import' => Sponsor::max('imported_at'),
        ]);
    }
}
