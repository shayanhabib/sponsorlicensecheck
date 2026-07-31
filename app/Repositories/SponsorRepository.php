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
                if (DB::connection()->getDriverName() === 'mysql') {
                    $builder->whereFullText(['company_name', 'town', 'county', 'postcode', 'licence_number', 'organisation_type'], $term);
                }

                $like = '%' . addcslashes($term, '%_') . '%';
                $builder
                    ->orWhere('company_name', 'like', $like)
                    ->orWhere('town', 'like', $like)
                    ->orWhere('county', 'like', $like)
                    ->orWhere('postcode', 'like', $like)
                    ->orWhere('licence_number', 'like', $like);
            });
        }

        foreach (['company' => 'company_name', 'town' => 'town', 'county' => 'county', 'rating' => 'rating', 'status' => 'status'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, 'like', '%' . addcslashes((string) $filters[$filter], '%_') . '%');
            }
        }

        if (! empty($filters['route'])) {
            if (DB::connection()->getDriverName() === 'mysql') {
                $query->whereJsonContains('routes', (string) $filters['route']);
            } else {
                $query->where('routes', 'like', '%' . addcslashes((string) $filters['route'], '%_') . '%');
            }
        }

        $sort = (string) ($filters['sort'] ?? 'alphabetical');

        return $query
            ->orderBy($sort === 'newest' ? 'imported_at' : 'company_name', $sort === 'newest' ? 'desc' : 'asc')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function statistics(): array
    {
        return Cache::remember('statistics', 300, function (): array {
            $totalRoutes = DB::connection()->getDriverName() === 'mysql'
                ? (int) DB::table('sponsors')
                    ->selectRaw('COUNT(DISTINCT sponsor_routes.route_name) AS route_count')
                    ->fromRaw('sponsors, JSON_TABLE(routes, "$[*]" COLUMNS(route_name VARCHAR(255) PATH "$")) sponsor_routes')
                    ->value('route_count')
                : $this->countRoutesWithoutJsonTable();

            return [
                'total_sponsors' => Sponsor::count(),
                'total_routes' => $totalRoutes,
                'latest_import' => Sponsor::max('imported_at'),
            ];
        });
    }

    private function countRoutesWithoutJsonTable(): int
    {
        $routes = [];

        Sponsor::query()->select('routes')->chunk(1000, function ($sponsors) use (&$routes): void {
            foreach ($sponsors as $sponsor) {
                foreach ($sponsor->routes ?? [] as $route) {
                    $routes[$route] = true;
                }
            }
        });

        return count($routes);
    }
}
