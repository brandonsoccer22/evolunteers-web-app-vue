<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiController;
use App\Http\Requests\OpportunityUpsertRequest;
use App\Models\Opportunity;
use App\Models\Tag;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Resources\OpportunityResource;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\BrowserResponse;

class AdminOpportunityController extends Controller
{

    public function showCreate(Request $request)
    {
        if (ApiController::isApiRequest($request)) {
            return ApiResponse::error('Use POST /opportunities to create.', 405);
        }

        return BrowserResponse::render('admin/opportunities/OpportunityForm');
    }

    public function store(OpportunityUpsertRequest $request)
    {

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

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }
        return redirect()->route('admin.opportunities.show', ['opportunity' => $opportunity])->with('success', 'Opportunity created.');
    }

    // public function showEdit(Request $request, $id)
    // {
    //     $opportunity = Opportunity::findOrFail($id);
    //     $resource = new OpportunityResource($opportunity);
    //     if (ApiController::isApiRequest($request)) {
    //         return ApiResponse::error('Use PUT/PATCH /opportunities/{id} to update.', 405);
    //     }
    //     return BrowserResponse::render('Opportunities/Edit', [
    //         'opportunity' => $resource,
    //     ]);
    // }

    public function update(OpportunityUpsertRequest $request, $id)
    {
        $opportunity = Opportunity::findOrFail($id);
        $this->assertOpportunityAccess($request->user(), $opportunity);

        $data = $request->validated();
        $organizationIds = $data['organization_ids'] ?? null;
        unset($data['organization_ids']);
        //$data = array_map(fn($param)=>urldecode($param), $data);
        $opportunity->update($data);
        $opportunity->save();

        if (is_array($organizationIds)) {
            $this->assertOrganizationAccess($request->user(), $organizationIds);
            $opportunity->organizations()->sync($organizationIds);
        }

        $this->syncTags($opportunity, $data['tag_names'] ?? []);

        $resource = new OpportunityResource($opportunity->load(['organizations', 'tags']));

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }
        return redirect()
            ->route('admin.opportunities.show', ['opportunity' => $opportunity])
            ->with('success', 'Opportunity updated.');
    }

    public function destroy(Request $request, $id)
    {
        $opportunity = Opportunity::findOrFail($id);
        $this->assertOpportunityAccess($request->user(), $opportunity);
        $opportunity->delete();

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::success('Opportunity deleted.');
        }

        return redirect()->route('admin.opportunities.index')->with('success', 'Opportunity deleted.');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = max(1, min($request->integer('per_page', 10), 100));
        $sort = $request->query('sort', 'created_at');
        $direction = $request->query('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortable = ['name', 'description', 'created_at', 'updated_at', 'start_date'];
        if (!in_array($sort, $sortable, true)) {
            $sort = 'created_at';
        }

        $opportunities = Opportunity::with(['organizations', 'tags'])
            ->when($user && !$user->isAdmin() && $user->hasRole(Role::ORGANIZATION_MANAGER), function ($query) use ($user) {
                $orgIds = $this->manageableOrganizationIds($user);
                $query->whereHas('organizations', fn ($q) => $q->whereIn('organizations.id', $orgIds));
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

        if (ApiController::isApiRequest($request)) {
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
        $this->assertOpportunityAccess($request->user(), $opportunity);
        $resource = new OpportunityResource($opportunity);

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return BrowserResponse::render('admin/opportunities/OpportunityForm', [
            'opportunity' => $resource->resolve(),
        ]);
    }

    public function attachOrganization(Request $request, $opportunityId, $organizationId)
    {
        $opportunity = Opportunity::findOrFail($opportunityId);
        $this->assertOpportunityAccess($request->user(), $opportunity, allowUnassigned: true);
        $this->assertOrganizationAccess($request->user(), [$organizationId]);
        $opportunity->organizations()->syncWithoutDetaching([$organizationId]);

        $resource = new OpportunityResource($opportunity->load('organizations'));

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return redirect()->back()->with('success', 'Organization added.');
    }

    public function detachOrganization(Request $request, $opportunityId, $organizationId)
    {
        $opportunity = Opportunity::findOrFail($opportunityId);
        $this->assertOpportunityAccess($request->user(), $opportunity);
        $this->assertOrganizationAccess($request->user(), [$organizationId]);
        $opportunity->organizations()->detach($organizationId);

        $resource = new OpportunityResource($opportunity->load('organizations'));

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return redirect()->back()->with('success', 'Organization removed.');
    }

    public function addTag(Request $request, $opportunityId)
    {
        $opportunity = Opportunity::findOrFail($opportunityId);
        $this->assertOpportunityAccess($request->user(), $opportunity);
        $validated = $request->validate([
            'tag_name' => 'required|string|max:255',
        ]);

        $tag = Tag::firstOrCreate(['name' => trim($validated['tag_name'])]);
        $opportunity->tags()->syncWithoutDetaching([$tag->id]);

        $resource = new OpportunityResource($opportunity->load(['organizations', 'tags']));

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return redirect()->back()->with('success', 'Tag added.');
    }

    public function removeTag(Request $request, $opportunityId)
    {
        $opportunity = Opportunity::findOrFail($opportunityId);
        $this->assertOpportunityAccess($request->user(), $opportunity);
        $validated = $request->validate([
            'tag_name' => 'required|string|max:255',
        ]);

        $tag = Tag::where('name', trim($validated['tag_name']))->first();
        if ($tag) {
            $opportunity->tags()->detach($tag->id);
        }

        $resource = new OpportunityResource($opportunity->load(['organizations', 'tags']));

        if (ApiController::isApiRequest($request)) {
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
            if ($user && $user->hasRole(Role::ORGANIZATION_MANAGER)) {
                abort(403, 'Organization managers must select at least one organization.');
            }
            return;
        }

        if (!$user) {
            abort(403, 'Unauthorized.');
        }

        if ($user->isAdmin()) {
            return;
        }

        $manageableIds = $this->manageableOrganizationIds($user);
        $invalidIds = array_diff($organizationIds, $manageableIds);

        if (!empty($invalidIds) || !$user->hasRole(Role::ORGANIZATION_MANAGER)) {
            abort(403, 'Unauthorized.');
        }
    }

    /**
     * Ensure the current user can manage the provided opportunity.
     */
    private function assertOpportunityAccess(?User $user, Opportunity $opportunity, bool $allowUnassigned = false): void
    {
        if (!$user) {
            abort(403, 'Unauthorized.');
        }

        if ($user->isAdmin()) {
            return;
        }

        if (!$user->hasRole(Role::ORGANIZATION_MANAGER)) {
            abort(403, 'Unauthorized.');
        }

        $orgIds = $this->manageableOrganizationIds($user);
        $hasOrg = $opportunity->organizations()->whereIn('organizations.id', $orgIds)->exists();

        if (!$hasOrg && !($allowUnassigned && $opportunity->organizations()->count() === 0)) {
            abort(403, 'Unauthorized.');
        }
    }

    private function manageableOrganizationIds(User $user): array
    {
        return $user->organizations()->pluck('organizations.id')->all();
    }
}
