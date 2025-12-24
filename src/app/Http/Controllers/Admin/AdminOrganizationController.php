<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Organization;
use Illuminate\Http\Request;

class AdminOrganizationController extends Controller
{
    public function index(Request $request)
    {
        $query = Organization::query();

        if ($search = $request->query('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $organizations = $query->orderBy('name')->limit(50)->get();
        $resource = OrganizationResource::collection($organizations);

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return ApiResponse::model($resource);
    }
}
