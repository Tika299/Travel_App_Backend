<?php

namespace App\Imports;

use App\Models\Cuisine;
use App\Models\Category;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithBatchInserts;

class CuisineImport implements ToModel, WithHeadingRow, SkipsEmptyRows, SkipsOnFailure, WithBatchInserts
{
    public function model(array $row)
    {
        try {
            if (empty(trim($row['name'] ?? ''))) {
                Log::warning('Bỏ qua dòng không có tên món ăn: ' . json_encode($row));
                return null;
            }

            // Xử lý category_id
            $categoryId = $this->processCategory($row['category_name'] ?? $row['categories_id'] ?? null);

            $imagePath = $this->handleImage($row['image'] ?? null);

            $cuisineData = [
                'categories_id' => $categoryId,
                'name' => trim($row['name']),
                'image' => $imagePath,
                'short_description' => $this->cleanDescription($row['short_description'] ?? $row['description'] ?? ''),
                'detailed_description' => $this->cleanDescription($row['detailed_description'] ?? null),
                'region' => $this->validateRegion($row['region'] ?? null),
                'price' => $this->parsePrice($row['price'] ?? 0),
                'address' => trim($row['address']),
                'serving_time' => trim($row['serving_time'] ?? ''),
                'delivery' => $this->parseBoolean($row['delivery'] ?? false),
                'operating_hours' => trim($row['operating_hours'] ?? $row['operating_hou'] ?? ''), // Fix typo
                'suitable_for' => trim($row['suitable_for'] ?? ''),
                'status' => $this->validateStatus($row['status'] ?? 'available'),
            ];



            return new Cuisine($cuisineData);
        } catch (\Exception $e) {
            Log::error('Lỗi trong model(): ' . $e->getMessage() . ' - Row: ' . json_encode($row));
            throw $e;
        }
    }



    public function batchSize(): int
    {
        return 50; // Giảm batch size để xử lý nhanh hơn
    }

    public function onFailure(\Maatwebsite\Excel\Validators\Failure ...$failures)
    {
        foreach ($failures as $failure) {
            Log::error('Import failure: ' . $failure->exception()->getMessage());
        }
    }

    protected function processCategory($categoryInput)
    {
        if (!$categoryInput) {
            // Tạo category mặc định nếu không có
            $defaultCategory = Category::where('name', 'Món ăn khác')->first();
            if (!$defaultCategory) {
                $defaultCategory = Category::create([
                    'name' => 'Món ăn khác',
                    'icon' => null,
                    'type' => 'food'
                ]);
            }
            return $defaultCategory->id;
        }

        // Nếu là ID
        if (is_numeric($categoryInput)) {
            $category = Category::find($categoryInput);
            if ($category) {
                return $category->id;
            }
            
            // Nếu ID không tồn tại, tạo category mới với tên "Category {ID}"
            $category = Category::create([
                'name' => 'Category ' . $categoryInput,
                'icon' => null,
                'type' => 'food'
            ]);
            return $category->id;
        }

        // Nếu là tên category
        $category = Category::where('name', 'like', '%' . trim($categoryInput) . '%')->first();
        if ($category) {
            return $category->id;
        }

        // Tạo category mới nếu không tìm thấy
        $category = Category::create([
            'name' => trim($categoryInput),
            'icon' => null,
            'type' => 'food'
        ]);

        return $category->id;
    }

