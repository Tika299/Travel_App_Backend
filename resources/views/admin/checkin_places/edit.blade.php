<!DOCTYPE html>
<html>
<head>
    <title>Chỉnh sửa Check-in Place</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        form { max-width: 600px; }
        label { font-weight: bold; display: block; margin-top: 15px; }
        input[type="text"], input[type="number"], textarea {
            width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box;
        }
        button {
            margin-top: 20px; padding: 10px 20px; font-size: 16px; background-color: #28a745; color: white; border: none; border-radius: 5px;
        }
        a { display: inline-block; margin-top: 20px; text-decoration: none; }
    </style>
</head>
<body>

    <h1>Chỉnh sửa Check-in Place</h1>

    <form action="{{ route('admin.checkin_places.update', $checkinPlace->id) }}" method="POST">
        @csrf
        @method('PUT')

        <label>Tên:</label>
        <input type="text" name="name" value="{{ $checkinPlace->name }}" required>

        <label>Mô tả:</label>
        <textarea name="description">{{ $checkinPlace->description }}</textarea>

        <label>Địa chỉ:</label>
        <input type="text" name="address" value="{{ $checkinPlace->address }}">

        <label>Latitude:</label>
        <input type="text" name="latitude" value="{{ $checkinPlace->latitude }}">

        <label>Longitude:</label>
        <input type="text" name="longitude" value="{{ $checkinPlace->longitude }}">

        <label>Hình ảnh (URL hoặc upload sau):</label>
        <input type="text" name="image" value="{{ $checkinPlace->image }}">

        <label>Rating:</label>
        <input type="number" step="0.01" name="rating" value="{{ $checkinPlace->rating }}">

        <label>Location ID:</label>
        <input type="number" name="location_id" value="{{ $checkinPlace->location_id }}">

        <button type="submit">Cập nhật</button>
    </form>

    <a href="{{ route('admin.checkin_places.index') }}">← Quay lại danh sách</a>

</body>
</html>
