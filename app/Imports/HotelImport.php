<?php

namespace App\Imports;

use App\Models\Hotel;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;

class HotelImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, SkipsOnFailure, WithBatchInserts, WithEvents
{
    protected $imageUrls = [];
    protected $guzzleClient;
    protected $failedImages = [];

    public function __construct()
    {
        $this->guzzleClient = new Client([
            'timeout' => 20,
            'http_errors' => false,
        ]);
    }

    public function model(array $row)
    {
        $name = trim($row['name'] ?? '');
        if (empty($name)) {
            Log::info("Bỏ qua dòng không có tên khách sạn: " . json_encode($row));
            return null;
        }

        $latitude = isset($row['latitude']) ? (float) str_replace(',', '.', $row['latitude']) : null;
        $longitude = isset($row['longitude']) ? (float) str_replace(',', '.', $row['longitude']) : null;

        $hotel = new Hotel([
            'name' => $name,
            'description' => $row['description'] ?? null,
            'address' => $row['address'] ?? null,
            'images' => json_encode([]),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'website' => $row['website'] ?? null,
        ]);

        $hotel->save();
        if (!empty($row['images'])) {
            $this->imageUrls[] = [
                'hotel_id' => $hotel->id,
                'images' => $row['images'],
            ];
            Log::info("Thêm URL ảnh cho hotel_id {$hotel->id}: " . $row['images']);
        }

        return null;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'website' => 'nullable|url',
        ];
    }

    public function onFailure(\Maatwebsite\Excel\Validators\Failure ...$failures)
    {
        foreach ($failures as $failure) {
            Log::error("Lỗi import dòng {$failure->row()}: " . implode(', ', $failure->errors()));
        }
    }

    public function batchSize(): int
    {
        return 200;
    }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterImport::class => function () {
                Log::info("Sự kiện AfterImport được gọi cho HotelImport");
                $this->onFinish();
            },
        ];
    }

    public function onFinish()
    {
        set_time_limit(600);
        Log::info("Bắt đầu xử lý ảnh cho HotelImport. Số lượng bản ghi: " . count($this->imageUrls));
        DB::transaction(function () {
            $this->processImages();
        });
        Log::info("Hoàn tất xử lý ảnh cho HotelImport. Lỗi: " . json_encode($this->failedImages));
        return [
            'failed_images' => $this->failedImages,
        ];
    }

    protected function processImages()
    {
        if (empty($this->imageUrls)) {
            Log::info("Không có URL ảnh nào để xử lý trong HotelImport.");
            return;
        }

        if (!Storage::disk('public')->exists('uploads/hotels')) {
            Storage::disk('public')->makeDirectory('uploads/hotels', 0755, true);
            Log::info("Đã tạo thư mục uploads/hotels");
        }

        $requests = function () {
            $requestCount = 0;
            foreach ($this->imageUrls as $index => $item) {
                $imageUrls = is_array($item['images']) ? $item['images'] : explode(',', $item['images']);
                foreach ($imageUrls as $url) {
                    $url = trim($url);
                    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                        $this->failedImages[] = "URL không hợp lệ cho hotel_id {$item['hotel_id']}: $url";
                        Log::warning("URL không hợp lệ: $url");
                        continue;
                    }
                    $requestCount++;
                    yield new Request('GET', $url);
                }
            }
            Log::info("Tổng số request ảnh trong HotelImport: $requestCount");
        };

        $pool = new Pool($this->guzzleClient, $requests(), [
            'concurrency' => 10,
            'fulfilled' => function ($response, $index) {
                $itemIndex = 0;
                $totalUrls = 0;
                foreach ($this->imageUrls as $i => $item) {
                    $urls = is_array($item['images']) ? $item['images'] : explode(',', $item['images']);
                    $urls = array_filter(array_map('trim', $urls), fn($url) => filter_var($url, FILTER_VALIDATE_URL));
                    $totalUrls += count($urls);
                    if ($index < $totalUrls) {
                        $itemIndex = $i;
                        break;
                    }
                }

                $item = $this->imageUrls[$itemIndex];
                $imageName = time() . '_' . uniqid() . '.jpg';
                $destinationPath = 'uploads/hotels/' . $imageName;

                try {
                    Storage::disk('public')->put($destinationPath, $response->getBody());
                    Log::info("Đã lưu ảnh: $destinationPath cho hotel_id {$item['hotel_id']}");
                    $hotel = Hotel::find($item['hotel_id']);
                    if ($hotel) {
                        $images = json_decode($hotel->images, true) ?? [];
                        $images[] = $destinationPath;
                        $hotel->images = json_encode($images);
                        $hotel->save();
                        Log::info("Đã cập nhật images cho hotel_id {$item['hotel_id']}: " . json_encode($images));
                    } else {
                        $this->failedImages[] = "Không tìm thấy hotel_id {$item['hotel_id']}";
                        Log::error("Không tìm thấy hotel_id {$item['hotel_id']}");
                    }
                } catch (\Exception $e) {
                    $this->failedImages[] = "Lỗi lưu ảnh cho hotel_id {$item['hotel_id']}: " . $e->getMessage();
                    Log::error("Lỗi lưu ảnh cho hotel_id {$item['hotel_id']}: " . $e->getMessage());
                }
            },
            'rejected' => function ($reason, $index) {
                $this->failedImages[] = "Lỗi tải ảnh: $reason";
                Log::error("Lỗi tải ảnh: $reason");
            },
        ]);

        $promise = $pool->promise();
        $promise->wait();
    }
}