<?php
// filepath: app/Http/Resources/OpportunityResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'organizations' => OrganizationResource::collection($this->whenLoaded('organizations')),
            // Add other safe fields here
            // Do NOT include sensitive fields
        ];
    }
}
