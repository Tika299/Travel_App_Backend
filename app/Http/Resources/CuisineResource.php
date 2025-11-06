<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\CategoryResource;

class CuisineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'image' => $this->image,
            'short_description' => $this->short_description,
            'region' => $this->region,
            'price' => $this->price,
            'price_formatted' => number_format($this->price) . ' VNĐ',
            'address' => $this->address,
            'serving_time' => $this->serving_time,
            'delivery' => $this->delivery,
            'status' => $this->status,
            'created_at' => $this->created_at->toDateTimeString(),
            
            // Tải relationship nếu đã được load
            'category' => new CategoryResource($this->whenLoaded('category')),
        ];

        // Chỉ thêm các field chi tiết khi cần thiết (ví dụ: trang chi tiết)
        if ($request->routeIs('*.show') || $request->has('include_details')) {
            $data['detailed_description'] = $this->detailed_description;
            $data['operating_hours'] = $this->operating_hours;
            $data['suitable_for'] = $this->suitable_for;
        }

        return $data;
    }
}