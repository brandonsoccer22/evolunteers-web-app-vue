<?php
// filepath: app/Http/Resources/OpportunityResource.php
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
            // Add other safe fields here
            // Do NOT include sensitive fields
        ];
    }
}
