<!DOCTYPE html>
<html>
<head>
    <title>Test Image Display</title>
    <style>
        .test-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #ccc;
        }
    </style>
</head>
<body>
    <h1>Test Image Display</h1>
    
    <h2>Ảnh mới import:</h2>
    <img src="http://localhost:8000/storage/uploads/cuisine/1754849485_6898e0cd04618.jpg" 
         alt="Bún chả" class="test-image" />
    <p>Đường dẫn: storage/uploads/cuisine/1754849485_6898e0cd04618.jpg</p>
    
    <h2>Ảnh mặc định:</h2>
    <img src="http://localhost:8000/storage/uploads/cuisine/default-food.gif" 
         alt="Default food" class="test-image" />
    <p>Đường dẫn: storage/uploads/cuisine/default-food.gif</p>
    
    <h2>Ảnh cũ (có thể không hoạt động):</h2>
    <img src="http://localhost:8000/storage/cuisine/906qXwWkVjHlYDKltjOTT3A7L9ajf5JWDnEeczmE.jpg" 
         alt="Bún Chả Hà Nội" class="test-image" />
    <p>Đường dẫn: http://localhost:8000/storage/cuisine/906qXwWkVjHlYDKltjOTT3A7L9ajf5JWDnEeczmE.jpg</p>
    
    <script>
        // Kiểm tra lỗi load ảnh
        document.querySelectorAll('img').forEach(img => {
            img.onerror = function() {
                console.error('Lỗi load ảnh:', this.src);
                this.style.border = '2px solid red';
                this.alt = 'Lỗi load ảnh: ' + this.src;
            };
            img.onload = function() {
                console.log('Load ảnh thành công:', this.src);
                this.style.border = '2px solid green';
            };
        });
    </script>
</body>
</html>
