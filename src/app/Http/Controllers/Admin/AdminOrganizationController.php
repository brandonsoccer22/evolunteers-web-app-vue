<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizationUpsertRequest;
use App\Http\Resources\OrganizationResource;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\BrowserResponse;
use App\Models\Organization;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;

class AdminOrganizationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Organization::with('users');

        if ($search = $request->query('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        if ($user && !$user->isAdmin()) {
            $query->whereHas('users', fn ($q) => $q->where('users.id', $user->id));
        }

        $organizations = $query->orderBy('name')->limit(50)->get();
        $resource = OrganizationResource::collection($organizations);

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return BrowserResponse::render('admin/organizations/OrganizationsIndex', [
            'organizations' => $resource->resolve(),
        ]);
    }

    public function showCreate(Request $request)
    {
        if (!$request->user()?->isAdmin()) {
            abort(403, 'Unauthorized.');
        }

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::error('Use POST /admin/organizations to create.', 405);
        }

        return BrowserResponse::render('admin/organizations/OrganizationForm', [
            'organization' => null,
        ]);
    }

    public function show(Request $request, $id)
    {
        $organization = Organization::with('users')->findOrFail($id);
        $this->ensureCanAccessOrganization($request->user(), $organization);
        $resource = new OrganizationResource($organization);

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return BrowserResponse::render('admin/organizations/OrganizationForm', [
            'organization' => $resource->resolve(),
        ]);
    }

    public function store(OrganizationUpsertRequest $request)
    {
        if (!$request->user()?->isAdmin()) {
            abort(403, 'Unauthorized.');
        }

        $data = $request->validated();
        $userIds = $data['user_ids'] ?? [];
        unset($data['user_ids']);

        $organization = Organization::create($data);

        if (!empty($userIds)) {
            $organization->users()->sync($userIds);
        }

        $resource = new OrganizationResource($organization->load('users'));

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return ApiResponse::model($resource);
    }

    public function update(OrganizationUpsertRequest $request, $id)
    {
        $organization = Organization::findOrFail($id);
        $user = $request->user();
        $this->ensureCanAccessOrganization($user, $organization);

        $data = $request->validated();
        $userIds = $data['user_ids'] ?? null;

        if (!$user?->isAdmin()) {
            // Organization managers can update org details but not membership
            $userIds = null;
        }

        unset($data['user_ids']);

        $organization->update($data);

        if (is_array($userIds)) {
            $organization->users()->sync($userIds);
        }

        $resource = new OrganizationResource($organization->load('users'));

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return ApiResponse::model($resource);
    }

    public function destroy(Request $request, $id)
    {
        if (!$request->user()?->isAdmin()) {
            abort(403, 'Unauthorized.');
        }

        $organization = Organization::findOrFail($id);
        $organization->delete();

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::success('Organization deleted.');
        }

        return ApiResponse::success('Organization deleted.');
    }

    public function attachUser(Request $request, $organizationId, $userId)
    {
        if (!$request->user()?->isAdmin()) {
            abort(403, 'Unauthorized.');
        }

        $organization = Organization::findOrFail($organizationId);
        $user = User::findOrFail($userId);

        $organization->users()->syncWithoutDetaching([$user->id]);

        $resource = new OrganizationResource($organization->load('users'));

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return ApiResponse::model($resource);
    }

    public function detachUser(Request $request, $organizationId, $userId)
    {
        if (!$request->user()?->isAdmin()) {
            abort(403, 'Unauthorized.');
        }

        $organization = Organization::findOrFail($organizationId);
        $user = User::findOrFail($userId);

        $organization->users()->detach($user->id);

        $resource = new OrganizationResource($organization->load('users'));

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return ApiResponse::model($resource);
    }

    protected function ensureCanAccessOrganization(?User $user, Organization $organization): void
    {
        if (!$user) {
            abort(403, 'Unauthorized.');
        }

        if ($user->isAdmin()) {
            return;
        }

        $isManagerOfOrg = $user->hasRole(Role::ORGANIZATION_MANAGER)
            && $organization->users()->where('users.id', $user->id)->exists();

        if (!$isManagerOfOrg) {
            abort(403, 'Unauthorized.');
        }
    }
}
