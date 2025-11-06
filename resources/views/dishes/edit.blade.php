@extends('layouts')

@section('title', 'Chỉnh sửa món ăn')

@section('header', 'Chỉnh sửa món ăn')

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

    <form action="{{ route('admin.dishes.update', $dish->id) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')
        
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Thông tin món ăn</h2>
                <div class="mt-6 space-y-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Tên món ăn <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name', $dish->name) }}" required
                            class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">Mô tả</label>
                        <textarea id="description" name="description" rows="4"
                            class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">{{ old('description', $dish->description) }}</textarea>
                    </div>

                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700">Giá <span class="text-red-500">*</span></label>
                        <div class="relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">₫</span>
                            </div>
                            <input type="number" name="price" id="price" min="0" step="1000"
                                value="{{ old('price', $dish->price) }}" required
                                class="focus:ring-primary-500 focus:border-primary-500 block w-full pl-7 pr-12 sm:text-sm border-gray-300 rounded-md">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">VND</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="restaurant_id" class="block text-sm font-medium text-gray-700">Nhà hàng <span class="text-red-500">*</span></label>
                        <select id="restaurant_id" name="restaurant_id" required
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md">
                            <option value="">-- Chọn nhà hàng --</option>
                            @foreach ($restaurants as $restaurant)
                                <option value="{{ $restaurant->id }}" {{ old('restaurant_id', $dish->restaurant_id) == $restaurant->id ? 'selected' : '' }}>
                                    {{ $restaurant->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="p-6 border-b border-gray-200 bg-gray-50">
                <h2 class="text-lg font-medium text-gray-900">Hình ảnh món ăn</h2>
                <div class="mt-6">
                    <label for="image" class="block text-sm font-medium text-gray-700">Hình ảnh (URL)</label>
                    <input type="text" name="image" id="image" value="{{ old('image', $dish->image) }}"
                        class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">

                    <div class="mt-4">
                        <p class="text-sm text-gray-500">Hình hiện tại:</p>
                        @if ($dish->image)
                            <img src="{{ $dish->image }}" alt="Dish Image" class="w-40 mt-2 rounded shadow">
                        @else
                            <p class="text-sm text-gray-400 italic">Chưa có hình</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-between">
            <a href="{{ route('admin.dishes.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i> Quay lại
            </a>
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-md hover:bg-primary-700">
                <i class="fas fa-save mr-2"></i> Cập nhật món ăn
            </button>
        </div>
    </form>
</div>
@endsection
