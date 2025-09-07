<?php
// app/Http/Responses/ApiResponse.php
namespace App\Http\Responses;

use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;

class BrowserResponse
{
    public static function beforeResponse(): void {
        // Perform any actions needed before the response is sent
    }

    /**
     * @return \Inertia\Response | \Illuminate\Contracts\View\View
     */
    public static function render(string $view, mixed $data = null, array $meta = [])
    {
       static::beforeResponse();

       return Inertia::render($view, [
            'data' => $data,
            'meta' => $meta,
        ]);
    }
}
