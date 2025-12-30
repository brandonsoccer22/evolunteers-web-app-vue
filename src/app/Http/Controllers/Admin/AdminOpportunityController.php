<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\ApiController;
use App\Http\Requests\OpportunityUpsertRequest;
use App\Models\Opportunity;
use App\Models\Tag;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Resources\OpportunityResource;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\BrowserResponse;

class AdminOpportunityController extends ApiController
{

    public function showCreate(Request $request)
    {
        if (!$request->user()->can('create', Opportunity::class)) {
            abort(403, 'Unauthorized.');
        }

        if (static::isApiRequest($request)) {
            return ApiResponse::error('Use POST /opportunities to create.', 405);
        }

        return BrowserResponse::render('admin/opportunities/OpportunityForm');
    }

    public function store(OpportunityUpsertRequest $request)
    {
        if (!$request->user()->can('create', Opportunity::class)) {
            abort(403, 'Unauthorized.');
        }

        $request->validate(
            [
                'organization_ids' => 'required|array|min:1',
            ],
            [
                'organization_ids.required' => 'Please select at least one organization.',
                'organization_ids.array' => 'Organizations must be provided as a list.',
                'organization_ids.min' => 'Please select at least one organization.',
            ]
        );

        $data = $request->validated();
        $organizationIds = $data['organization_ids'] ?? [];
        unset($data['organization_ids']);

        $this->assertOrganizationAccess($request->user(), $organizationIds);

        $opportunity = Opportunity::create($data);

        if (!empty($organizationIds)) {
            $opportunity->organizations()->sync($organizationIds);
        }

        $this->syncTags($opportunity, $data['tag_names'] ?? []);

        $resource = new OpportunityResource($opportunity->load(['organizations', 'tags']));

        if (static::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }
        return redirect()->route('admin.opportunities.show', ['opportunity' => $opportunity])->with('success', 'Opportunity created.');
    }

    public function update(OpportunityUpsertRequest $request, $id)
    {
        $opportunity = Opportunity::findOrFail($id);
        if (!$request->user()->can('update', $opportunity)) {
            abort(403, 'Unauthorized.');
        }

        $data = $request->validated();

        $opportunity->update($data);
        $opportunity->save();


        $resource = new OpportunityResource($opportunity->load(['organizations', 'tags']));

        if (static::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }
        return redirect()
            ->route('admin.opportunities.show', ['opportunity' => $opportunity])
            ->with('success', 'Opportunity updated.');
    }

    public function destroy(Request $request, $id)
    {
        $opportunity = Opportunity::findOrFail($id);
        if (!$request->user()->can('delete', $opportunity)) {
            abort(403, 'Unauthorized.');
        }
        $opportunity->delete();

        if (static::isApiRequest($request)) {
            return ApiResponse::success('Opportunity deleted.');
        }

        return redirect()->route('admin.opportunities.index')->with('success', 'Opportunity deleted.');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user->can('viewAny', Opportunity::class)) {
            abort(403, 'Unauthorized.');
        }
        $perPage = max(1, min($request->integer('per_page', 10), 100));
        $sort = $request->query('sort', 'created_at');
        $direction = $request->query('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortable = ['name', 'description', 'created_at', 'updated_at', 'start_date'];
        if (!in_array($sort, $sortable, true)) {
            $sort = 'created_at';
        }

        $opportunities = Opportunity::with(['organizations', 'tags'])
            ->when(!$user->can('viewAll', Opportunity::class), function ($query) use ($user) {
                $query->visibleToUser($user);
            })
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->query('name'), fn ($query, $name) => $query->where('name', 'like', "%{$name}%"))
            ->when($request->query('description'), fn ($query, $description) => $query->where('description', 'like', "%{$description}%"))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->appends($request->query());

        $resource = OpportunityResource::collection($opportunities);
        $resourceResponse = $resource->response()->getData(true);

        if (static::isApiRequest($request)) {
            return response()->json($resourceResponse);
        }

