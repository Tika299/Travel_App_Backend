@extends('layouts')

@section('title', 'Thêm món ăn')

@section('header', 'Thêm món ăn mới')

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

    <form action="{{ route('admin.dishes.store') }}" method="POST" class="space-y-6">
        @csrf

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Thông tin món ăn</h2>
                <p class="mt-1 text-sm text-gray-500">Nhập thông tin chi tiết về món ăn mới.</p>

                <div class="mt-6 space-y-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">
                            Tên món ăn <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1">
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">
                            Mô tả
                        </label>
                        <div class="mt-1">
                            <textarea id="description" name="description" rows="4"
                                class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">{{ old('description') }}</textarea>
                        </div>
                        <p class="mt-2 text-sm text-gray-500">Mô tả ngắn gọn về món ăn, nguyên liệu, cách chế biến...
                        </p>
                    </div>

                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700">
                            Giá <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">₫</span>
                            </div>
                            <input type="number" name="price" id="price" min="0" step="1000" value="{{ old('price') }}"
                                required
                                class="focus:ring-primary-500 focus:border-primary-500 block w-full pl-7 pr-12 sm:text-sm border-gray-300 rounded-md"
                                placeholder="0">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">VND</span>
                            </div>
                        </div>
                        <p class="mt-2 text-sm text-gray-500">Giá bán của món ăn (VND).</p>
                    </div>

                    <div>
                        <label for="restaurant_id" class="block text-sm font-medium text-gray-700">
                            Nhà hàng <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1">
                            <select id="restaurant_id" name="restaurant_id" required
                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md">
                                <option value="">-- Chọn nhà hàng --</option>
                                @foreach ($restaurants as $restaurant)
                                <option value="{{ $restaurant->id }}"
                                    {{ old('restaurant_id') == $restaurant->id ? 'selected' : '' }}>
                                    {{ $restaurant->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <p class="mt-2 text-sm text-gray-500">Chọn nhà hàng cung cấp món ăn này.</p>
                    </div>
                    <div>
                        <label for="is_best_seller" class="block text-sm font-medium text-gray-700">
                            Best seller <span class="text-red-500">*</span>
                        </label>
                        <select id="is_best_seller" name="is_best_seller" required
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md">
                            <option value="1" {{ old('is_best_seller') == '1' ? 'selected' : '' }}>True</option>
                            <option value="0" {{ old('is_best_seller') == '0' ? 'selected' : '' }}>False</option>
                        </select>
                    </div>

                </div>


            </div>
        </div>

        <div class="p-6 border-b border-gray-200 bg-gray-50">
            <h2 class="text-lg font-medium text-gray-900">Hình ảnh món ăn</h2>
            <p class="mt-1 text-sm text-gray-500">Thêm hình ảnh cho món ăn (có thể thêm sau).</p>

            <div class="mt-6">
                <label for="image" class="block text-sm font-medium text-gray-700">Hình ảnh (URL)</label>
                <div class="mt-1">
                    <input type="text" name="image" id="image" value="{{ old('image') }}"
                        class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">
                </div>
                <p class="mt-2 text-sm text-gray-500">Nhập URL hình ảnh hoặc upload sau.</p>

                <div class="mt-4 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                    <div class="space-y-1 text-center">
                        <i class="fas fa-utensils text-3xl text-gray-400"></i>
                        <div class="flex text-sm text-gray-600">
                            <label for="image_upload"
                                class="relative cursor-pointer bg-white rounded-md font-medium text-primary-600 hover:text-primary-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-primary-500">
                                <span>Tải lên hình ảnh</span>
                                <input id="image_upload" name="image_upload" type="file" class="sr-only">
                            </label>
                            <p class="pl-1">hoặc kéo thả vào đây</p>
                        </div>
                        <p class="text-xs text-gray-500">PNG, JPG, GIF tối đa 10MB</p>
                    </div>
                </div>
            </div>
        </div>
</div>

<div class="flex justify-between">
    <a href="{{ route('admin.dishes.index') }}"
        class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
        <i class="fas fa-arrow-left mr-2"></i>
        Quay lại danh sách
    </a>
    <button type="submit"
        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
        <i class="fas fa-save mr-2"></i>
        Lưu món ăn
    </button>
</div>
</form>
</div>
@endsection