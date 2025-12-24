<?php
// filepath: app/Http/Resources/OrganizationResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray($request)
    {

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'opportunities' => OpportunityResource::collection($this->whenLoaded('opportunities')),
            'users' => $this->whenLoaded('users', function () {
                return UserSummaryResource::collection($this->users)->resolve();
            }, []),
            // Add other safe fields here
            // Do NOT include sensitive fields
        ];
    }
}