    protected function handleImage($image)
    {
        if (!$image) {
            return null;
        }

        $image = trim($image);
        if (!$image) {
            return null;
        }

        // Xử lý lỗi chính tả 'imgae' -> 'image'
        if (empty($image) || $image === 'imgae') {
            return null;
        }

        // Tạo thư mục nếu chưa có
        $uploadDir = public_path('storage/uploads/cuisine');
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Xử lý URL Google Drive - Tải ảnh thật từ Google Drive
        if (preg_match('/drive\.google\.com\/file\/d\/(.+?)\/view/', $image, $matches)) {
            $fileId = $matches[1];
            
            // Thử các cách khác nhau để tải ảnh từ Google Drive
            $googleDriveUrls = [
                "https://drive.google.com/uc?export=view&id={$fileId}",
                "https://drive.google.com/uc?export=download&id={$fileId}",
                "https://drive.google.com/thumbnail?id={$fileId}&sz=w400",
                "https://docs.google.com/uc?id={$fileId}",
            ];
            
            try {
                // Tạo thư mục nếu chưa có
                $uploadDir = public_path('storage/uploads/cuisine');
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Thử từng URL cho đến khi thành công
                foreach ($googleDriveUrls as $url) {
                    $context = stream_context_create([
                        'http' => [
                            'method' => 'GET',
                            'header' => [
                                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                            ]
                        ]
                    ]);
                    
                    $imageContent = file_get_contents($url, false, $context);
                    if ($imageContent !== false && strlen($imageContent) > 1000) {
                        // Kiểm tra xem có phải là ảnh thật không (không phải trang lỗi)
                        if (strpos($imageContent, 'JFIF') !== false || strpos($imageContent, 'PNG') !== false || strpos($imageContent, 'GIF') !== false) {
                            $imageName = time() . '_' . uniqid() . '.jpg';
                            $destinationPath = 'storage/uploads/cuisine/' . $imageName;
                            file_put_contents(public_path($destinationPath), $imageContent);
                            Log::info('Tải ảnh Google Drive thành công: ' . $url);
                            return $destinationPath;
                        }
                    }
                }
                
                Log::warning('Không thể tải ảnh từ Google Drive: ' . $image);
            } catch (\Exception $e) {
                Log::error('Lỗi tải ảnh Google Drive: ' . $e->getMessage());
            }
        }

        // Xử lý đường dẫn cục bộ
        if (file_exists(public_path($image))) {
            $imageName = time() . '_' . uniqid() . '.' . pathinfo($image, PATHINFO_EXTENSION);
            $destinationPath = 'storage/uploads/cuisine/' . $imageName;
            copy(public_path($image), public_path($destinationPath));
            return $destinationPath;
        }

        // Xử lý URL trực tuyến - Tải về local để đảm bảo hiển thị
        if (filter_var($image, FILTER_VALIDATE_URL)) {
            try {
                $imageName = time() . '_' . uniqid() . '.jpg';
                $destinationPath = 'storage/uploads/cuisine/' . $imageName;
                
                // Tạo thư mục nếu chưa có
                $uploadDir = public_path('storage/uploads/cuisine');
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Tải ảnh về local
                $imageContent = file_get_contents($image);
                if ($imageContent !== false) {
                    file_put_contents(public_path($destinationPath), $imageContent);
                    return $destinationPath;
                }
            } catch (\Exception $e) {
                Log::error('Lỗi tải hình ảnh từ URL: ' . $e->getMessage());
                // Trả về URL gốc nếu tải thất bại
                return $image;
            }
        }

        // Nếu không xử lý được, sử dụng ảnh mặc định
        return 'storage/uploads/cuisine/default-food.gif';
    }

    protected function validateRegion($region)
    {
        $validRegions = ['Miền Bắc', 'Miền Trung', 'Miền Nam'];
        return in_array($region, $validRegions) ? $region : null;
    }

    protected function validateStatus($status)
    {
        $validStatuses = ['available', 'unavailable'];
        return in_array($status, $validStatuses) ? $status : 'available';
    }

    protected function parseBoolean($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['true', '1', 'yes', 'có', 'co', 'true']);
        }

        return (bool) $value;
    }

    protected function parsePrice($value)
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            // Loại bỏ các ký tự không phải số
            $cleaned = preg_replace('/[^0-9]/', '', $value);
            return $cleaned ? (int) $cleaned : 0;
        }

        return 0;
    }

    protected function cleanDescription($value)
    {
        if (!$value) {
            return '';
        }

        // Loại bỏ khoảng trắng thừa và ký tự đặc biệt không mong muốn
        $cleaned = trim($value);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned); // Thay nhiều khoảng trắng thành 1
        
        return $cleaned;
    }
}
