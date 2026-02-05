<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Employee Report - {{ $employee->name }}</title>
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
        .info {
            margin-bottom: 20px;
        }
        .info table {
            width: 100%;
            border-collapse: collapse;
        }
        .info table td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        .info table td:first-child {
            font-weight: bold;
            background: #f5f5f5;
            width: 40%;
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
        <h1>Employee Report</h1>
        <p><strong>Employee:</strong> {{ $employee->name }} ({{ $employee->email }})</p>
        <p><strong>Period:</strong> {{ ucfirst($period) }} - {{ $data['period'] ?? '' }}</p>
        <p><strong>Generated:</strong> {{ $generated_at->format('Y-m-d H:i:s') }}</p>
    </div>

    <div class="info">
        <table>
            <tr>
                <td>Projects Assigned</td>
                <td>{{ $data['projects_assigned'] ?? 0 }}</td>
            </tr>
            <tr>
                <td>Attendance (Present Days)</td>
                <td>{{ $data['attendance_present'] ?? 0 }}</td>
            </tr>
            <tr>
                <td>Total Hours Worked</td>
                <td>{{ $data['total_hours'] ?? 0 }} hours</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>GTmanagement System - Generated on {{ $generated_at->format('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>
