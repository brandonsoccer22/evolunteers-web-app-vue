<?php

namespace App\Http\Controllers;

use App\Http\Requests\OpportunityUpsertRequest;
use App\Models\Opportunity;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Resources\OpportunityResource;
use App\Http\Responses\ApiResponse;

class OpportunityController extends Controller
{

    public function create(Request $request)
    {
        if (ApiController::isApiRequest($request)) {
            return response()->json(['message' => 'Use POST /opportunities to create.'], 405);
        }
        return Inertia::render('Opportunities/Create');
    }

    public function store(OpportunityUpsertRequest $request)
    {

        $opportunity = Opportunity::create($request->validated());
        $resource = new OpportunityResource($opportunity);

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }
        return redirect()->route('opportunities.show', ['opportunity' => $opportunity])->with('success', 'Opportunity created.');
    }

    public function edit(Request $request, $id)
    {
        $opportunity = Opportunity::findOrFail($id);
        $resource = new OpportunityResource($opportunity);
        if (ApiController::isApiRequest($request)) {
            return ApiResponse::error('Use PUT/PATCH /opportunities/{id} to update.',405);
        }
        return Inertia::render('Opportunities/Edit', [
            'opportunity' => $resource,
        ]);
    }

    public function update(OpportunityUpsertRequest $request, $id)
    {
        $opportunity = Opportunity::findOrFail($id);
        $data = $request->validated();
        //$data = array_map(fn($param)=>urldecode($param), $data);
        $opportunity->update($data);
        $opportunity->save();

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::model(new OpportunityResource($opportunity));
        }
        return redirect()
            ->route('opportunities.show', ['opportunity' => $opportunity])
            ->with('success', 'Opportunity updated.');
    }

    public function destroy(Request $request, $id)
    {
        $opportunity = Opportunity::findOrFail($id);
        $opportunity->delete();

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::success('Opportunity deleted.');
        }

        return redirect()->route('opportunities.index')->with('success', 'Opportunity deleted.');
    }

    public function index(Request $request)
    {
        $opportunities = Opportunity::with('organizations')->get();
        $resource = OpportunityResource::collection($opportunities);

        if (ApiController::isApiRequest($request)) {
            return ApiResponse::model($resource);
        }

        return Inertia::render('Opportunities/Index', [
            'opportunities' => $resource,
        ]);
    }

    public function show(Request $request, $id)
    {
        $opportunity = Opportunity::with('organizations')->findOrFail($id);
        $resource = new OpportunityResource($opportunity);

        if (ApiController::isApiRequest($request)) {
             return ApiResponse::model($resource);
        }

        return Inertia::render('Opportunities/Show', [
            'opportunity' => $resource,
        ]);
    }
}
