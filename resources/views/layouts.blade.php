<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPSUM Travel - @yield('title', 'Quản trị')</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap');
        
        body {
            font-family: 'Roboto', sans-serif;
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <!-- Top Navigation Bar -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <span class="text-xl font-bold text-primary-600">IPSUM Travel</span>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="ml-3 relative">
                        <div>
                            <button type="button" class="bg-white rounded-full flex text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500" id="user-menu-button">
                                <span class="sr-only">Mở menu</span>
                                <span class="h-8 w-8 rounded-full bg-primary-100 flex items-center justify-center">
                                    <i class="fas fa-user text-primary-600"></i>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex flex-1">
        <!-- Sidebar -->
        <div class="w-64 bg-white shadow-md hidden md:block">
            <div class="h-full flex flex-col">
                <div class="flex-1 flex flex-col pt-5 pb-4 overflow-y-auto">
                    <div class="flex-1 px-3 space-y-1">
                        <div class="px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Quản lý
                        </div>
                        
                        <a href="{{ route('admin.dishes.index') }}" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-primary-50 hover:text-primary-700 {{ request()->routeIs('admin.dishes.*') ? 'bg-primary-100 text-primary-700' : 'text-gray-600' }}">
                            <i class="fas fa-utensils mr-3 text-gray-400 group-hover:text-primary-500 {{ request()->routeIs('admin.dishes.*') ? 'text-primary-500' : '' }}"></i>
                            Món ăn
                        </a>
                        <a href="{{ route('admin.restaurants.index') }}" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-primary-50 hover:text-primary-700 {{ request()->routeIs('admin.restaurants.*') ? 'bg-primary-100 text-primary-700' : 'text-gray-600' }}">
                            <i class="fas fa-store mr-3 text-gray-400 group-hover:text-primary-500 {{ request()->routeIs('admin.restaurants.*') ? 'text-primary-500' : '' }}"></i>
                            Nhà Hàng
                        </a>
                        
                        <a href="{{ route('admin.checkin_places.index') }}" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-primary-50 hover:text-primary-700 {{ request()->routeIs('admin.checkin_places.*') ? 'bg-primary-100 text-primary-700' : 'text-gray-600' }}">
                            <i class="fas fa-map-marker-alt mr-3 text-gray-400 group-hover:text-primary-500 {{ request()->routeIs('admin.checkin_places.*') ? 'text-primary-500' : '' }}"></i>
                            Địa điểm
                        </a>
                        
                        <!-- Thêm menu khác nếu có -->
                        <div class="px-3 py-3 mt-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Hệ thống
                        </div>
                        
                        <a href="#" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-primary-50 hover:text-primary-700 text-gray-600">
                            <i class="fas fa-cog mr-3 text-gray-400 group-hover:text-primary-500"></i>
                            Cài đặt
                        </a>
                        
                        <a href="#" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-primary-50 hover:text-primary-700 text-gray-600">
                            <i class="fas fa-sign-out-alt mr-3 text-gray-400 group-hover:text-primary-500"></i>
                            Đăng xuất
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile menu button -->
        <div class="md:hidden fixed bottom-0 inset-x-0 bg-white border-t border-gray-200 flex">
            <a href="{{ route('admin.dishes.index') }}" class="flex-1 text-center py-4 {{ request()->routeIs('admin.dishes.*') ? 'text-primary-600' : 'text-gray-600' }}">
                <i class="fas fa-utensils text-lg"></i>
                <span class="block text-xs mt-1">Món ăn</span>
            </a>
             <a href="{{ route('admin.restaurants.index') }}" class="flex-1 text-center py-4 {{ request()->routeIs('admin.restaurants.*') ? 'text-primary-600' : 'text-gray-600' }}">
                <i class="fas fa-store-alt text-lg"></i>
                <span class="block text-xs mt-1">Nhà Hàng</span>
            </a>
            <a href="{{ route('admin.checkin_places.index') }}" class="flex-1 text-center py-4 {{ request()->routeIs('admin.checkin_places.*') ? 'text-primary-600' : 'text-gray-600' }}">
                <i class="fas fa-map-marker-alt text-lg"></i>
                <span class="block text-xs mt-1">Địa điểm</span>
            </a>
            <button type="button" class="flex-1 text-center py-4 text-gray-600" id="mobile-menu-button">
                <i class="fas fa-bars text-lg"></i>
                <span class="block text-xs mt-1">Menu</span>
            </button>
        </div>

        <!-- Main content -->
        <div class="flex-1 overflow-auto pb-16 md:pb-0">
            <div class="py-6 px-4 sm:px-6 lg:px-8">
                <!-- Page header -->
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">@yield('header', 'Quản trị')</h1>
                </div>
                
                <!-- Success message -->
                @if(session('success'))
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded" role="alert">
                        <p class="font-medium">Thành công!</p>
                        <p>{{ session('success') }}</p>
                    </div>
                @endif
                
                <!-- Error message -->
                @if(session('error'))
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                        <p class="font-medium">Lỗi!</p>
                        <p>{{ session('error') }}</p>
                    </div>
                @endif
                
                <!-- Main content -->
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="p-6">
                        @yield('content')
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 hidden md:block">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm">&copy; {{ date('Y') }} - Hệ thống quản trị IPSUM Travel</p>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Phiên bản 1.0.0</p>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Toggle mobile menu
        document.getElementById('mobile-menu-button')?.addEventListener('click', function() {
            // Add mobile menu functionality here if needed
            alert('Menu mobile clicked');
        });
        
        // Toggle user dropdown
        document.getElementById('user-menu-button')?.addEventListener('click', function() {
            // Add user dropdown functionality here if needed
            alert('User menu clicked');
        });
    </script>
</body>
</html>