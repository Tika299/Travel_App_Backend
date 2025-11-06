<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'type' => $this->type,
            // Hiển thị số lượng món ăn nếu đã được đếm
            'cuisines_count' => $this->when(isset($this->cuisines_count), $this->cuisines_count),
        ];
    }
}