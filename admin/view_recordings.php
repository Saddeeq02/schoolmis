<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/config.php';

if (!isAdmin()) {
    header("Location: ../index.php");
    exit();
}

try {
    // Get filter values
    $schoolId = isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;
    $teacherId = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
    $classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
    $subjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
    $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $timeFrom = isset($_GET['time_from']) ? $_GET['time_from'] : '';
    $timeTo = isset($_GET['time_to']) ? $_GET['time_to'] : '';
    
    // Pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 10;
    $offset = ($page - 1) * $perPage;

    // Base query
    $query = "
        SELECT 
            r.*,
            u.name as teacher_name,
            s.name as school_name,
            c.class_name,
            sub.subject_name
        FROM recordings r
        INNER JOIN users u ON r.teacher_id = u.id
        INNER JOIN schools s ON u.school_id = s.id
        INNER JOIN classes c ON r.class_id = c.id
        INNER JOIN subjects sub ON r.subject_id = sub.id
        WHERE 1=1
    ";

    // Count query for pagination
    $countQuery = "
        SELECT COUNT(*) 
        FROM recordings r
        INNER JOIN users u ON r.teacher_id = u.id
        INNER JOIN schools s ON u.school_id = s.id
        INNER JOIN classes c ON r.class_id = c.id
        INNER JOIN subjects sub ON r.subject_id = sub.id
        WHERE 1=1
    ";

    $params = [];

    // Add filters to query
    if ($schoolId > 0) {
        $query .= " AND u.school_id = :school_id";
        $countQuery .= " AND u.school_id = :school_id";
        $params[':school_id'] = $schoolId;
    }
    
    if ($teacherId > 0) {
        $query .= " AND r.teacher_id = :teacher_id";
        $countQuery .= " AND r.teacher_id = :teacher_id";
        $params[':teacher_id'] = $teacherId;
    }
    
    if ($classId > 0) {
        $query .= " AND r.class_id = :class_id";
        $countQuery .= " AND r.class_id = :class_id";
        $params[':class_id'] = $classId;
    }
    
    if ($subjectId > 0) {
        $query .= " AND r.subject_id = :subject_id";
        $countQuery .= " AND r.subject_id = :subject_id";
        $params[':subject_id'] = $subjectId;
    }

    // Date and time filters
    if ($dateFrom) {
        $query .= " AND DATE(r.created_at) >= :date_from";
        $countQuery .= " AND DATE(r.created_at) >= :date_from";
        $params[':date_from'] = $dateFrom;
    }
    
    if ($dateTo) {
        $query .= " AND DATE(r.created_at) <= :date_to";
        $countQuery .= " AND DATE(r.created_at) <= :date_to";
        $params[':date_to'] = $dateTo;
    }
    
    if ($timeFrom) {
        $query .= " AND TIME(r.created_at) >= :time_from";
        $countQuery .= " AND TIME(r.created_at) >= :time_from";
        $params[':time_from'] = $timeFrom;
    }
    
    if ($timeTo) {
        $query .= " AND TIME(r.created_at) <= :time_to";
        $countQuery .= " AND TIME(r.created_at) <= :time_to";
        $params[':time_to'] = $timeTo;
    }

    // Get total count for pagination
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalRecords = $stmt->fetchColumn();
    $totalPages = ceil($totalRecords / $perPage);

    // Add sorting and pagination to main query
    $query .= " ORDER BY r.created_at DESC LIMIT :offset, :limit";
    $params[':offset'] = $offset;
    $params[':limit'] = $perPage;

    // Get recordings
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        if (in_array($key, [':offset', ':limit'])) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->execute();
    $recordings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get filter options
    // Get all schools
    $schools = $pdo->query("SELECT id, name FROM schools ORDER BY name")->fetchAll();
    
    // Get teachers based on selected school
    $teacherQuery = "SELECT id, name FROM users WHERE role = 'teacher'";
    if ($schoolId > 0) {
        $teacherQuery .= " AND school_id = " . $schoolId;
    }
    $teacherQuery .= " ORDER BY name";
    $teachers = $pdo->query($teacherQuery)->fetchAll();
    
    // Get classes
    $classQuery = "SELECT id, class_name FROM classes";
    if ($schoolId > 0) {
        $classQuery .= " WHERE school_id = " . $schoolId;
    }
    $classQuery .= " ORDER BY class_name";
    $classes = $pdo->query($classQuery)->fetchAll();
    
    // Get subjects
    $subjectQuery = "SELECT id, subject_name FROM subjects";
    if ($schoolId > 0) {
        $subjectQuery .= " WHERE school_id = " . $schoolId;
    }
    $subjectQuery .= " ORDER BY subject_name";
    $subjects = $pdo->query($subjectQuery)->fetchAll();

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
    <title>View Recordings - School MIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/clean-styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .filters {
            background: var(--white);
            padding: var(--spacing-md);
            border-radius: var(--radius);
            margin-bottom: var(--spacing-lg);
        }
        
        .filter-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-md);
        }
        
        .time-filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: var(--spacing-sm);
        }
        
        .recordings-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .recordings-table th,
        .recordings-table td {
            padding: var(--spacing-sm);
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        .recordings-table th {
            background: var(--light);
            font-weight: 600;
        }
        
        .audio-cell {
            min-width: 250px;
        }
        
        .audio-cell audio {
            width: 100%;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            padding: 1rem;
            z-index: 1000;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            width: 100%;
            max-width: 500px;
        }
        
        @media (max-width: 768px) {
            .filter-group {
                grid-template-columns: 1fr;
            }
            
            .recordings-table {
                display: block;
                overflow-x: auto;
            }
            
            .modal-content {
                margin: 1rem;
                max-height: 90vh;
                overflow-y: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="mb-0">View Recordings</h1>
                <a href="dashboard.php" class="btn">
                    <i class="fas fa-arrow-left"></i>
                    <span class="d-none d-md-inline ml-2">Back</span>
                </a>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="filters">
            <form method="GET" action="" id="filterForm">
                <div class="filter-group">
                    <div>
                        <label>School</label>
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
                        <label>Teacher</label>
                        <select name="teacher_id" id="teacherSelect" class="form-select">
                            <option value="0">All Teachers</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?= $teacher['id'] ?>" <?= $teacherId == $teacher['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($teacher['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Class</label>
                        <select name="class_id" id="classSelect" class="form-select">
                            <option value="0">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= $class['id'] ?>" <?= $classId == $class['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($class['class_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Subject</label>
                        <select name="subject_id" id="subjectSelect" class="form-select">
                            <option value="0">All Subjects</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= $subject['id'] ?>" <?= $subjectId == $subject['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($subject['subject_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="filter-group">
                    <div>
                        <label>Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                    </div>
                    
                    <div>
                        <label>Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                    </div>
                    
                    <div>
                        <label>Time From</label>
                        <input type="time" name="time_from" class="form-control" value="<?= htmlspecialchars($timeFrom) ?>">
                    </div>
                    
                    <div>
                        <label>Time To</label>
                        <input type="time" name="time_to" class="form-control" value="<?= htmlspecialchars($timeTo) ?>">
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="view_recordings.php" class="btn">Reset</a>
                </div>
            </form>
        </div>

        <div class="card mb-4">
            <div class="table-responsive">
                <table class="recordings-table">
                    <thead>
                        <tr>
                            <th>School</th>
                            <th>Teacher</th>
                            <th>Class</th>
                            <th>Subject</th>
                            <th>Recording</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recordings)): ?>
                            <?php foreach ($recordings as $recording): ?>
                                <tr>
                                    <td><?= htmlspecialchars($recording['school_name']) ?></td>
                                    <td><?= htmlspecialchars($recording['teacher_name']) ?></td>
                                    <td><?= htmlspecialchars($recording['class_name']) ?></td>
                                    <td><?= htmlspecialchars($recording['subject_name']) ?></td>
                                    <td class="audio-cell">
                                        <audio controls preload="none">
                                            <source src="../<?= htmlspecialchars($recording['recording_path']) ?>" type="audio/webm">
                                            Your browser does not support the audio element.
                                        </audio>
                                        <div class="text-center mt-2">
                                            <a href="../<?= htmlspecialchars($recording['recording_path']) ?>" 
                                               download 
                                               class="btn btn-sm">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                        </div>
                                    </td>
                                    <td><?= date('Y-m-d H:i:s', strtotime($recording['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No recordings found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-center gap-2">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>&<?= http_build_query(array_filter([
                        'school_id' => $schoolId,
                        'teacher_id' => $teacherId,
                        'class_id' => $classId,
                        'subject_id' => $subjectId,
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                        'time_from' => $timeFrom,
                        'time_to' => $timeTo
                    ])) ?>" 
                    class="btn <?= $page === $i ? 'btn-primary' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Update dropdowns when school changes
        document.getElementById('schoolSelect').addEventListener('change', function() {
            const schoolId = this.value;
            
            // Update teachers
            fetch(`get_teachers.php?school_id=${schoolId}&type=teachers`)
                .then(response => response.json())
                .then(teachers => {
                    const select = document.getElementById('teacherSelect');
                    select.innerHTML = '<option value="0">All Teachers</option>';
                    teachers.forEach(teacher => {
                        select.innerHTML += `<option value="${teacher.id}">${teacher.name}</option>`;
                    });
                });
                
            // Update classes
            fetch(`get_teachers.php?school_id=${schoolId}&type=classes`)
                .then(response => response.json())
                .then(classes => {
                    const select = document.getElementById('classSelect');
                    select.innerHTML = '<option value="0">All Classes</option>';
                    classes.forEach(cls => {
                        select.innerHTML += `<option value="${cls.id}">${cls.name}</option>`;
                    });
                });
                
            // Update subjects
            fetch(`get_teachers.php?school_id=${schoolId}&type=subjects`)
                .then(response => response.json())
                .then(subjects => {
                    const select = document.getElementById('subjectSelect');
                    select.innerHTML = '<option value="0">All Subjects</option>';
                    subjects.forEach(subject => {
                        select.innerHTML += `<option value="${subject.id}">${subject.name}</option>`;
                    });
                });
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
