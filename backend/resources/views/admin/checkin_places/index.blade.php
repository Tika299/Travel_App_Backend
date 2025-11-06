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
    <h1>Danh sách Check-in Places</h1>

    <a href="{{ route('admin.checkin_places.create') }}" style="padding: 8px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;">Thêm mới</a>

    <table border="1" cellpadding="10" cellspacing="0" style="margin-top: 20px; width: 100%; border-collapse: collapse;">
        <thead style="background-color: #f8f8f8;">
            <tr>
                <th>ID</th>
                <th>Tên</th>
                <th>Địa chỉ</th>
                <th>Latitude</th>
                <th>Longitude</th>
                <th>Rating</th>
                <th>Location ID</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
            @foreach($checkinPlaces as $place)
                <tr>
                    <td>{{ $place->id }}</td>
                    <td>{{ $place->name }}</td>
                    <td>{{ $place->address }}</td>
                    <td>{{ $place->latitude }}</td>
                    <td>{{ $place->longitude }}</td>
                    <td>{{ $place->rating }}</td>
                    <td>{{ $place->location_id }}</td>
                    <td>
                        <a href="{{ route('admin.checkin_places.edit', $place->id) }}" style="padding: 5px 10px; background-color: orange; color: white; border-radius: 3px;">Sửa</a>

                        <form action="{{ route('admin.checkin_places.destroy', $place->id) }}" method="POST" style="display: inline-block;" onsubmit="return confirm('Bạn có chắc muốn xoá?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="padding: 5px 10px; background-color: red; color: white; border: none; border-radius: 3px;">Xoá</button>
                        </form>
                    </td>
                </tr>
            @endforeach

            @if($checkinPlaces->isEmpty())
                <tr>
                    <td colspan="8" style="text-align: center;">Không có dữ liệu</td>
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>