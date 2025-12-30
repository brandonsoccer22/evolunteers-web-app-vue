<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\ApiController;
use App\Http\Requests\OrganizationUpsertRequest;
use App\Http\Resources\OrganizationResource;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\BrowserResponse;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;

class AdminOrganizationController extends ApiController
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user?->can('viewAny', Organization::class)) {
            abort(403, 'Unauthorized.');
        }

        $query = Organization::with('users');
        if (!$user->can('viewAll', Organization::class)) {
            $query->visibleToUser($user);
        }

        if ($search = $request->query('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $organizations = $query->orderBy('name')->limit(50)->get();
        $resource = OrganizationResource::collection($organizations);

        if (static::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return BrowserResponse::render('admin/organizations/OrganizationsIndex', [
            'organizations' => $resource->resolve(),
        ]);
    }

    public function showCreate(Request $request)
    {
        if (!$request->user()?->can('create', Organization::class)) {
            abort(403, 'Unauthorized.');
        }

        if (static::isApiRequest($request)) {
            return ApiResponse::error('Use POST /admin/organizations to create.', 405);
        }

        return BrowserResponse::render('admin/organizations/OrganizationForm', [
            'organization' => null,
        ]);
    }

    public function show(Request $request, $id)
    {
        $organization = Organization::with('users')->findOrFail($id);
        if (!$request->user()?->can('view', $organization)) {
            abort(403, 'Unauthorized.');
        }
        $resource = new OrganizationResource($organization);

        if (static::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return BrowserResponse::render('admin/organizations/OrganizationForm', [
            'organization' => $resource->resolve(),
        ]);
    }

    public function store(OrganizationUpsertRequest $request)
    {
        if (!$request->user()?->can('create', Organization::class)) {
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

        if (static::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return ApiResponse::model($resource);
    }

    public function update(OrganizationUpsertRequest $request, $id)
    {
        $organization = Organization::findOrFail($id);
        $user = $request->user();
        if (!$user?->can('update', $organization)) {
            abort(403, 'Unauthorized.');
        }

        $data = $request->validated();
        $userIds = $data['user_ids'] ?? null;

        if (!$user->can('viewAll', Organization::class)) {
            // Organization managers can update org details but not membership
            $userIds = null;
        }

        unset($data['user_ids']);

        $organization->update($data);

        if (is_array($userIds)) {
            $organization->users()->sync($userIds);
        }

        $resource = new OrganizationResource($organization->load('users'));

        if (static::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return ApiResponse::model($resource);
    }

    public function destroy(Request $request, $id)
    {
        $organization = Organization::findOrFail($id);
        if (!$request->user()?->can('delete', $organization)) {
            abort(403, 'Unauthorized.');
        }
        $organization->delete();

        if (static::isApiRequest($request)) {
            return ApiResponse::success('Organization deleted.');
        }

        return ApiResponse::success('Organization deleted.');
    }

    public function attachUser(Request $request, $organizationId, $userId)
    {
        $organization = Organization::findOrFail($organizationId);
        if (!$request->user()?->can('update', $organization)) {
            abort(403, 'Unauthorized.');
        }
        $user = User::findOrFail($userId);

        $organization->users()->syncWithoutDetaching([$user->id]);

        $resource = new OrganizationResource($organization->load('users'));

        if (static::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return ApiResponse::model($resource);
    }

    public function detachUser(Request $request, $organizationId, $userId)
    {
        $organization = Organization::findOrFail($organizationId);
        if (!$request->user()?->can('update', $organization)) {
            abort(403, 'Unauthorized.');
        }
        $user = User::findOrFail($userId);

        $organization->users()->detach($user->id);

        $resource = new OrganizationResource($organization->load('users'));

        if (static::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return ApiResponse::model($resource);
    }

}
