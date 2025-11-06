<?php

namespace Database\Seeders;
use App\Models\Amenity;
use Illuminate\Database\Seeder;

class AmenitiesTableSeeder extends Seeder
{
    public function run()
    {
        Amenity::create([
            'name' => 'Wifi miễn phí',
            'icon' => 'wifi.svg',
            'react_icon' => 'FaWifi'
        ]);

        Amenity::create([
            'name' => 'Điều hòa nhiệt độ',
            'icon' => 'air-conditioner.svg',
            'react_icon' => 'MdAcUnit'
        ]);

        Amenity::create([
            'name' => 'Hồ bơi',
            'icon' => 'pool.svg',
            'react_icon' => 'FaSwimmingPool'
        ]);

        Amenity::create([
            'name' => 'Bãi đậu xe',
            'icon' => 'parking.svg',
            'react_icon' => 'FaParking'
        ]);

        Amenity::create([
            'name' => 'Nhà hàng',
            'icon' => 'restaurant.svg',
            'react_icon' => 'FaUtensils'
        ]);

        Amenity::create([
            'name' => 'Quầy bar',
            'icon' => 'bar.svg',
            'react_icon' => 'FaCocktail'
        ]);

        Amenity::create([
            'name' => 'Spa',
            'icon' => 'spa.svg',
            'react_icon' => 'FaSpa'
        ]);

        Amenity::create([
            'name' => 'Phòng gym',
            'icon' => 'gym.svg',
            'react_icon' => 'FaDumbbell'
        ]);

        Amenity::create([
            'name' => 'Thang máy',
            'icon' => 'elevator.svg',
            'react_icon' => 'FaSortAmountUp'
        ]);

        Amenity::create([
            'name' => 'Dịch vụ phòng',
            'icon' => 'room-service.svg',
            'react_icon' => 'MdRoomService'
        ]);

        Amenity::create([
            'name' => 'TV màn hinh phẳng',
            'react_icon' => 'FaTv'
        ]);

        Amenity::create([
            'name' => 'Máy pha cà phê',
            'react_icon' => 'MdCoffeeMaker'
        ]);

        Amenity::create([
            'name' => 'Ban công',
            'react_icon' => 'MdBalcony'
        ]);

        Amenity::create([
            'name' => 'Hệ thống cách âm',
            'react_icon' => 'IoVolumeMute'
        ]);

        Amenity::create([
            'name' => 'Phòng tắm riêng trong phòng',
            'react_icon' => 'FaBath'
        ]);

        Amenity::create([
            'name' => 'Phòng tắm riêng',
            'react_icon' => 'MdBathtub'
        ]);

        Amenity::create([
            'name' => 'Nhìn ra vườn',
            'react_icon' => 'MdDoorSliding'
        ]);

        Amenity::create([
            'name' => 'Nhìn ra thành phố',
            'react_icon' => 'FaCity'
        ]);

        Amenity::create([
            'name' => 'Bồn tắm',
            'react_icon' => 'MdOutlineBathtub'
        ]);

        Amenity::create([
            'name' => 'Tầm nhìn ra khung cảnh',
            'react_icon' => 'MdLooks'
        ]);

        Amenity::create([
            'name' => 'Bếp riêng',
            'react_icon' => 'MdFireplace'
        ]);

        Amenity::create([
            'name' => 'Sân hiên',
            'react_icon' => 'MdOutlineYard'
        ]);

        Amenity::create([
            'name' => 'Nhìn ra sông',
            'react_icon' => 'GiRiver'
        ]);

        Amenity::create([
            'name' => 'Nhìn ra núi',
            'react_icon' => 'FaMountainSun'
        ]);

        Amenity::create([
            'name' => 'Căn hộ nguyên căn',
            'react_icon' => 'MdApartment'
        ]);

        Amenity::create([
            'name' => 'Studio nguyên căn',
            'react_icon' => 'MdMapsHomeWork'
        ]);

        Amenity::create([
            'name' => 'Sân trong',
            'react_icon' => 'MdYard'
        ]);

        Amenity::create([
            'name' => 'Suite nguyên căn',
            'react_icon' => 'MdApartment'
        ]);

        Amenity::create([
            'name' => 'Suite riêng tư',
            'react_icon' => 'MdApartment'
        ]);

        Amenity::create([
            'name' => 'Bếp nhỏ riêng',
            'react_icon' => 'MdFireplace'
        ]);

        Amenity::create([
            'name' => 'Nhìn ra địa danh nổi tiếng',
            'react_icon' => 'MdMuseum'
        ]);

        Amenity::create([
            'name' => 'Bungalow nguyên căn',
            'react_icon' => 'FaTent'
        ]);

        Amenity::create([
            'name' => 'Tiện nghi BBQ',
            'react_icon' => 'MdOutdoorGrill'
        ]);

        Amenity::create([
            'name' => 'Nhìn ra hồ',
            'react_icon' => 'GiWaterfall'
        ]);

        Amenity::create([
            'name' => 'Hồ bơi riêng',
            'react_icon' => 'FaWaterLadder'
        ]);

        Amenity::create([
            'name' => 'Hồ bơi có tầm nhìn',
            'react_icon' => 'FaWaterLadder'
        ]);

        Amenity::create([
            'name' => 'Hồ bơi có tầm nhìn',
            'react_icon' => 'MdPool'
        ]);

        Amenity::create([
            'name' => 'Biệt thự nguyên căn',
            'react_icon' => 'FaHome'
        ]);

        Amenity::create([
            'name' => 'Lều trại',
            'react_icon' => 'FaCampground'
        ]);

        Amenity::create([
            'name' => 'Bếp nhỏ',
            'react_icon' => 'MdFireplace'
        ]);

        Amenity::create([
            'name' => 'Máy giặt',
            'react_icon' => 'GiWashingMachine'
        ]);
    }
}
