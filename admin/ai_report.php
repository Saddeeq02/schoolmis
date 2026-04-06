<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Verify admin role
if (!isAdmin()) {
    header("Location: ../index.php");
    exit();
}

try {
    // Get filter values
    $schoolId = isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;
    $timePeriod = isset($_GET['time_period']) ? $_GET['time_period'] : '30_days';

    // Calculate date range
    $dateCondition = '';
    switch ($timePeriod) {
        case '7_days':
            $dateCondition = "DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case '90_days':
            $dateCondition = "DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
            break;
        case 'year':
            $dateCondition = "DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
            break;
        default: // 30_days
            $dateCondition = "DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    }

    // Attendance Analytics
    $attendanceQuery = "
        SELECT 
            u.id, u.name, s.name as school_name,
            COUNT(DISTINCT DATE(a.clock_in)) as days_present,
            AVG(TIMESTAMPDIFF(SECOND, a.clock_in, a.clock_out)) as avg_seconds
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        JOIN schools s ON u.school_id = s.id
        WHERE a.clock_in >= $dateCondition
    ";
    if ($schoolId > 0) {
        $attendanceQuery .= " AND u.school_id = :school_id";
    }
    $attendanceQuery .= " GROUP BY u.id, u.name, s.name ORDER BY days_present DESC LIMIT 10";

    $stmt = $pdo->prepare($attendanceQuery);
    if ($schoolId > 0) {
        $stmt->bindValue(':school_id', $schoolId, PDO::PARAM_INT);
    }
    $stmt->execute();
    $attendanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recordings Analytics
    $recordingsQuery = "
        SELECT 
            u.id, u.name, s.name as school_name,
            COUNT(r.id) as recording_count,
            GROUP_CONCAT(DISTINCT sub.subject_name) as subjects
        FROM recordings r
        JOIN users u ON r.teacher_id = u.id
        JOIN schools s ON u.school_id = s.id
        JOIN subjects sub ON r.subject_id = sub.id
        WHERE r.created_at >= $dateCondition
    ";
    if ($schoolId > 0) {
        $recordingsQuery .= " AND u.school_id = :school_id";
    }
    $recordingsQuery .= " GROUP BY u.id, u.name, s.name ORDER BY recording_count DESC LIMIT 10";

    $stmt = $pdo->prepare($recordingsQuery);
    if ($schoolId > 0) {
        $stmt->bindValue(':school_id', $schoolId, PDO::PARAM_INT);
    }
    $stmt->execute();
    $recordingsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // School Health Metrics
    $healthQuery = "
        SELECT 
            COUNT(DISTINCT u.id) as total_staff,
            COUNT(DISTINCT DATE(a.clock_in)) as total_attendance_days,
            (COUNT(DISTINCT a.user_id) / COUNT(DISTINCT u.id) * 100) as engagement_rate
        FROM users u
        LEFT JOIN attendance a ON u.id = a.user_id AND a.clock_in >= $dateCondition
        WHERE u.role IN ('teacher', 'admin')
    ";
    if ($schoolId > 0) {
        $healthQuery .= " AND u.school_id = :school_id";
    }
    $stmt = $pdo->prepare($healthQuery);
    if ($schoolId > 0) {
        $stmt->bindValue(':school_id', $schoolId, PDO::PARAM_INT);
    }
    $stmt->execute();
    $healthData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get all schools for filter
    $schools = $pdo->query("SELECT id, name FROM schools ORDER BY name")->fetchAll();

} catch (PDOException $e) {
    error_log("AI Report Error: " . $e->getMessage());
    $error = "Database error occurred: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-Powered Performance Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/clean-styles.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #2ecc71;
            --secondary: #27ae60;
            --accent: #f1c40f;
            --dark: #2c3e50;
            --light: #f8f9fc;
        }

        body {
            background: linear-gradient(135deg, #e9ecef, #f8f9fc);
            font-family: 'Roboto', sans-serif;
        }

        .container {
            max-width: 1500px;
            padding: 2rem;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 1.5rem;
            font-weight: 700;
        }

        .nav-tabs {
            border: none;
            background: var(--light);
            border-radius: 8px;
            padding: 0.5rem;
        }

        .nav-tabs .nav-link {
            color: var(--dark);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .nav-tabs .nav-link.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .filter-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .badge-top {
            background: var(--accent);
            color: var(--dark);
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 20px;
        }

        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 2rem;
        }

        .school-health {
            background: linear-gradient(135deg, #ffffff, #f1f3f5);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .btn-export {
            background: var(--secondary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .btn-export:hover {
            background: darken(var(--secondary), 10%);
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .filter-group {
                grid-template-columns: 1fr;
            }

            .chart-container {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="fw-bold" style="color: var(--primary);">AI-Powered Performance Dashboard</h1>
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="filters">
            <form method="GET" action="" id="filterForm">
                <div class="filter-group">
                    <div>
                        <label class="fw-bold">School</label>
                        <select name="school_id" id="schoolSelect" class="form-select">
                            <option value="0">All Schools</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?= $school['id'] ?>" <?= $schoolId == $school['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($school['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="fw-bold">Time Period</label>
                        <select name="time_period" class="form-select">
                            <option value="7_days" <?= $timePeriod == '7_days' ? 'selected' : '' ?>>Last 7 Days</option>
                            <option value="30_days" <?= $timePeriod == '30_days' ? 'selected' : '' ?>>Last 30 Days</option>
                            <option value="90_days" <?= $timePeriod == '90_days' ? 'selected' : '' ?>>Last 90 Days</option>
                            <option value="year" <?= $timePeriod == 'year' ? 'selected' : '' ?>>Last Year</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Apply Filters</button>
            </form>
        </div>

        <ul class="nav nav-tabs mb-4" id="analyticsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">Attendance Analytics</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="recordings-tab" data-bs-toggle="tab" data-bs-target="#recordings" type="button" role="tab">Recordings Analytics</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="health-tab" data-bs-toggle="tab" data-bs-target="#health" type="button" role="tab">School Health</button>
            </li>
        </ul>

        <div class="tab-content" id="analyticsTabContent">
            <!-- Attendance Analytics -->
            <div class="tab-pane fade show active" id="attendance" role="tabpanel">
                <div class="card mb-4">
                    <div class="card-header">
                        Attendance Performance
                        <button class="btn btn-export float-end" onclick="exportToCSV('attendance')">Export to CSV</button>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Top Performers</h5>
                                <ul class="list-group">
                                    <?php foreach ($attendanceData as $index => $row): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= htmlspecialchars($row['name']) ?></strong> (<?= htmlspecialchars($row['school_name']) ?>)
                                                <br>
                                                <small><?= $row['days_present'] ?> days present, Avg. <?= gmdate('H:i', $row['avg_seconds']) ?> hours/day</small>
                                            </div>
                                            <?php if ($index < 3): ?>
                                                <span class="badge-top">Top #<?= $index + 1 ?></span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <div class="chart-container">
                                    <canvas id="attendanceChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recordings Analytics -->
            <div class="tab-pane fade" id="recordings" role="tabpanel">
                <div class="card mb-4">
                    <div class="card-header">
                        Recordings Performance
                        <button class="btn btn-export float-end" onclick="exportToCSV('recordings')">Export to CSV</button>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Top Contributors</h5>
                                <ul class="list-group">
                                    <?php foreach ($recordingsData as $index => $row): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= htmlspecialchars($row['name']) ?></strong> (<?= htmlspecialchars($row['school_name']) ?>)
                                                <br>
                                                <small><?= $row['recording_count'] ?> recordings, Subjects: <?= htmlspecialchars($row['subjects']) ?></small>
                                            </div>
                                            <?php if ($index < 3): ?>
                                                <span class="badge-top">Top #<?= $index + 1 ?></span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <div class="chart-container">
                                    <canvas id="recordingsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- School Health -->
            <div class="tab-pane fade" id="health" role="tabpanel">
                <div class="school-health">
                    <h3>School Health Overview</h3>
                    <p class="text-muted">A snapshot of staff engagement and performance metrics.</p>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h4><?= $healthData['total_staff'] ?></h4>
                                    <p>Total Staff</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h4><?= round($healthData['engagement_rate'], 1) ?>%</h4>
                                    <p>Staff Engagement Rate</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h4><?= $healthData['total_attendance_days'] ?></h4>
                                    <p>Total Attendance Days</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h5>Health Insights</h5>
                        <p>
                            The school demonstrates a staff engagement rate of <?= round($healthData['engagement_rate'], 1) ?>%, 
                            indicating <?php echo $healthData['engagement_rate'] > 80 ? 'excellent' : ($healthData['engagement_rate'] > 50 ? 'good' : 'room for improvement'); ?> 
                            participation. Consistent attendance and recording activity suggest a healthy academic environment. 
                            Focus on encouraging less active staff to boost overall performance.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="../assets/clean-styles.css">

    <script>
        // Attendance Chart
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(attendanceCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($attendanceData, 'name')) . "'"; ?>],
                datasets: [{
                    label: 'Days Present',
                    data: [<?php echo implode(',', array_column($attendanceData, 'days_present')); ?>],
                    backgroundColor: 'rgba(78, 115, 223, 0.6)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Recordings Chart
        const recordingsCtx = document.getElementById('recordingsChart').getContext('2d');
        new Chart(recordingsCtx, {
            type: 'pie',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($recordingsData, 'name')) . "'"; ?>],
                datasets: [{
                    label: 'Recordings',
                    data: [<?php echo implode(',', array_column($recordingsData, 'recording_count')); ?>],
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#f6c23e', '#e74a3b', '#36b9cc',
                        '#858796', '#5a5c69', '#f8f9fc', '#d1d3e2', '#b7b9cc'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Export to CSV
        function exportToCSV(type) {
            let data, headers;
            if (type === 'attendance') {
                headers = ['Name', 'School', 'Days Present', 'Avg Hours/Day'];
                data = <?php echo json_encode($attendanceData); ?>.map(row => [
                    row.name,
                    row.school_name,
                    row.days_present,
                    new Date(row.avg_seconds * 1000).toISOString().substr(11, 8)
                ]);
            } else if (type === 'recordings') {
                headers = ['Name', 'School', 'Recording Count', 'Subjects'];
                data = <?php echo json_encode($recordingsData); ?>.map(row => [
                    row.name,
                    row.school_name,
                    row.recording_count,
                    row.subjects
                ]);
            }

            let csv = headers.join(',') + '\n';
            data.forEach(row => {
                csv += row.map(cell => `"${cell.replace(/"/g, '""')}"`).join(',') + '\n';
            });

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `ai_report_${type}_${new Date().toISOString().slice(0, 10)}.csv`;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>