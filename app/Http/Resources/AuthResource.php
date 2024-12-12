<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthResource extends JsonResource
{
    // define properti
    public $message;
    public $status;
    public $resource;
    public $token;

    public function __construct($status, $message, $token, $resource)
    {
        parent::__construct($resource);
        $this->status = $status;
        $this->message = $message;
        $this->token = $token;
    }

    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'data' => $this->resource,
            'token' => $this->token,
        ];
    }
}
