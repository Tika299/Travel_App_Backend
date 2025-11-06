<?php
 
namespace Database\Seeders;

use App\Models\Review;
use Illuminate\Database\Seeder;

class ReviewsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Xóa các đánh giá cũ nếu cần, hoặc bỏ qua nếu bạn muốn thêm vào
        // Review::truncate(); // Cẩn thận khi dùng truncate, nó sẽ xóa hết dữ liệu cũ!

        // Đánh giá cho một địa điểm duy nhất (CheckinPlace có ID = 1)
        Review::create([
            'user_id' => 1, // Đảm bảo user_id này tồn tại trong bảng users
            'reviewable_type' => 'App\Models\CheckinPlace',
            'reviewable_id' => 1, // Chỉ ID 1 cho địa điểm
            'content' => 'Hồ Hoàn Kiếm rất đẹp về đêm, nhiều góc chụp ảnh đẹp. Không gian thoáng đãng, lý tưởng để đi dạo.',
            'rating' => 1,
            'is_approved' => true
        ]);

        Review::create([
            'user_id' => 2, // Đảm bảo user_id này tồn tại
            'reviewable_type' => 'App\Models\CheckinPlace',
            'reviewable_id' => 1, // Chỉ ID 1 cho địa điểm
            'content' => 'Địa điểm này rất đáng để ghé thăm. Tôi rất thích kiến trúc và không khí ở đây. Rất nhiều cửa hàng xung quanh.',
            'rating' => 3,
            'is_approved' => true
        ]);

        Review::create([
            'user_id' => 3, // Đảm bảo user_id này tồn tại
            'reviewable_type' => 'App\Models\CheckinPlace',
            'reviewable_id' => 1, // Chỉ ID 1 cho địa điểm
            'content' => 'Cảnh quan tuyệt vời, tôi đã có một buổi chiều thư giãn tại đây. Rất nhiều cây xanh và không khí trong lành.',
            'rating' => 4.7,
            'is_approved' => true
        ]);

        Review::create([
            'user_id' => 4, // Đảm bảo user_id này tồn tại
            'reviewable_type' => 'App\Models\CheckinPlace',
            'reviewable_id' => 1, // Chỉ ID 1 cho địa điểm
            'content' => 'Một nơi đáng yêu để dành thời gian, đặc biệt là vào buổi tối. Có rất nhiều hoạt động và người dân địa phương.',
            'rating' => 4.6,
            'is_approved' => true
        ]);

        Review::create([
            'user_id' => 5, // Đảm bảo user_id này tồn tại
            'reviewable_type' => 'App\Models\CheckinPlace',
            'reviewable_id' => 1, // Chỉ ID 1 cho địa điểm
            'content' => 'Tuyệt vời để đi bộ và khám phá. Tôi rất thích các di tích lịch sử ở khu vực này. Cần nhiều thời gian hơn để khám phá hết.',
            'rating' => 4.9,
            'is_approved' => true
        ]);

        // Đánh giá cho một công ty vận tải duy nhất (TransportCompany có ID = 1)
        Review::create([
            'user_id' => 1, // Đảm bảo user_id này tồn tại
            'reviewable_type' => 'App\Models\TransportCompany',
            'reviewable_id' => 1, // Chỉ ID 1 cho phương tiện
            'content' => 'Tài xế Mai Linh lái xe an toàn, giá cả hợp lý. Dịch vụ nhanh chóng và đáng tin cậy.',
            'rating' => 4.0,
            'is_approved' => true // Đã sửa thành true để hiển thị mặc định
        ]);

        Review::create([
            'user_id' => 2, // Đảm bảo user_id này tồn tại
            'reviewable_type' => 'App\Models\TransportCompany',
            'reviewable_id' => 1, // Chỉ ID 1 cho phương tiện
            'content' => 'Dịch vụ Mai Linh rất chuyên nghiệp. Luôn đến đúng giờ và xe sạch sẽ. Rất khuyến khích!',
            'rating' => 4.2,
            'is_approved' => true
        ]);

        Review::create([
            'user_id' => 3, // Đảm bảo user_id này tồn tại
            'reviewable_type' => 'App\Models\TransportCompany',
            'reviewable_id' => 1, // Chỉ ID 1 cho phương tiện
            'content' => 'Mai Linh có ứng dụng gọi xe tiện lợi, nhưng có lúc tài xế từ chối chuyến đi ngắn. Tuy nhiên, nhìn chung là tốt.',
            'rating' => 3.5,
            'is_approved' => true
        ]);
    }
}