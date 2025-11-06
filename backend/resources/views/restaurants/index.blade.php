@extends('layouts')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Danh sách món ăn</h1>
        <a href="{{ route('admin.restaurants.create') }}"
            class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd"
                    d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"
                    clip-rule="evenodd" />
            </svg>
            Thêm món ăn mới
        </a>
    </div>

    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded mb-6">
        {{ session('success') }}
    </div>
    @endif

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tên món</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Giá</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hình ảnh</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hành động</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($restaurants as $restaurant)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $restaurant->id }}</td>
                    <td class="px-6 py-4 text-sm text-gray-900 font-medium">{{ $restaurant->name }}</td>
                    @php
                    [$min, $max] = explode('-', $restaurant->price_range);
                    $min = (int) str_replace([',', ' '], '', $min);
                    $max = (int) str_replace([',', ' '], '', $max);
                    @endphp
                    <td class="px-6 py-4 text-sm text-gray-900 font-semibold">
                        {{ number_format($min, 0, ',', '.') }} - {{ number_format($max, 0, ',', '.') }} VND
                    </td>
                    <td class="px-6 py-4">
                        @if ($restaurant->image)
                        <img src="{{ $restaurant->image }}" alt="{{ $restaurant->name }}"
                            class="h-16 w-16 object-cover rounded-md shadow-sm border">
                        @else
                        <span class="inline-block px-2 py-1 text-xs font-medium bg-gray-100 text-gray-700 rounded">Không
                            có ảnh</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <div class="flex space-x-2">
                            <a href="{{ route('admin.restaurants.edit', $restaurant->id) }}"
                                class="inline-flex items-center px-3 py-1 text-indigo-600 bg-indigo-100 hover:bg-indigo-200 rounded-md text-xs font-medium transition">
                                ✏️ Sửa
                            </a>
                            <form action="{{ route('admin.restaurants.destroy', $restaurant->id) }}" method="POST"
                                onsubmit="return confirm('Bạn có chắc muốn xoá món này?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="inline-flex items-center px-3 py-1 text-red-600 bg-red-100 hover:bg-red-200 rounded-md text-xs font-medium transition">
                                    🗑️ Xoá
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-4 text-center text-gray-500 text-sm">Không có món ăn nào.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection