<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Chia sẻ lịch trình</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }
        .content {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 0 0 10px 10px;
        }
        .event-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .event-title {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        .event-detail {
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .event-detail:last-child {
            border-bottom: none;
        }
        .icon {
            display: inline-block;
            width: 20px;
            margin-right: 10px;
            color: #667eea;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📅 Chia sẻ lịch trình</h1>
        <p>{{ $sender['name'] }} đã chia sẻ lịch trình với bạn</p>
    </div>
    
    <div class="content">
        <div class="event-card">
            <div class="event-title">{{ $event['title'] }}</div>
            
            <div class="event-detail">
                <span class="icon">📅</span>
                <strong>Ngày:</strong> {{ \Carbon\Carbon::parse($event['start'])->format('l, d/m/Y') }}
            </div>
            
            <div class="event-detail">
                <span class="icon">🕐</span>
                <strong>Thời gian:</strong> 
                {{ \Carbon\Carbon::parse($event['start'])->format('H:i') }} - 
                {{ \Carbon\Carbon::parse($event['end'])->format('H:i') }}
            </div>
            
            @if(isset($event['location']) && $event['location'])
            <div class="event-detail">
                <span class="icon">📍</span>
                <strong>Địa điểm:</strong> {{ $event['location'] }}
            </div>
            @endif
            
            @if(isset($event['description']) && $event['description'])
            <div class="event-detail">
                <span class="icon">📝</span>
                <strong>Mô tả:</strong> {{ $event['description'] }}
            </div>
            @endif
        </div>
        
        <div class="footer">
            <p>Email này được gửi từ hệ thống IPSUM TRAVEL</p>
            <p>Nếu bạn có thắc mắc, vui lòng liên hệ với chúng tôi</p>
        </div>
    </div>
</body>
</html>

