<?php

namespace App\Http\Controllers;

use App\Http\Requests\OpportunitySearchRequest;
use App\Services\OpportunitySearchService;
use App\Http\Responses\BrowserResponse;
use App\Http\Controllers\ApiController;
use App\Http\Resources\TagResource;
use App\Models\Tag;

class PublicOpportunityController extends Controller
{
    public function __construct(protected OpportunitySearchService $searchService)
    {
    }

    public function index(OpportunitySearchRequest $request)
    {
        $validated = $request->validated();
        $filters = $validated['filters'] ?? [];
        $perPage = max(1, min((int) ($validated['per_page'] ?? 12), 50));
        $page = max(1, (int) ($validated['page'] ?? 1));

        $results = $this->searchService->search($filters, $perPage, $page);

        if (ApiController::isApiRequest($request)) {
            return response()->json($results);
        }

        $tagResource = TagResource::collection(Tag::all());

        return BrowserResponse::render('opportunities/PublicDashboard', [
            'filters' => $filters,
            'opportunities' => $results['data'] ?? [],
            'tags'=>$tagResource->resolve(),
            'meta' => $results['meta'] ?? [],
        ]);
    }
}
