<?php

namespace App\Imports;

use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\Amenity;
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

class HotelRoomImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, SkipsOnFailure, WithBatchInserts, WithEvents
{
    protected $imageUrls = [];
    protected $amenitiesData = [];
    protected $guzzleClient;
    protected $amenityCache = [];
    protected $failedImages = [];

    public function __construct()
    {
        $this->guzzleClient = new Client([
            'timeout' => 20,
            'http_errors' => false,
        ]);
        $this->amenityCache = Amenity::pluck('id', 'name')->toArray();
    }

    public function model(array $row)
    {
        if (empty($row['hotel_id'] ?? '')) {
            Log::info("Bỏ qua dòng không có hotel_id: " . json_encode($row));
            return null;
        }

        $hotel = Hotel::find($row['hotel_id']);
        if (!$hotel) {
            Log::warning("Không tìm thấy khách sạn với ID: " . $row['hotel_id']);
            return null;
        }

        $roomArea = null;
        if (!empty($row['room_area'] ?? '') && preg_match('/^\d+(\.\d+)?\s*m²$/', trim($row['room_area']))) {
            $roomArea = trim($row['room_area']);
        }

        $room = new HotelRoom([
            'hotel_id' => $hotel->id,
            'room_type' => $row['room_type'],
            'price_per_night' => $row['price_per_night'],
            'description' => $row['description'] ?? null,
            'room_area' => $roomArea,
            'bed_type' => $row['bed_type'] ?? null,
            'max_occupancy' => $row['max_occupancy'] ?? null,
            'images' => json_encode([]),
        ]);

        $room->save();
        if (!empty($row['images'])) {
            $this->imageUrls[] = [
                'room_id' => $room->id,
                'images' => $row['images'],
            ];
            Log::info("Thêm URL ảnh cho room_id {$room->id}: " . $row['images']);
        }

        if (!empty($row['amenities'])) {
            $this->amenitiesData[] = [
                'room_id' => $room->id,
                'amenities' => $row['amenities'],
            ];
        }

        return null;
    }

    public function rules(): array
    {
        return [
            'hotel_id' => 'required|exists:hotels,id',
            'room_type' => 'required|string',
            'price_per_night' => 'required|string',
            'description' => 'nullable|string',
            'room_area' => 'nullable|string',
            'bed_type' => 'nullable|string',
            'max_occupancy' => 'nullable|integer|min:1',
            'amenities' => 'nullable|string',
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
                Log::info("Sự kiện AfterImport được gọi cho HotelRoomImport");
                $this->onFinish();
            },
        ];
    }

    public function onFinish()
    {
        set_time_limit(1800);
        Log::info("Bắt đầu xử lý ảnh và tiện ích cho HotelRoomImport. Số lượng bản ghi ảnh: " . count($this->imageUrls));
        DB::transaction(function () {
            $this->processImages();
            $this->processAmenities();
        });
        Log::info("Hoàn tất xử lý ảnh cho HotelRoomImport. Lỗi: " . json_encode($this->failedImages));
        return [
            'failed_images' => $this->failedImages,
        ];
    }

    protected function processImages()
    {
        if (empty($this->imageUrls)) {
            Log::info("Không có URL ảnh nào để xử lý trong HotelRoomImport.");
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
                        $this->failedImages[] = "URL không hợp lệ cho room_id {$item['room_id']}: $url";
                        Log::warning("URL không hợp lệ: $url");
                        continue;
                    }
                    $requestCount++;
                    yield new Request('GET', $url);
                }
            }
            Log::info("Tổng số request ảnh trong HotelRoomImport: $requestCount");
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
                    Log::info("Đã lưu ảnh: $destinationPath cho room_id {$item['room_id']}");
                    $room = HotelRoom::find($item['room_id']);
                    if ($room) {
                        $images = json_decode($room->images, true) ?? [];
                        $images[] = $destinationPath;
                        $room->images = json_encode($images);
                        $room->save();
                        Log::info("Đã cập nhật images cho room_id {$item['room_id']}: " . json_encode($images));
                    } else {
                        $this->failedImages[] = "Không tìm thấy room_id {$item['room_id']}";
                        Log::error("Không tìm thấy room_id {$item['room_id']}");
                    }
                } catch (\Exception $e) {
                    $this->failedImages[] = "Lỗi lưu ảnh cho room_id {$item['room_id']}: " . $e->getMessage();
                    Log::error("Lỗi lưu ảnh cho room_id {$item['room_id']}: " . $e->getMessage());
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

    protected function processAmenities()
    {
        foreach ($this->amenitiesData as $item) {
            $amenityIds = [];
            $amenityInputs = is_array($item['amenities']) ? $item['amenities'] : explode(',', $item['amenities']);

            foreach ($amenityInputs as $input) {
                $input = trim($input);
                if (empty($input)) continue;

                if (is_numeric($input)) {
                    if (Amenity::where('id', $input)->exists()) {
                        $amenityIds[] = $input;
                    }
                } else {
                    if (isset($this->amenityCache[$input])) {
                        $amenityIds[] = $this->amenityCache[$input];
                    } else {
                        $amenity = Amenity::firstOrCreate(
                            ['name' => $input],
                            ['react_icon' => null]
                        );
                        $this->amenityCache[$input] = $amenity->id;
                        $amenityIds[] = $amenity->id;
                    }
                }
            }

            if (!empty($amenityIds)) {
                $room = HotelRoom::find($item['room_id']);
                if ($room) {
                    $room->amenityList()->sync($amenityIds);
                    Log::info("Đã đồng bộ tiện ích cho room_id {$item['room_id']}: " . json_encode($amenityIds));
                }
            }
        }
    }
}