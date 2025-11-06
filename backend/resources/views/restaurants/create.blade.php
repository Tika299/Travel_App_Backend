@extends('layouts')

@section('title', 'Thêm nhà hàng')

@section('header', 'Thêm nhà hàng mới')

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

    <form action="{{ route('admin.restaurants.store') }}" method="POST" class="space-y-6">
        @csrf

        <div class="bg-white shadow-sm rounded-lg overflow-hidden p-6 space-y-6 border border-black">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 ">Tên nhà hàng <span class="text-red-500">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 border border-black">
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700">Mô tả</label>
                <textarea name="description" id="description" rows="3" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 border border-black">{{ old('description') }}</textarea>
            </div>

            <div>
                <label for="address" class="block text-sm font-medium text-gray-700">Địa chỉ <span class="text-red-500">*</span></label>
                <input type="text" name="address" id="address" value="{{ old('address') }}" required class="block w-full border-gray-300 rounded-md shadow-sm border border-black">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="latitude" class="block text-sm font-medium text-gray-700">Vĩ độ (Latitude) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.000001" name="latitude" id="latitude" value="{{ old('latitude') }}" required class="block w-full border-gray-300 rounded-md shadow-sm border border-black">
                </div>
                <div>
                    <label for="longitude" class="block text-sm font-medium text-gray-700">Kinh độ (Longitude) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.000001" name="longitude" id="longitude" value="{{ old('longitude') }}" required class="block w-full border-gray-300 rounded-md shadow-sm border border-black">
                </div>
            </div>

            <div>
                <label for="rating" class="block text-sm font-medium text-gray-700">Đánh giá (Rating)</label>
                <input type="number" step="0.1" name="rating" id="rating" value="{{ old('rating') }}" class="block w-full border-gray-300 rounded-md shadow-sm border border-black">
            </div>

            <div>
                <label for="price_range" class="block text-sm font-medium text-gray-700">Khoảng giá <span class="text-red-500">*</span></label>
                <input type="text" name="price_range" id="price_range" value="{{ old('price_range') }}" required class="block w-full border-gray-300 rounded-md shadow-sm border border-black">
                <p class="text-sm text-gray-500">Ví dụ: "100,000 - 300,000 VND/người"</p>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-md hover:bg-primary-700">Lưu nhà hàng</button>
        </div>
    </form>
</div>
@endsection