        return BrowserResponse::render('admin/opportunities/OpportunitiesIndex', [
            'opportunities' => $resourceResponse['data'] ?? [],
            'meta' => $resourceResponse['meta'] ?? null,
            'links' => $resourceResponse['links'] ?? null,
        ]);
    }

    public function show(Request $request, $id)
    {
        $opportunity = Opportunity::with(['organizations', 'tags'])->findOrFail($id);
        if (!$request->user()->can('view', $opportunity)) {
            abort(403, 'Unauthorized.');
        }
        $resource = new OpportunityResource($opportunity);

        if (static::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return BrowserResponse::render('admin/opportunities/OpportunityForm', [
            'opportunity' => $resource->resolve(),
        ]);
    }

    public function attachOrganization(Request $request, $opportunityId, $organizationId)
    {
        $opportunity = Opportunity::findOrFail($opportunityId);
        $organization = Organization::findOrFail($organizationId);
        $request->user()->can('attachOrganization', [$opportunity, $organization]);
        $opportunity->organizations()->syncWithoutDetaching([$organization->id]);

        $resource = new OpportunityResource($opportunity->load('organizations'));

        if (static::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return redirect()->back()->with('success', 'Organization added.');
    }

    public function detachOrganization(Request $request, $opportunityId, $organizationId)
    {
        $opportunity = Opportunity::findOrFail($opportunityId);
        $organization = Organization::findOrFail($organizationId);
        $request->user()->can('detachOrganization', [$opportunity, $organization]);
        $opportunity->organizations()->detach($organization->id);

        $resource = new OpportunityResource($opportunity->load('organizations'));

        if (static::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return redirect()->back()->with('success', 'Organization removed.');
    }

    public function addTag(Request $request, $opportunityId)
    {
        $opportunity = Opportunity::findOrFail($opportunityId);
        if (!$request->user()->can('update', $opportunity)) {
            abort(403, 'Unauthorized.');
        }
        $validated = $request->validate([
            'tag_name' => 'required|string|max:255',
        ]);

        $tag = Tag::firstOrCreate(['name' => trim($validated['tag_name'])]);
        $opportunity->tags()->syncWithoutDetaching([$tag->id]);

        $resource = new OpportunityResource($opportunity->load(['organizations', 'tags']));

        if (static::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return redirect()->back()->with('success', 'Tag added.');
    }

    public function removeTag(Request $request, $opportunityId)
    {
        $opportunity = Opportunity::findOrFail($opportunityId);
        if (!$request->user()->can('update', $opportunity)) {
            abort(403, 'Unauthorized.');
        }
        $validated = $request->validate([
            'tag_name' => 'required|string|max:255',
        ]);

        $tag = Tag::where('name', trim($validated['tag_name']))->first();
        if ($tag) {
            $opportunity->tags()->detach($tag->id);
        }

        $resource = new OpportunityResource($opportunity->load(['organizations', 'tags']));

        if (static::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return redirect()->back()->with('success', 'Tag removed.');
    }

    private function syncTags(Opportunity $opportunity, array $tagNames): void
    {
        $cleanNames = collect($tagNames)
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values();

        if ($cleanNames->isEmpty()) {
            $opportunity->tags()->sync([]);
            return;
        }

        $tagIds = $cleanNames->map(function ($name) {
            return Tag::firstOrCreate(['name' => $name])->id;
        })->all();

        $opportunity->tags()->sync($tagIds);
    }

    /**
     * Ensure the current user can manage all provided organization IDs.
     */
    private function assertOrganizationAccess(?User $user, array $organizationIds): void
    {
        if (empty($organizationIds)) {
            if ($user && !$user->can('viewAll', Organization::class)) {
                abort(403, 'Organization managers must select at least one organization.');
            }
            return;
        }

        if (!$user) {
            abort(403, 'Unauthorized.');
        }

        if ($user->can('viewAll', Organization::class)) {
            return;
        }

        $manageableIds = $this->manageableOrganizationIds($user);
        $invalidIds = array_diff($organizationIds, $manageableIds);

        if (!empty($invalidIds)) {
            abort(403, 'Unauthorized.');
        }
    }

    private function manageableOrganizationIds(User $user): array
    {
        return Organization::visibleToUser($user)->pluck('organizations.id')->all();
    }
}
