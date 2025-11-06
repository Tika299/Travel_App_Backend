<?php

namespace App\Imports;

use App\Models\Category;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;
use Illuminate\Support\Facades\Log;

class CategoryImport implements ToModel, WithHeadingRow, SkipsEmptyRows, SkipsOnFailure, WithBatchInserts, WithValidation
{
    private $errors = [];
    private $imported = 0;
    private $skipped = 0;

    public function __construct()
    {
        Log::info('CategoryImport: Constructor called');
    }

    public function model(array $row)
    {
        Log::info('CategoryImport: model() method called with row', $row);
        
        try {
            // Debug: Log dữ liệu đầu vào
            Log::info('Category import: Processing row', $row);
            
            // Debug: Kiểm tra tên cột
            Log::info('Category import: Available columns', array_keys($row));
            
            // Chuẩn hóa dữ liệu
            $name = trim($row['name'] ?? '');
            $icon = trim($row['icon'] ?? '');
            $type = trim($row['type'] ?? 'food');

            Log::info('Category import: Processed data', [
                'name' => $name,
                'icon' => $icon,
                'type' => $type,
                'name_length' => strlen($name),
                'name_empty' => empty($name)
            ]);

            // Kiểm tra dữ liệu bắt buộc
            if (empty($name)) {
                $this->skipped++;
                Log::warning('Category import: Bỏ qua dòng vì thiếu tên', $row);
                return null;
            }

            // Kiểm tra xem category đã tồn tại chưa
            $existingCategory = Category::where('name', $name)->first();
            if ($existingCategory) {
                $this->skipped++;
                Log::info('Category import: Bỏ qua category đã tồn tại', [
                    'name' => $name,
                    'existing_id' => $existingCategory->id
                ]);
                return null;
            }

            // Xử lý icon từ Google Drive hoặc URL khác
            $processedIcon = $this->processIcon($icon, $name);

            // Tạo category mới (cho phép icon null)
            $category = new Category([
                'name' => $name,
                'icon' => $processedIcon, // Có thể là null nếu không xử lý được ảnh
                'type' => $type ?: 'food',
            ]);

            $this->imported++;
            Log::info('Category import: Tạo category mới thành công', [
                'name' => $name,
                'icon' => $processedIcon,
                'imported_count' => $this->imported
            ]);
            
            return $category;

        } catch (\Exception $e) {
            $this->skipped++;
            Log::error('Category import: Lỗi xử lý dòng', [
                'row' => $row,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Xử lý icon từ Google Drive hoặc URL khác
     */
    private function processIcon($iconUrl, $categoryName)
    {
        if (empty($iconUrl)) {
            return null;
        }

        $iconUrl = trim($iconUrl);
        if (!$iconUrl) {
            return null;
        }

        // Kiểm tra URL Google Drive không hợp lệ
        if (strpos($iconUrl, 'drive.google.com') !== false) {
            // Nếu URL chỉ là https://drive.google.com/ hoặc https://drive.google.com thì bỏ qua
            if (trim($iconUrl) === 'https://drive.google.com/' || trim($iconUrl) === 'https://drive.google.com') {
                Log::warning('Category import: URL Google Drive không hợp lệ (thiếu file ID): ' . $iconUrl);
                return null;
            }
            
            // Thử xử lý Google Drive URL
            $result = $this->downloadGoogleDriveImage($iconUrl, $categoryName);
            if ($result) {
                return $result;
            }
        }

        // Tạo thư mục nếu chưa có
        $uploadDir = public_path('storage/uploads/category_icons');
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Xử lý đường dẫn cục bộ
        if (file_exists(public_path($iconUrl))) {
            $imageName = time() . '_' . uniqid() . '.' . pathinfo($iconUrl, PATHINFO_EXTENSION);
            $destinationPath = 'storage/uploads/category_icons/' . $imageName;
            copy(public_path($iconUrl), public_path($destinationPath));
            return $destinationPath;
        }

        // Xử lý URL trực tuyến - Tải về local để đảm bảo hiển thị
        if (filter_var($iconUrl, FILTER_VALIDATE_URL)) {
            try {
                $context = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'header' => [
                            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                        ]
                    ]
                ]);
                
                $imageContent = file_get_contents($iconUrl, false, $context);
                if ($imageContent !== false && strlen($imageContent) > 1000) {
                    // Xác định định dạng ảnh từ content
                    $extension = $this->detectImageFormat($imageContent);
                    $imageName = time() . '_' . uniqid() . '.' . $extension;
                    $destinationPath = 'storage/uploads/category_icons/' . $imageName;
                    file_put_contents(public_path($destinationPath), $imageContent);
                    Log::info('Category import: Tải ảnh URL thành công: ' . $iconUrl . ' (format: ' . $extension . ')');
                    return $destinationPath;
                }
            } catch (\Exception $e) {
                Log::error('Category import: Lỗi tải ảnh URL: ' . $e->getMessage());
            }
        }

        // Nếu không xử lý được, trả về null (không lỗi, chỉ không có ảnh)
        Log::info('Category import: Không thể xử lý icon, sẽ tạo category không có ảnh: ' . $iconUrl);
        return null;
    }

    /**
     * Tải ảnh từ Google Drive với nhiều format URL khác nhau
     */
    private function downloadGoogleDriveImage($url, $categoryName)
    {
        try {
            $fileId = null;
            
            // Thử các pattern khác nhau để lấy file ID
            $patterns = [
                '/drive\.google\.com\/file\/d\/(.+?)\/view/',
                '/drive\.google\.com\/open\?id=(.+?)(&|$)/',
                '/drive\.google\.com\/uc\?id=(.+?)(&|$)/',
                '/drive\.google\.com\/uc\?export=download&id=(.+?)(&|$)/',
            ];
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $url, $matches)) {
                    $fileId = $matches[1];
                    break;
                }
            }
            
