<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiController;
use App\Http\Requests\UserUpsertRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\BrowserResponse;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use App\Mail\UserPasswordResetMail;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with(['organizations', 'roles'])->get();
        $resource = UserResource::collection($users);

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return BrowserResponse::render('admin/users/UsersIndex', [
            'users' => $resource->resolve(),
        ]);
    }

    public function createForm(Request $request)
    {
        if (ApiController::isApiRequest($request)) {
            return ApiResponse::error('Use POST /admin/users to create.', 405);
        }

        return BrowserResponse::render('admin/users/UserForm', [
            'availableRoles' => $this->availableRoles(),
        ]);
    }

    public function show(Request $request, $id)
    {
        $user = User::with(['organizations', 'roles'])->findOrFail($id);

        $resource = new UserResource($user);

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return BrowserResponse::render('admin/users/UserForm', [
            'user' => $resource->resolve(),
            'availableRoles' => $this->availableRoles(),
        ]);
    }

    public function store(UserUpsertRequest $request)
    {
        $data = $request->validated();
        $organizationIds = $data['organization_ids'] ?? [];
        $roleNames = $data['roles'] ?? [];
        unset($data['organization_ids']);
        unset($data['roles']);

        $generatedPassword = null;
        if (empty($data['password'])) {
            $generatedPassword = Str::uuid()->toString();
            $data['password'] = $generatedPassword;
        }

        $user = User::create($data);

        if (!empty($organizationIds)) {
            $user->organizations()->sync($organizationIds);
        }

        $roleIds = $this->roleIdsFromNames($roleNames);
        if (empty($roleIds)) {
            $defaultRoleId = $this->defaultRoleId();
            if ($defaultRoleId) {
                $roleIds[] = $defaultRoleId;
            }
        }
        if (!empty($roleIds)) {
            $user->roles()->sync($roleIds);
        }

        if ($generatedPassword) {
            $token = Password::createToken($user);
            Mail::to($user->email)->send(new UserPasswordResetMail($user, $token));
        }

        $resource = new UserResource($user->load(['organizations', 'roles']));

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return redirect()->route('admin.users.show', ['user' => $user]);
    }

    public function update(UserUpsertRequest $request, $id)
    {
        $user = User::findOrFail($id);
        $data = $request->validated();
        $organizationIds = $data['organization_ids'] ?? null;
        $roleNames = $data['roles'] ?? null;
        unset($data['organization_ids']);
        unset($data['roles']);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        if (is_array($organizationIds)) {
            $user->organizations()->sync($organizationIds);
        }

        if (is_array($roleNames)) {
            $roleIds = $this->roleIdsFromNames($roleNames);
            $user->roles()->sync($roleIds);
        }

        $resource = new UserResource($user->load(['organizations', 'roles']));

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return redirect()->route('admin.users.show', ['user' => $user]);
    }

    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::success('User deleted.');
        }

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }

    public function attachOrganization(Request $request, $userId, $organizationId)
    {
        $user = User::findOrFail($userId);
        $organization = Organization::findOrFail($organizationId);

        $user->organizations()->syncWithoutDetaching([$organization->id]);

        $resource = new UserResource($user->load('organizations'));

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return redirect()->back()->with('success', 'Organization added.');
    }

    public function detachOrganization(Request $request, $userId, $organizationId)
    {
        $user = User::findOrFail($userId);
        $organization = Organization::findOrFail($organizationId);

        $user->organizations()->detach($organization->id);

        $resource = new UserResource($user->load('organizations'));

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return redirect()->back()->with('success', 'Organization removed.');
    }

    private function availableRoles()
    {
        return Role::orderBy('name')->pluck('name')->values();
    }

    private function roleIdsFromNames(array $names): array
    {
        if (empty($names)) {
            return [];
        }

        return Role::whereIn('name', $names)->pluck('id')->all();
    }

    private function defaultRoleId(): ?int
    {
        return Role::where('name', Role::USER)->value('id');
    }
}
