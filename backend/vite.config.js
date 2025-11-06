import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    // 💡 Thêm cấu hình proxy tại đây để giải quyết lỗi 404 (Not Found)
    proxy: {
      // Khi có một yêu cầu bắt đầu bằng '/api'
      '/api': {
        // Chuyển tiếp yêu cầu đó đến server backend
        // Đảm bảo URL này khớp với URL của server Laravel của bạn
        target: 'http://localhost:8000',
        
        // Cần thiết để đảm bảo các header của yêu cầu được gửi đi đúng cách
        changeOrigin: true,
        
        // Viết lại đường dẫn yêu cầu
        // Ví dụ: '/api/transport-companies/import' sẽ trở thành
        // 'http://localhost:8000/api/transport-companies/import'
        rewrite: (path) => path.replace(/^\/api/, '/api'),
      },
    },
  },
});
