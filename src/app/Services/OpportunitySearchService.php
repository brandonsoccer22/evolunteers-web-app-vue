<?php

namespace App\Services;

use App\Http\Resources\OpportunityResource;
use App\Models\Opportunity;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Typesense\Client;

class OpportunitySearchService
{
    protected Client $client;

    public function __construct()
    {
        $settings = config('scout.typesense.client-settings', []);
        $this->client = new Client($settings);
    }

    public function search(array $filters, int $perPage = 12, int $page = 1): array
    {
        $normalized = $this->normalizeFilters($filters);

        try {
            $this->ensureCollection();
            return $this->searchTypesense($normalized, $perPage, $page);
        } catch (\Throwable) {
            return $this->searchDatabase($normalized, $perPage, $page);
        }
    }

    protected function normalizeFilters(array $filters): array
    {
        $text = [];
        $organizations = [];
        $tags = [];
        $startDate = null;
        $startDateOperator = 'eq';

        foreach ($filters as $filter) {
            $field = Arr::get($filter, 'field');
            $value = Arr::get($filter, 'value');
            if ($value === null || $value === '') {
                continue;
            }

            switch ($field) {
                case 'name':
                case 'description':
                    $text[] = (string) $value;
                    break;
                case 'organization':
                    $organizations[] = (string) $value;
                    break;
                case 'tag':
                case 'tags':
                    $tags = array_merge($tags, $this->splitValues($value));
                    break;
                case 'start_date':
                    $startDate = Carbon::parse($value);
                    $startDateOperator = $this->normalizeOperator($filter['operator'] ?? 'eq');
                    break;
            }
        }

        return [
            'text' => $text,
            'organizations' => collect($organizations)->filter()->unique()->values()->all(),
            'tags' => collect($tags)->filter()->unique()->values()->all(),
            'start_date' => $startDate,
            'start_date_operator' => $startDateOperator,
        ];
    }

    protected function splitValues(mixed $value): array
    {
        if (is_array($value)) {
            return array_map('strval', $value);
        }

        return collect(explode(',', (string) $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    protected function searchTypesense(array $filters, int $perPage, int $page): array
    {
        $filterParts = [];

        if ($filters['start_date'] instanceof Carbon) {
            $filterParts[] = $this->buildDateFilter($filters['start_date_operator'], $filters['start_date']->timestamp);
        }
        if (!empty($filters['organizations'])) {
            $filterParts[] = 'organization_names:=[' . $this->quoteArray($filters['organizations']) . ']';
        }
        if (!empty($filters['tags'])) {
            $filterParts[] = 'tag_names:=[' . $this->quoteArray($filters['tags']) . ']';
        }

        $collection = (new Opportunity())->searchableAs();

        $results = $this->client->collections[$collection]->documents->search([
            'q' => !empty($filters['text']) ? implode(' ', $filters['text']) : '*',
            'query_by' => config('scout.typesense.model-settings.'.Opportunity::class.'.search-parameters.query_by', 'name,description'),
            'filter_by' => $filterParts ? implode(' && ', $filterParts) : null,
            'per_page' => $perPage,
            'page' => $page,
            'sort_by' => 'start_date_ts:asc,name:asc',
        ]);

        $ids = collect($results['hits'] ?? [])->pluck('document.id')->map(fn ($id) => (int) $id)->filter();

        $models = Opportunity::with(['organizations', 'tags'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $ordered = $ids->map(fn ($id) => $models->get($id))->filter()->values();

        return $this->buildResponse($ordered, $results['found'] ?? $ordered->count(), $perPage, $page);
    }

    protected function searchDatabase(array $filters, int $perPage, int $page): array
    {
        $query = Opportunity::with(['organizations', 'tags'])
            ->when(!empty($filters['text']), function ($builder) use ($filters) {
                $builder->where(function ($q) use ($filters) {
                    foreach ($filters['text'] as $term) {
                        $like = '%' . mb_strtolower($term) . '%';
                        $q->orWhereRaw('LOWER(name) like ?', [$like])
                            ->orWhereRaw('LOWER(description) like ?', [$like]);
                    }
                });
            })
            ->when($filters['start_date'] instanceof Carbon, function ($builder) use ($filters) {
                $op = $this->toSqlOperator($filters['start_date_operator']);
                $builder->whereDate('start_date', $op, $filters['start_date']->toDateString());
            })
            ->when(!empty($filters['organizations']), function ($builder) use ($filters) {
                $builder->whereHas('organizations', function ($q) use ($filters) {
                    $q->whereIn('name', $filters['organizations']);
                });
            })
            ->when(!empty($filters['tags']), function ($builder) use ($filters) {
                $builder->whereHas('tags', function ($q) use ($filters) {
                    $q->whereIn('name', $filters['tags']);
                });
            })
            ->orderBy('start_date')
            ->orderBy('name');

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $resource = OpportunityResource::collection($paginator);
        $resourceResponse = $resource->response()->getData(true);

        return [
            'data' => $resourceResponse['data'] ?? [],
            'meta' => $resourceResponse['meta'] ?? [],
            'links' => $resourceResponse['links'] ?? [],
        ];
    }

    protected function buildResponse(Collection $ordered, int $total, int $perPage, int $page): array
    {
        $paginator = new LengthAwarePaginator(
            $ordered,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        $resource = OpportunityResource::collection($paginator);
        $resourceResponse = $resource->response()->getData(true);

        return [
            'data' => $resourceResponse['data'] ?? [],
            'meta' => $resourceResponse['meta'] ?? [],
            'links' => $resourceResponse['links'] ?? [],
        ];
    }

    protected function ensureCollection(): void
    {
        $collection = (new Opportunity())->searchableAs();
        $schema = (new Opportunity())->typesenseCollectionSchema();

        try {
            $this->client->collections[$collection]->retrieve();
        } catch (\Throwable) {
            $this->client->collections->create($schema);
        }
    }

    protected function quoteArray(array $values): string
    {
        return collect($values)
            ->map(fn ($value) => '"' . addslashes((string) $value) . '"')
            ->implode(',');
    }

    protected function normalizeOperator(string $operator): string
    {
        return match ($operator) {
            'gt', 'gte', 'lt', 'lte', 'neq' => $operator,
            default => 'eq',
        };
    }

    protected function buildDateFilter(string $operator, int $timestamp): string
    {
        return match ($operator) {
            'gt' => 'start_date_ts:>' . $timestamp,
            'gte' => 'start_date_ts:>=' . $timestamp,
            'lt' => 'start_date_ts:<' . $timestamp,
            'lte' => 'start_date_ts:<=' . $timestamp,
            'neq' => 'start_date_ts:!=' . $timestamp,
            default => 'start_date_ts:=' . $timestamp,
        };
    }

    protected function toSqlOperator(string $operator): string
    {
        return match ($operator) {
            'gt' => '>',
            'gte' => '>=',
            'lt' => '<',
            'lte' => '<=',
            'neq' => '<>',
            default => '=',
        };
    }
}
