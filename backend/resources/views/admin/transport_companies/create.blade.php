<!DOCTYPE html>
<html>
<head>
    <title>Thêm hãng xe</title>
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
    <h1>Thêm hãng xe</h1>

    <form method="POST" action="{{ route('admin.transport_companies.store') }}">
        @csrf

        <label>Transportation ID:</label><br>
        <input type="number" name="transportation_id" required><br><br>

        <label>Tên hãng:</label><br>
        <input type="text" name="name" required><br><br>

        <label>Thông tin liên hệ:</label><br>
        <input type="text" name="contact_info" required><br><br>

        <label>Địa chỉ:</label><br>
        <input type="text" name="address" required><br><br>

        <label>Latitude:</label><br>
        <input type="text" name="latitude" required><br><br>

        <label>Longitude:</label><br>
        <input type="text" name="longitude" required><br><br>

        <label>Mô tả:</label><br>
        <textarea name="description"></textarea><br><br>

        <label>Logo:</label><br>
        <input type="text" name="logo"><br><br>

        <label>Giờ hoạt động (JSON):</label><br>
        <textarea name="operating_hours"></textarea><br><br>

        <label>Rating:</label><br>
        <input type="number" step="0.1" name="rating"><br><br>

        <button type="submit">Thêm mới</button>
    </form>

    <a href="{{ route('admin.transport_companies.index') }}">Quay lại danh sách</a>
</body>
</html>
