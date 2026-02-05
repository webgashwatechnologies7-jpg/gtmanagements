<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance Report - {{ $user->name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table th, table td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        table th {
            background: #f5f5f5;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Attendance Report</h1>
        <p><strong>Employee:</strong> {{ $user->name }} ({{ $user->email }})</p>
        <p><strong>Period:</strong> {{ $month }}/{{ $year }}</p>
        <p><strong>Generated:</strong> {{ $generated_at->format('Y-m-d H:i:s') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Status</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Hours</th>
            </tr>
        </thead>
        <tbody>
            @forelse($attendances as $attendance)
            <tr>
                <td>{{ $attendance->date->format('Y-m-d') }}</td>
                <td>{{ ucfirst($attendance->status) }}</td>
                <td>{{ $attendance->check_in ? $attendance->check_in->format('H:i') : '-' }}</td>
                <td>{{ $attendance->check_out ? $attendance->check_out->format('H:i') : '-' }}</td>
                <td>{{ $attendance->hours ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align: center;">No attendance records found</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>GTmanagement System - Generated on {{ $generated_at->format('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>
