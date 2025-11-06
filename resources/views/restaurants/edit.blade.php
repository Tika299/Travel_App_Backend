@extends('layouts')

@section('title', 'Chỉnh sửa nhà hàng')

@section('header', 'Cập nhật thông tin nhà hàng')

@section('content')
<div class="max-w-2xl mx-auto">
    @if ($errors->any())
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
            <p class="font-medium">Vui lòng kiểm tra lại thông tin:</p>
            <ul class="mt-2 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.restaurants.update', $restaurant->id) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="bg-white shadow-sm rounded-lg overflow-hidden p-6 space-y-6  border border-black">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-500">Tên nhà hàng <span class="text-red-500">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name', $restaurant->name) }}" required class="block w-full border-gray-500 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 px-4 py-2 border-0 border-b-2">
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-500">Mô tả</label>
                <textarea name="description" id="description" rows="3" class="block w-full border-gray-500 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 px-4 py-3 border-0 border-b-2">{{ old('description', $restaurant->description) }}</textarea>
            </div>

            <div>
                <label for="address" class="block text-sm font-medium text-gray-500">Địa chỉ <span class="text-red-500">*</span></label>
                <input type="text" name="address" id="address" value="{{ old('address', $restaurant->address) }}" required class="block w-full border-gray-500 rounded-md shadow-sm px-4 py-3 border-0 border-b-2">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="latitude" class="block text-sm font-medium text-gray-500">Vĩ độ (Latitude) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.000001" name="latitude" id="latitude" value="{{ old('latitude', $restaurant->latitude) }}" required class="block w-full border-gray-500 rounded-md shadow-sm px-4 py-3 border-0 border-b-2">
                </div>
                <div>
                    <label for="longitude" class="block text-sm font-medium text-gray-500">Kinh độ (Longitude) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.000001" name="longitude" id="longitude" value="{{ old('longitude', $restaurant->longitude) }}" required class="block w-full border-gray-500 rounded-md shadow-sm px-4 py-3 border-0 border-b-2">
                </div>
            </div>

            <div>
                <label for="rating" class="block text-sm font-medium text-gray-500">Đánh giá (Rating)</label>
                <input type="number" step="0.1" name="rating" id="rating" value="{{ old('rating', $restaurant->rating) }}" class="block w-full border-gray-500 rounded-md shadow-sm px-4 py-3 border-0 border-b-2">
            </div>

            <div>
                <label for="price_range" class="block text-sm font-medium text-gray-500">Khoảng giá <span class="text-red-500">*</span></label>
                <input type="text" name="price_range" id="price_range" value="{{ old('price_range', $restaurant->price_range) }}" required class="block w-full border-gray-500 rounded-md shadow-sm px-4 py-3 border-0 border-b-2">
            </div>
        </div>

        <div class="flex justify-between">
            <a href="{{ route('admin.restaurants.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-500 shadow-sm text-sm font-medium rounded-md text-gray-500 bg-white hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i>
                Quay lại
            </a>
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-md hover:bg-primary-700">
                <i class="fas fa-save mr-2"></i>
                Cập nhật
            </button>
        </div>
    </form>
</div>
@endsection