            // Nếu không tìm thấy file ID, thử xử lý URL ngắn
            if (!$fileId && strpos($url, 'drive.google.com') !== false) {
                // Nếu URL chỉ là https://drive.google.com/ thì bỏ qua
                if (trim($url) === 'https://drive.google.com/' || trim($url) === 'https://drive.google.com') {
                    Log::warning('Category import: URL Google Drive không hợp lệ (thiếu file ID): ' . $url);
                    return null;
                }
                
                // Thử các cách khác để lấy file ID
                $fileId = $this->extractFileIdFromUrl($url);
            }
            
            if (!$fileId) {
                Log::warning('Category import: Không thể trích xuất file ID từ URL: ' . $url);
                return null;
            }
            
            // Thử các cách khác nhau để tải ảnh từ Google Drive
            $googleDriveUrls = [
                "https://drive.google.com/uc?export=view&id={$fileId}",
                "https://drive.google.com/uc?export=download&id={$fileId}",
                "https://drive.google.com/thumbnail?id={$fileId}&sz=w400",
                "https://docs.google.com/uc?id={$fileId}",
                "https://drive.google.com/uc?id={$fileId}&export=download",
            ];
            
            // Thử từng URL cho đến khi thành công
            foreach ($googleDriveUrls as $downloadUrl) {
                try {
                    $context = stream_context_create([
                        'http' => [
                            'method' => 'GET',
                            'header' => [
                                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                            ],
                            'timeout' => 30
                        ]
                    ]);
                    
                    $imageContent = file_get_contents($downloadUrl, false, $context);
                    if ($imageContent !== false && strlen($imageContent) > 1000) {
                        // Kiểm tra xem có phải là ảnh thật không (không phải trang lỗi)
                        if (strpos($imageContent, 'JFIF') !== false || strpos($imageContent, 'PNG') !== false || strpos($imageContent, 'GIF') !== false || strpos($imageContent, 'JPEG') !== false) {
                            // Xác định định dạng ảnh từ content
                            $extension = $this->detectImageFormat($imageContent);
                            $imageName = time() . '_' . uniqid() . '.' . $extension;
                            $destinationPath = 'storage/uploads/category_icons/' . $imageName;
                            file_put_contents(public_path($destinationPath), $imageContent);
                            Log::info('Category import: Tải ảnh Google Drive thành công: ' . $downloadUrl . ' (format: ' . $extension . ')');
                            return $destinationPath;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Category import: Thử URL Google Drive thất bại: ' . $downloadUrl . ' - ' . $e->getMessage());
                    continue;
                }
            }
            
            Log::warning('Category import: Không thể tải ảnh từ Google Drive: ' . $url);
            return null;
            
        } catch (\Exception $e) {
            Log::error('Category import: Lỗi tải ảnh Google Drive: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Phát hiện định dạng ảnh từ content
     */
    private function detectImageFormat($imageContent)
    {
        // Kiểm tra signature của các định dạng ảnh
        if (strpos($imageContent, "\x89PNG\r\n\x1a\n") === 0) {
            return 'png';
        }
        if (strpos($imageContent, "\xff\xd8\xff") === 0) {
            return 'jpg';
        }
        if (strpos($imageContent, "GIF87a") === 0 || strpos($imageContent, "GIF89a") === 0) {
            return 'gif';
        }
        if (strpos($imageContent, "BM") === 0) {
            return 'bmp';
        }
        if (strpos($imageContent, "RIFF") === 0 && strpos($imageContent, "WEBP", 8) === 8) {
            return 'webp';
        }
        
        // Mặc định là PNG nếu không xác định được (vì Google Drive thường là PNG)
        Log::info('Category import: Không xác định được định dạng ảnh, mặc định là PNG');
        return 'png';
    }

    /**
     * Trích xuất file ID từ URL Google Drive
     */
    private function extractFileIdFromUrl($url)
    {
        // Loại bỏ các tham số không cần thiết
        $url = preg_replace('/\?.*$/', '', $url);
        $url = rtrim($url, '/');
        
        // Thử các pattern khác
        $patterns = [
            '/\/d\/([a-zA-Z0-9_-]+)/',
            '/id=([a-zA-Z0-9_-]+)/',
            '/file\/d\/([a-zA-Z0-9_-]+)/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:50',
        ];
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function onFailure(Failure ...$failures)
    {
        foreach ($failures as $failure) {
            $this->errors[] = [
                'row' => $failure->row(),
                'attribute' => $failure->attribute(),
                'errors' => $failure->errors(),
                'values' => $failure->values(),
            ];
        }
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getImportedCount()
    {
        return $this->imported;
    }

    public function getSkippedCount()
    {
        return $this->skipped;
    }
}
