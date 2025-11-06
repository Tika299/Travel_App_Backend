<?php

namespace App\Imports;

use App\Models\CheckinPlace;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\Importable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class CheckinPlacesImport implements ToModel, WithHeadingRow, WithValidation
{
    use Importable;

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Bỏ qua hàng nếu không có tên địa điểm
        if (!isset($row['name']) || empty($row['name'])) {
            return null;
        }

        // --- Xử lý tải ảnh đại diện (trường `image`) ---
        $imagePath = $this->downloadAndSaveImage($row['image'] ?? null, 'checkin_places');

        // --- Xử lý tải danh sách ảnh (trường `images`) ---
        $imagesPaths = [];
        // Giải mã chuỗi JSON từ file Excel thành mảng PHP
        $imagesUrls = json_decode($row['images'] ?? '[]', true);

        if (is_array($imagesUrls)) {
            foreach ($imagesUrls as $url) {
                if ($url) {
                    $path = $this->downloadAndSaveImage($url, 'checkin_places');
                    if ($path) {
                        $imagesPaths[] = $path;
                    }
                }
            }
        }

        // Xử lý các trường JSON và boolean từ file Excel một cách an toàn
        $operatingHours = json_decode($row['operating_hours'] ?? '[]', true) ?? [];
        $transportOptions = json_decode($row['transport_options'] ?? '[]', true) ?? [];
        $isFree = (bool) filter_var($row['is_free'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Tạo một instance mới của CheckinPlace và gán dữ liệu
        return new CheckinPlace([
            'name'              => $row['name'],
            'description'       => $row['description'] ?? null,
            'address'           => $row['address'] ?? null,
            'latitude'          => $row['latitude'] ?? null,
            'longitude'         => $row['longitude'] ?? null,
            'image'             => $imagePath, // Đường dẫn ảnh đã tải về
            'location_id'       => $row['location_id'] ?? null,
            'price'             => $row['price'] ?? null,
            'is_free'           => $isFree,
            'operating_hours'   => $operatingHours,
            'images'            => $imagesPaths, // Danh sách đường dẫn ảnh đã tải về
            'region'            => $row['region'] ?? null,
            'caption'           => $row['caption'] ?? null,
            'transport_options' => $transportOptions,
            'status'            => $row['status'] ?? 'active',
        ]);
    }

    /**
     * Tải và lưu ảnh từ một URL.
     *
     * @param string|null $url
     * @param string $path
     * @return string|null
     */
    protected function downloadAndSaveImage(?string $url, string $path)
    {
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        try {
            // Tải nội dung của file ảnh từ URL
            $contents = @file_get_contents($url);
            
            if ($contents !== false) {
                // Lấy thông tin header để xác định Content-Type
                $headers = get_headers($url, 1);
                $contentType = is_array($headers['Content-Type'] ?? null) ? $headers['Content-Type'][0] : ($headers['Content-Type'] ?? '');
                
                // Lấy phần mở rộng file
                $extension = 'png';
                if (str_contains($contentType, 'image/jpeg')) {
                    $extension = 'jpg';
                } elseif (str_contains($contentType, 'image/png')) {
                    $extension = 'png';
                } elseif (str_contains($contentType, 'image/gif')) {
                    $extension = 'gif';
                } elseif (str_contains($contentType, 'image/svg+xml')) {
                    $extension = 'svg';
                }
                
                $filename = $path . '/' . Str::random(40) . '.' . $extension;
                Storage::disk('public')->put($filename, $contents);
                
                return $filename;
            }
        } catch (Exception $e) {
             // Có lỗi khi download, trả về null
        }
        
        return null;
    }

    /**
     * Định nghĩa các quy tắc validation cho từng dòng.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'address'           => 'nullable|string',
            'latitude'          => 'nullable|numeric',
            'longitude'         => 'nullable|numeric',
            'image'             => 'nullable|string', // Chấp nhận URL là string
            'location_id'       => 'nullable|integer|exists:locations,id',
            'price'             => 'nullable|numeric|min:0',
            'is_free'           => 'nullable|boolean',
            'operating_hours'   => 'nullable|json',
            'images'            => 'nullable|json',
            'region'            => 'nullable|string',
            'caption'           => 'nullable|string',
            'transport_options' => 'nullable|json',
            'status'            => 'nullable|in:active,inactive,draft',
        ];
    }
}