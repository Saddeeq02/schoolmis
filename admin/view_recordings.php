<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

if (!isAdmin()) {
    header("Location: ../index.php");
    exit();
}

try {
    // Get filter values
    $school = isset($_GET['school']) ? $_GET['school'] : '';
    $teacher_id = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : '';
    $class_id = isset($_GET['class_id']) ? $_GET['class_id'] : '';
    $subject_id = isset($_GET['subject_id']) ? $_GET['subject_id'] : '';
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    
    // Pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    // Base query
    $query = "
        SELECT 
            r.*,
            u.name as teacher_name,
            u.school_name,
            c.class_name,
            s.subject_name
        FROM recordings r
        INNER JOIN users u ON r.teacher_id = u.id AND u.role = 'teacher'
        INNER JOIN classes c ON r.class_id = c.id
        INNER JOIN subjects s ON r.subject_id = s.id
        WHERE 1=1
    ";

    // Count query for pagination
    $count_query = "SELECT COUNT(*) FROM recordings r
        INNER JOIN users u ON r.teacher_id = u.id AND u.role = 'teacher'
        INNER JOIN classes c ON r.class_id = c.id
        INNER JOIN subjects s ON r.subject_id = s.id
        WHERE 1=1";

    $params = [];

    // Add filters to query
    if ($school) {
        $query .= " AND u.school_name = :school";
        $count_query .= " AND u.school_name = :school";
        $params[':school'] = $school;
    }
    if ($teacher_id) {
        $query .= " AND r.teacher_id = :teacher_id";
        $count_query .= " AND r.teacher_id = :teacher_id";
        $params[':teacher_id'] = $teacher_id;
    }
    if ($class_id) {
        $query .= " AND r.class_id = :class_id";
        $count_query .= " AND r.class_id = :class_id";
        $params[':class_id'] = $class_id;
    }
    if ($subject_id) {
        $query .= " AND r.subject_id = :subject_id";
        $count_query .= " AND r.subject_id = :subject_id";
        $params[':subject_id'] = $subject_id;
    }
    if ($date_from) {
        $query .= " AND DATE(r.created_at) >= :date_from";
        $count_query .= " AND DATE(r.created_at) >= :date_from";
        $params[':date_from'] = $date_from;
    }
    if ($date_to) {
        $query .= " AND DATE(r.created_at) <= :date_to";
        $count_query .= " AND DATE(r.created_at) <= :date_to";
        $params[':date_to'] = $date_to;
    }

    // Get count first without LIMIT
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);

    // Add pagination to main query using named parameters
    $query .= " ORDER BY r.created_at DESC LIMIT :offset, :limit";
    
    // Add pagination parameters to the existing params array
    $params[':offset'] = $offset;
    $params[':limit'] = $per_page;
    
    // Prepare and execute main query
    $stmt = $pdo->prepare($query);
    
    // Bind all parameters including pagination
    foreach ($params as $key => $value) {
        if ($key === ':offset' || $key === ':limit') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    
    $stmt->execute();
    $recordings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug information
    error_log("Query executed: " . $query);
    error_log("Parameters: " . print_r($params, true));
    error_log("Total records: " . $total_records);
    error_log("Total pages: " . $total_pages);

    // Get filter options
    $schools = $pdo->query("SELECT DISTINCT school_name FROM users WHERE role = 'teacher' AND school_name IS NOT NULL ORDER BY school_name")->fetchAll();
    $teachers = $pdo->query("SELECT id, name, school_name FROM users WHERE role = 'teacher' ORDER BY name")->fetchAll();
    $classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name")->fetchAll();
    $subjects = $pdo->query("SELECT id, subject_name FROM subjects ORDER BY subject_name")->fetchAll();

} catch (PDOException $e) {
    error_log("Database Error in view_recordings.php: " . $e->getMessage());
    $error = "Database error occurred: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Recordings - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/styles.css">
    <!-- <style>
        /* Container */
        .container {
            padding: 15px;
            max-width: 100%;
            margin: 0 auto;
        }

        /* Filters */
        .filters {
            margin: 20px 0;
            padding: 15px;
            background: rgba(245, 245, 245, 0.9);
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }

        .filter-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }

        .filters select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
        }

        .date-filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }

        .date-filters input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            flex: 1;
            text-align: center;
            text-decoration: none;
            min-width: 120px;
        }

        .filter-btn:hover {
            background: #0056b3;
        }

        /* Table */
        .recordings-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .recordings-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }

        .recordings-table td {
            padding: 12px;
            border-top: 1px solid #dee2e6;
        }

        .audio-cell {
            max-width: 300px;
            padding: 15px !important;
        }

        audio {
            width: 100%;
            height: 40px;
            border-radius: 20px;
            background: #f0f0f0;
        }

        audio::-webkit-media-controls-panel {
            background: #f0f0f0;
        }

        .timestamp {
            white-space: nowrap;
            font-size: 14px;
        }

        .audio-info {
            display: block;
            margin-top: 5px;
            text-align: center;
        }

        .download-link {
            color: #007bff;
            text-decoration: none;
            font-size: 0.9em;
        }

        .download-link:hover {
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .recordings-table {
                display: block;
                overflow-x: auto;
            }

            .recordings-table thead {
                display: none; /* Hide headers on mobile */
            }

            .recordings-table tr {
                display: block;
                margin-bottom: 15px;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 10px;
            }

            .recordings-table td {
                display: block;
                text-align: left;
                padding: 8px;
                border: none;
                position: relative;
                padding-left: 120px;
                min-height: 40px;
            }

            .recordings-table td:before {
                content: attr(data-label);
                position: absolute;
                left: 8px;
                width: 100px;
                font-weight: bold;
            }

            .audio-cell {
                max-width: none;
                padding-left: 8px !important;
            }

            .filter-buttons {
                flex-direction: column;
            }

            .filter-btn {
                width: 100%;
            }

            .recordings-count {
                text-align: center;
                margin: 15px 0;
            }

            .audio-cell {
                padding: 15px !important;
                max-width: none;
                width: 100%;
            }
        }

        /* Utility Classes */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .alert-error {
            background: #ff4444;
            color: white;
        }

        .pagination {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }

        .page-link {
            padding: 8px 12px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            text-decoration: none;
            color: #007bff;
        }

        .page-link.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
    </style> -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body>
    <div class="container">
        <h1>Recorded Classes</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php else: ?>

            <!-- Enhanced Filters -->
            <div class="filters">
                <form method="GET" action="" id="filterForm">
                    <div class="filter-group">
                        <select name="school">
                            <option value="">All Schools</option>
                            <?php foreach ($schools as $s): ?>
                                <option value="<?= htmlspecialchars($s['school_name']) ?>" 
                                    <?= $school === $s['school_name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['school_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="teacher_id">
                            <option value="">All Teachers</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?= $t['id'] ?>" 
                                    <?= $teacher_id == $t['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="class_id">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>" 
                                    <?= $class_id == $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['class_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="subject_id">
                            <option value="">All Subjects</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?= $s['id'] ?>" 
                                    <?= $subject_id == $s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['subject_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="date-filters">
                        <input type="text" name="date_from" class="datepicker" placeholder="From Date" value="<?= htmlspecialchars($date_from) ?>">
                        <input type="text" name="date_to" class="datepicker" placeholder="To Date" value="<?= htmlspecialchars($date_to) ?>">
                    </div>

                    <div class="filter-buttons">
                        <button type="submit" class="filter-btn">Apply Filters</button>
                        <a href="view_recordings.php" class="filter-btn">Reset</a>
                    </div>
                </form>
            </div>

            <div class="recordings-count">
                Total Recordings: <?= count($recordings) ?>
            </div>

            <table class="recordings-table">
                <thead>
                    <tr>
                        <th>School</th>
                        <th>Teacher</th>
                        <th>Class</th>
                        <th>Subject</th>
                        <th>Recording</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recordings)): ?>
                        <?php foreach ($recordings as $recording): ?>
                            <tr>
                                <td data-label="School"><?= htmlspecialchars($recording['school_name']) ?></td>
                                <td data-label="Teacher"><?= htmlspecialchars($recording['teacher_name']) ?></td>
                                <td data-label="Class"><?= htmlspecialchars($recording['class_name']) ?></td>
                                <td data-label="Subject"><?= htmlspecialchars($recording['subject_name']) ?></td>
                                <td class="audio-cell">
                                    <audio controls preload="none">
                                        <source src="../<?= htmlspecialchars($recording['recording_path']) ?>" type="audio/webm;codecs=opus">
                                        <source src="../<?= htmlspecialchars($recording['recording_path']) ?>" type="audio/webm">
                                        Download: <a href="../<?= htmlspecialchars($recording['recording_path']) ?>" download>Audio File</a>
                                    </audio>
                                    <small class="audio-info">
                                        <a href="../<?= htmlspecialchars($recording['recording_path']) ?>" target="_blank" class="download-link">
                                            Download Recording
                                        </a>
                                    </small>
                                </td>
                                <td data-label="Start Time">
                                    <?= date(DATETIME_FORMAT, strtotime($recording['start_time'] . ' UTC')) ?>
                                </td>
                                <td data-label="End Time">
                                    <?= date(DATETIME_FORMAT, strtotime($recording['end_time'] . ' UTC')) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">No recordings found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination">
                <?php if ($total_pages > 1): ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>&<?= http_build_query(array_filter([
                            'school' => $school,
                            'teacher_id' => $teacher_id,
                            'class_id' => $class_id,
                            'subject_id' => $subject_id,
                            'date_from' => $date_from,
                            'date_to' => $date_to
                        ])) ?>" 
                        class="page-link <?= $page === $i ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                <?php endif; ?>
            </div>

        <?php endif; ?>
        
        <p><a href="dashboard.php">&larr; Back to Dashboard</a></p>
    </div>

    <script>
        // Initialize date pickers
        flatpickr(".datepicker", {
            dateFormat: "Y-m-d",
            allowInput: true
        });

        // Quick date filters
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);

            // Add quick filter buttons if needed
            const quickFilters = {
                'today': today,
                'yesterday': yesterday
            };
        });

        // Auto-pause other players when one starts playing
        document.addEventListener('play', function(e) {
            if(e.target.tagName.toLowerCase() === 'audio') {
                const audios = document.getElementsByTagName('audio');
                for(let i = 0; i < audios.length; i++) {
                    if(audios[i] !== e.target) {
                        audios[i].pause();
                    }
                }
            }
        }, true);
    </script>
</body>
</html>