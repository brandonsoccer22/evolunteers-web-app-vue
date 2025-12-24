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
            'url' => $this->url,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'organizations' => OrganizationResource::collection($this->whenLoaded('organizations'))->resolve(),
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                ])->values();
            }),
            // Add other safe fields here
            // Do NOT include sensitive fields
        ];
    }
}
