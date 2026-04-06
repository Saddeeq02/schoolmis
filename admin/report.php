<?php
// Enable error reporting for debugging
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
// ini_set('log_errors', 1);
// ini_set('error_log', dirname(__DIR__) . '/error.log');

// Add error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error [$errno] $errstr on line $errline in file $errfile");
    return false;
});

try {
    include '../includes/auth.php';
    include '../includes/db.php';

    // Verify admin or teacher role
    if (!in_array($_SESSION['role'], ['admin', 'teacher'])) {
        header("Location: ../unauthorized.php");
        exit;
    }
    // Get attendance records
    $query = "SELECT u.name, s.name as school_name, a.clock_in, a.clock_out 
              FROM attendance a 
              JOIN users u ON a.user_id = u.id
              JOIN schools s ON u.school_id = s.id";
              
    // If teacher, only show their school's records
    if ($_SESSION['role'] === 'teacher') {
        $query .= " WHERE a.school_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(1, $_SESSION['school_id'], PDO::PARAM_INT);
    } else {
        $stmt = $pdo->prepare($query);
    }

    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Report.php Error: " . $e->getMessage());
    die("An error occurred: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance Reports</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/clean-styles.css">


    
    <style>
        .recordings-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .recordings-table thead {
            background: linear-gradient(135deg,rgb(19, 55, 139),rgb(23, 48, 24));
            color: violet;
        }

        .recordings-table th {
            color: blue;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            padding: 1.2rem 1rem;
            letter-spacing: 0.5px;
            border: none;
            text-align: left;
            white-space: nowrap;
        }

        .recordings-table td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            color: #333;
            font-size: 0.9rem;
        }

        .recordings-table tbody tr:hover {
            background-color: #fff8f3;
        }

        .recordings-table tbody tr:last-child td {
            border-bottom: none;
        }

        .badge {
            padding: 0.5em 0.8em;
            border-radius: 30px;
            font-weight: 500;
            font-size: 0.75rem;
        }

        .badge.bg-warning {
            background-color: #FFF3CD !important;
            color: #856404;
            border: 1px solid #FFEEBA;
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

        .form-group label {
            color:rgb(19, 89, 139);
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 0.5rem;
            width: 100%;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color:rgb(19, 23, 139);
            outline: none;
            box-shadow: 0 0 0 2px rgba(139, 69, 19, 0.1);
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-success {
            background:rgb(19, 41, 139);
            border: none;
            color: white;
        }

        .btn-success:hover {
            background:rgb(19, 41, 139);
            transform: translateY(-1px);
        }

        .btn-success a {
            color: white;
            text-decoration: none;
        }

        .container {
            padding: 2rem;
            max-width: 1400px;
        }

        @media (max-width: 768px) {
            .filter-group {
                grid-template-columns: 1fr;
            }
            
            .recordings-table {
                display: block;
                overflow-x: auto;
            }

            .container {
                padding: 1rem;
            }
        }
        .menu-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,.04);
            transition: all 0.3s;
            text-decoration: none;
            color: #212529;
            height: 100%;
        }
        .menu-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,.08);
            color: #0d6efd;
        }
        .menu-icon {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        .school-info {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,.04);
        }
        .school-logo {
            max-height: 60px;
            max-width: 100%;
        }
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 -2px 10px rgba(0,0,0,.1);
            z-index: 1000;
            display: none;
        }
        @media (max-width: 768px) {
            .bottom-nav {
                display: flex;
            }
            .container {
                margin-bottom: 70px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 style="color:rgb(61, 41, 196); font-weight: 700; margin: 0;">Attendance Records</h2>
            <div class="d-flex gap-2">
                <button id="exportBtn" class="btn btn-success">
                    <i class="fas fa-download"></i> Export to CSV
                </button>
                <a href="./dashboard.php" class="btn btn-success">
                    <i class="fas fa-home"></i> Back to Home
                </a>
            </div>
        </div>
        
        <div class="filters">
            <div class="filter-group">
                <div class="form-group">
                    <label for="nameFilter">Filter by Name</label>
                    <input type="text" id="nameFilter" class="form-control" placeholder="Enter name">
                </div>
                <div class="form-group">
                    <label for="dateFilter">Filter by Date</label>
                    <input type="date" id="dateFilter" class="form-control">
                </div>
                <div class="form-group">
                    <label for="timeFilter">Filter by Clock-in Time</label>
                    <input type="time" id="timeFilter" class="form-control">
                </div>
                <div class="form-group">
                    <label for="schoolFilter">Filter by School</label>
                    <input type="text" id="schoolFilter" class="form-control" placeholder="Enter school name">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="recordings-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>School</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['school_name']) ?></td>
                            <td><?= $row['clock_in'] ?></td>
                            <td><?= $row['clock_out'] ?? 'N/A' ?></td>
                            <td>
                                <?php if ($row['clock_out']): 
                                    $diff = strtotime($row['clock_out']) - strtotime($row['clock_in']);
                                    echo gmdate('H:i:s', $diff);
                                else: ?>
                                    <span class="badge bg-warning">Still clocked in</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.getElementById('dateFilter').addEventListener('change', filterTable);
        document.getElementById('timeFilter').addEventListener('change', filterTable);
        document.getElementById('schoolFilter').addEventListener('input', filterTable);
        document.getElementById('nameFilter').addEventListener('input', filterTable);
        document.getElementById('exportBtn').addEventListener('click', exportToCSV);

        function filterTable() {
            const dateFilter = document.getElementById('dateFilter').value;
            const timeFilter = document.getElementById('timeFilter').value;
            const schoolFilter = document.getElementById('schoolFilter').value.toLowerCase();
            const nameFilter = document.getElementById('nameFilter').value.toLowerCase();
            const rows = document.querySelectorAll('.recordings-table tbody tr');

            rows.forEach(row => {
                const schoolName = row.children[1].textContent.toLowerCase();
                const clockIn = row.children[2].textContent;
                const name = row.children[0].textContent.toLowerCase();
                
                let dateMatch = true;
                let timeMatch = true;
                let nameMatch = true;
                
                if (dateFilter) {
                    const rowDate = clockIn.split(' ')[0];
                    dateMatch = rowDate === dateFilter;
                }
                
                if (timeFilter) {
                    const rowTime = clockIn.split(' ')[1];
                    timeMatch = rowTime >= timeFilter;
                }
                
                const schoolMatch = schoolFilter ? schoolName.includes(schoolFilter) : true;
                nameMatch = name.includes(nameFilter);

                row.style.display = (dateMatch && timeMatch && schoolMatch && nameMatch) ? '' : 'none';
            });
        }

        function exportToCSV() {
            const table = document.querySelector('.recordings-table');
            const rows = table.querySelectorAll('tr:not([style*="display: none"])');
            let csv = [];
            
            // Add headers
            const headers = ['Name', 'School', 'Clock In', 'Clock Out', 'Duration'];
            csv.push(headers.join(','));
            
            // Add visible rows
            rows.forEach((row, index) => {
                if (index === 0) return; // Skip header row
                const cells = row.querySelectorAll('td');
                const rowData = Array.from(cells).map(cell => {
                    // Escape commas and quotes in the content
                    let content = cell.textContent.trim();
                    if (content.includes(',') || content.includes('"')) {
                        content = `"${content.replace(/"/g, '""')}"`;
                    }
                    return content;
                });
                csv.push(rowData.join(','));
            });
            
            // Create and trigger download
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const timestamp = new Date().toISOString().slice(0, 10);
            
            link.href = URL.createObjectURL(blob);
            link.download = `attendance_report_${timestamp}.csv`;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
