<?php

namespace App\Imports;

use App\Models\TransportCompany;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\Importable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class TransportCompaniesImport implements ToModel, WithHeadingRow, WithValidation
{
    use Importable;

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        $logoPath = null;
        $logoUrl = $row['logo'] ?? null;

        // Kiểm tra nếu có URL ảnh và URL đó hợp lệ
        if ($logoUrl && filter_var($logoUrl, FILTER_VALIDATE_URL)) {
            try {
                // Tải nội dung của file ảnh từ URL bằng Guzzle HTTP Client
                // Hoặc đơn giản hơn là dùng file_get_contents
                $contents = @file_get_contents($logoUrl);

                if ($contents !== false) {
                    // Lấy thông tin header để xác định Content-Type
                    $headers = get_headers($logoUrl, 1);
                    $contentType = $headers['Content-Type'] ?? '';

                    $extension = 'png'; // Mặc định là png
                    if (is_array($contentType)) {
                         $contentType = $contentType[0];
                    }

                    // Xác định đuôi file dựa trên Content-Type
                    if (str_contains($contentType, 'image/jpeg')) {
                        $extension = 'jpg';
                    } elseif (str_contains($contentType, 'image/png')) {
                        $extension = 'png';
                    } elseif (str_contains($contentType, 'image/gif')) {
                        $extension = 'gif';
                    } elseif (str_contains($contentType, 'image/svg')) {
                        $extension = 'svg';
                    }

                $filename = 'logos/' . Str::random(40) . '.' . $extension;
Storage::disk('public')->put($filename, $contents);
$logoPath = 'storage/' . $filename; // <-- thêm storage/ ở đây

                }
            } catch (Exception $e) {
                // Nếu có lỗi khi tải ảnh, bỏ qua
                $logoPath = null;
            }
        } else {
            // Nếu không phải URL, giả định đây là đường dẫn đã được lưu trước đó
            $logoPath = $row['logo'] ?? null;
        }

        // Xử lý các trường JSON từ chuỗi trong file CSV một cách an toàn
        $operatingHours = json_decode($row['operating_hours'] ?? '[]', true) ?? [];
        $priceRange = json_decode($row['price_range'] ?? '[]', true) ?? [];
        $paymentMethods = json_decode($row['payment_methods'] ?? '[]', true) ?? [];

        // Xử lý trường boolean từ chuỗi một cách an toàn
        $hasMobileApp = (isset($row['has_mobile_app']) && (strtolower($row['has_mobile_app']) === 'true' || $row['has_mobile_app'] === '1'));

        // Tạo hoặc cập nhật bản ghi
        return new TransportCompany([
            'transportation_id' => $row['transportation_id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'address' => $row['address'],
            'latitude' => $row['latitude'],
            'longitude' => $row['longitude'],
            'logo' => $logoPath, // Sử dụng đường dẫn mới đã được tải về
            'operating_hours' => $operatingHours,
            'price_range' => $priceRange,
            'phone_number' => $row['phone_number'],
            'email' => $row['email'],
            'website' => $row['website'],
            'payment_methods' => $paymentMethods,
            'has_mobile_app' => $hasMobileApp,
            'status' => $row['status'],
        ]);
    }

    /**
     * Định nghĩa các quy tắc validation cho từng dòng trong file CSV.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'transportation_id' => 'required|integer|exists:transportations,id',
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'logo' => 'nullable|string',
            'operating_hours' => 'nullable|json',
            'price_range' => 'nullable|json',
            'payment_methods' => 'nullable|json',
            'phone_number' => 'nullable|string',
            'email' => 'nullable|email',
            'website' => 'nullable|url',
            'has_mobile_app' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive,draft',
        ];
    }
}
