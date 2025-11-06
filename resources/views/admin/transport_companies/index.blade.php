<!DOCTYPE html>
<html>
<head>
    <title>Danh sách Hãng Xe</title>
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
    <h1>Danh sách Hãng Xe</h1>

    <a href="{{ route('admin.transport_companies.create') }}">Thêm hãng xe mới</a>

    @if(session('success'))
        <p style="color: green;">{{ session('success') }}</p>
    @endif

    <table border="1" cellpadding="10" cellspacing="0">
        <thead>
            <tr>
                <th>ID</th>
                <th>Transportation ID</th>
                <th>Tên hãng</th>
                <th>Liên hệ</th>
                <th>Địa chỉ</th>
                <th>Latitude</th>
                <th>Longitude</th>
                <th>Rating</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transportCompanies as $company)
                <tr>
                    <td>{{ $company->id }}</td>
                    <td>{{ $company->transportation_id }}</td>
                    <td>{{ $company->name }}</td>
                    <td>{{ $company->contact_info }}</td>
                    <td>{{ $company->address }}</td>
                    <td>{{ $company->latitude }}</td>
                    <td>{{ $company->longitude }}</td>
                    <td>{{ $company->rating }}</td>
                    <td>
                        <a href="{{ route('admin.transport_companies.edit', $company->id) }}">Sửa</a> |
                        <form action="{{ route('admin.transport_companies.destroy', $company->id) }}" method="POST" style="display:inline-block;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit">Xóa</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
