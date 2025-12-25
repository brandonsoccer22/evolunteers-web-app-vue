<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'organizations' => $this->whenLoaded('organizations', function () {
                return OrganizationSummaryResource::collection($this->organizations)->resolve();
            }, []),
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->pluck('name')->values()->all();
            }, []),
        ];
    }
}
