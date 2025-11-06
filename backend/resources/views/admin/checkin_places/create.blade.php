<!DOCTYPE html>
<html>
<head>
    <title>Thêm Check-in Place mới</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        form { max-width: 600px; }
        label { font-weight: bold; display: block; margin-top: 15px; }
        input[type="text"], input[type="number"], textarea {
            width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box;
        }
        button {
            margin-top: 20px; padding: 10px 20px; font-size: 16px; background-color: #007bff; color: white; border: none; border-radius: 5px;
        }
        a { display: inline-block; margin-top: 20px; text-decoration: none; }
    </style>
</head>
<body>

    <h1>Thêm Check-in Place mới</h1>

    <form action="{{ route('admin.checkin_places.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <label>Tên:</label>
        <input type="text" name="name" required>

        <label>Mô tả:</label>
        <textarea name="description"></textarea>

        <label>Địa chỉ:</label>
        <input type="text" name="address">

        <label>Latitude:</label>
        <input type="text" name="latitude">

        <label>Longitude:</label>
        <input type="text" name="longitude">

        <label>Hình ảnh (URL hoặc upload sau):</label>
        <input type="text" name="image">

        <label>Rating:</label>
        <input type="number" step="0.01" name="rating" value="0.00">

        <label>Location ID:</label>
        <input type="number" name="location_id">

        <button type="submit">Lưu</button>
    </form>

    <a href="{{ route('admin.checkin_places.index') }}">← Quay lại danh sách</a>

</body>
</html>
