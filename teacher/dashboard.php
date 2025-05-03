<?php
include '../includes/auth.php';
include '../includes/db.php';

if ($_SESSION['role'] != 'teacher') {
    header("Location: ../index.php");
    exit();
}

$teacherId = $_SESSION['user_id'];
$schoolId = $_SESSION['school_id'] ?? 1;

// Get user's name from database
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$teacherId]);
$user = $stmt->fetch();
$teacherName = $user['name'] ?? 'Teacher';

// Get school details
$stmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$schoolId]);
$school = $stmt->fetch();

// Get assigned classes for this teacher in this school
$stmt = $pdo->prepare("
    SELECT DISTINCT c.id, c.class_name
    FROM classes c
    JOIN teacher_subjects ts ON c.id = ts.class_id
    WHERE ts.teacher_id = ? AND c.school_id = ?
    ORDER BY c.class_name
");
$stmt->execute([$teacherId, $schoolId]);
$assignedClasses = $stmt->fetchAll();

// Get assigned subjects for this teacher in this school
$stmt = $pdo->prepare("
    SELECT DISTINCT s.id, s.subject_name
    FROM subjects s
    JOIN teacher_subjects ts ON s.id = ts.subject_id
    JOIN classes c ON ts.class_id = c.id
    WHERE ts.teacher_id = ? AND c.school_id = ?
    ORDER BY s.subject_name
");
$stmt->execute([$teacherId, $schoolId]);
$assignedSubjects = $stmt->fetchAll();

// Get counts for dashboard stats
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT c.id) as class_count FROM classes c JOIN teacher_subjects ts ON c.id = ts.class_id WHERE ts.teacher_id = ? AND c.school_id = ?");
$stmt->execute([$teacherId, $schoolId]);
$classCount = $stmt->fetch()['class_count'];

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT s.id) as subject_count FROM subjects s JOIN teacher_subjects ts ON s.id = ts.subject_id WHERE ts.teacher_id = ?");
$stmt->execute([$teacherId]);
$subjectCount = $stmt->fetch()['subject_count'];

// Fix: Count exams for classes assigned to this teacher
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT e.id) as exam_count
    FROM exams e
    JOIN classes c ON e.class_id = c.id
    JOIN teacher_subjects ts ON c.id = ts.class_id
    WHERE ts.teacher_id = ? AND c.school_id = ?
");
$stmt->execute([$teacherId, $schoolId]);
$examCount = $stmt->fetch()['exam_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#4A90E2">
    <script src="../assets/scripts.js"></script>
    <title>Teacher Dashboard - <?= htmlspecialchars($school['school_name'] ?? 'School MIS') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #e0e7ff;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --warning: #f8961e;
            --danger: #f72585;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --white: #ffffff;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fb;
            color: var(--dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header Styles */
        .header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-weight: 600;
            font-size: 1.8rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-name {
            font-weight: 500;
            font-size: 1rem;
        }

        .btn-logout {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 50px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
        }

        .btn-logout:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: var(--white);
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.classes {
            background-color: var(--primary-light);
            color: var(--primary);
        }

        .stat-icon.subjects {
            background-color: #e0f4ff;
            color: var(--success);
        }

        .stat-icon.exams {
            background-color: #fff3e0;
            color: var(--warning);
        }

        .stat-content h3 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }

        .stat-content p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Main Menu Grid */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 50px;
        }

        .menu-card {
            background-color: var(--white);
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            color: var(--dark);
        }

        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        .menu-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 15px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }

        .menu-icon.attendance {
            background-color: var(--primary-light);
            color: var(--primary);
        }

        .menu-icon.record {
            background-color: #f0f9ff;
            color: var(--success);
        }

        .menu-icon.recordings {
            background-color: #f5f0ff;
            color: #7b2cbf;
        }

        .menu-icon.exams {
            background-color: #fff0f3;
            color: var(--danger);
        }

        .menu-card h3 {
            font-size: 1.2rem;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .menu-card p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Mobile Bottom Navigation */
        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: var(--white);
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            padding: 10px 0;
            z-index: 1000;
        }

        .nav-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: var(--gray);
            font-size: 0.8rem;
            padding: 5px 0;
        }

        .nav-link i {
            font-size: 1.2rem;
            margin-bottom: 5px;
        }

        .nav-link.active {
            color: var(--primary);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .user-info {
                flex-direction: column;
                gap: 10px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .menu-grid {
                grid-template-columns: 1fr 1fr;
            }

            .bottom-nav {
                display: flex;
                justify-content: space-around;
            }
        }

        @media (max-width: 480px) {
            .menu-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding-bottom: 70px;
            }
        }

        /* Welcome Message */
        .welcome-message {
            background-color: var(--white);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
        }

        .welcome-message h2 {
            color: var(--primary);
            margin-bottom: 10px;
            font-weight: 600;
        }

        .welcome-message p {
            color: var(--gray);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>Teacher Dashboard</h1>
                <p><?= htmlspecialchars($school['school_name'] ?? 'School') ?></p>
            </div>
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($teacherName) ?></span>
                <a href="../logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="d-none d-md-inline">Logout</span>
                </a>
            </div>
        </div>

        <!-- Welcome Message -->
        <div class="welcome-message">
            <h2>Welcome back, <?= htmlspecialchars(explode(' ', $teacherName)[0]) ?>!</h2>
            <p>Here's what's happening with your classes today.</p>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon classes">
                    <i class="fas fa-chalkboard"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $classCount ?></h3>
                    <p>Classes Assigned</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon subjects">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $subjectCount ?></h3>
                    <p>Subjects Teaching</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon exams">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $examCount ?></h3>
                    <p>Exams Scheduled</p>
                </div>
            </div>
        </div>

        <!-- Main Menu Grid -->
        <div class="menu-grid">
            <a href="attendance.php" class="menu-card">
                <div class="menu-icon attendance">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <h3>Attendance</h3>
                <p>Mark and view student attendance</p>
            </a>

            <a href="record_audio.php" class="menu-card">
                <div class="menu-icon record">
                    <i class="fas fa-microphone-alt"></i>
                </div>
                <h3>Record Audio</h3>
                <p>Record your class sessions</p>
            </a>

            <a href="view_recordings.php" class="menu-card">
                <div class="menu-icon recordings">
                    <i class="fas fa-headphones"></i>
                </div>
                <h3>View Recordings</h3>
                <p>Access your recorded sessions</p>
            </a>

            <a href="exams_list.php" class="menu-card">
                <div class="menu-icon exams">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h3>Exams</h3>
                <p>Enter and manage student scores</p>
            </a>
        </div>

        <!-- Mobile Bottom Navigation -->
        <nav class="bottom-nav">
            <a href="attendance.php" class="nav-link">
                <i class="fas fa-clipboard-check"></i>
                <span>Attendance</span>
            </a>
            <a href="record_audio.php" class="nav-link">
                <i class="fas fa-microphone-alt"></i>
                <span>Record</span>
            </a>
            <a href="view_recordings.php" class="nav-link">
                <i class="fas fa-headphones"></i>
                <span>Recordings</span>
            </a>
            <a href="exams_list.php" class="nav-link">
                <i class="fas fa-graduation-cap"></i>
                <span>Exams</span>
            </a>
        </nav>
    </div>

    <script>
        // Add active class to current page in mobile nav
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>