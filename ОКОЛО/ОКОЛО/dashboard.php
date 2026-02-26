<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];
$user_id = $_SESSION['user_id'];

// ============================================
// ДЛЯ ПАЦИЕНТА
// ============================================
if ($role === 'patient'): 
    // Получаем информацию о пациенте
    $stmt = $pdo->prepare("
        SELECT p.id as patient_id, u.full_name, u.district
        FROM patients p
        JOIN users u ON p.user_id = u.id
        WHERE p.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $patient = $stmt->fetch();
    
    if (!$patient) {
        // Если пациент не найден в таблице patients, создаем запись
        $stmt = $pdo->prepare("INSERT INTO patients (user_id, district) VALUES (?, ?)");
        $stmt->execute([$user_id, $_SESSION['district'] ?? '']);
        $patient_id = $pdo->lastInsertId();
    } else {
        $patient_id = $patient['patient_id'];
    }
    
    // Получаем статистику пациента
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT s.id) as total_surgeries,
            COUNT(DISTINCT CASE WHEN s.status = 'approved' THEN s.id END) as approved_surgeries,
            COUNT(DISTINCT CASE WHEN s.status = 'preparation' THEN s.id END) as in_preparation
        FROM patients p
        LEFT JOIN surgeries s ON p.id = s.patient_id
        WHERE p.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
    // Получаем информацию о последней операции
    $stmt = $pdo->prepare("
        SELECT s.*, d.name as diagnosis
        FROM surgeries s
        JOIN diseases d ON s.disease_id = d.id
        WHERE s.patient_id = ?
        ORDER BY s.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$patient_id]);
    $latest_surgery = $stmt->fetch();
    
    // Получаем ближайшую операцию
    $stmt = $pdo->prepare("
        SELECT s.*, d.name as diagnosis
        FROM surgeries s
        JOIN diseases d ON s.disease_id = d.id
        WHERE s.patient_id = ? AND s.surgery_date >= CURDATE() AND s.status = 'approved'
        ORDER BY s.surgery_date ASC
        LIMIT 1
    ");
    $stmt->execute([$patient_id]);
    $next_surgery = $stmt->fetch();
    
    // Получаем прогресс анализов
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(t.id) as total_tests,
            COUNT(CASE WHEN t.status IN ('uploaded', 'approved') THEN 1 END) as completed_tests
        FROM tests t
        JOIN surgeries s ON t.surgery_id = s.id
        WHERE s.patient_id = ?
    ");
    $stmt->execute([$patient_id]);
    $test_stats = $stmt->fetch();
    
    $full_name = $_SESSION['full_name'];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет пациента - ОКОЛО</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .patient-dashboard {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .dashboard-row {
            display: flex;
            gap: 2rem;
            margin: 2rem 0;
            flex-wrap: wrap;
        }
        
        .greeting-column {
            flex: 1;
            min-width: 300px;
        }
        
        .stats-column {
            flex: 2;
            min-width: 500px;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .welcome-card h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        .welcome-card p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .stats-grid-horizontal {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            height: 100%;
        }
        
        .stat-card-compact {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s ease;
            border-bottom: 4px solid transparent;
        }
        
        .stat-card-compact.total {
            border-bottom-color: #2a5298;
        }
        
        .stat-card-compact.approved {
            border-bottom-color: #28a745;
        }
        
        .stat-card-compact.preparation {
            border-bottom-color: #ffc107;
        }
        
        .stat-card-compact .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #1e3c72;
            margin-bottom: 0.5rem;
        }
        
        .stat-card-compact .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .actions-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin: 2rem 0;
        }
        
        .action-card-compact {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .action-card-compact:hover {
            border-color: #2a5298;
            transform: translateY(-5px);
        }
        
        .action-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .action-title {
            font-size: 1.1rem;
            color: #1e3c72;
            font-weight: bold;
            margin-bottom: 0.3rem;
        }
        
        .action-description {
            color: #666;
            font-size: 0.9rem;
        }
        
        .progress-section {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin: 2rem 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        
        .progress-bar-large {
            height: 20px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-fill-large {
            height: 100%;
            background: linear-gradient(90deg, #2a5298, #1e3c72);
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .dashboard-row {
                flex-direction: column;
            }
            
            .greeting-column, .stats-column {
                min-width: 100%;
            }
            
            .actions-row {
                grid-template-columns: 1fr;
            }
            
            .stats-grid-horizontal {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <img src="assets/img/logo.png" alt="ОКОЛО" width="85" height="55">
            ОКОЛО
        </div>
        <nav>
            <div class="nav-links">
                <a href="dashboard.php" class="active">Дашборд</a>
                <a href="check_status.php">Статус подготовки</a>
                <a href="schedule.php">Расписание</a>
                <a href="profile.php">Профиль</a>
            </div>
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($full_name); ?></span>
                <span class="role-badge">Пациент</span>
                <a href="logout.php" class="logout-btn">Выйти</a>
            </div>
        </nav>
    </header>

    <main class="container patient-dashboard">
        <!-- Горизонтальный ряд: приветствие + статистика -->
        <div class="dashboard-row">
            <div class="greeting-column">
                <div class="welcome-card">
                    <h1>Здравствуйте, <?php echo htmlspecialchars($full_name); ?>!</h1>
                    <p>Мы помогаем вам подготовиться к операции</p>
                </div>
            </div>
            
            <div class="stats-column">
                <div class="stats-grid-horizontal">
                    <div class="stat-card-compact total">
                        <div class="stat-number"><?php echo $stats['total_surgeries'] ?? 0; ?></div>
                        <div class="stat-label">Всего операций</div>
                    </div>
                    
                    <div class="stat-card-compact approved">
                        <div class="stat-number"><?php echo $stats['approved_surgeries'] ?? 0; ?></div>
                        <div class="stat-label">Одобрено</div>
                    </div>
                    
                    <div class="stat-card-compact preparation">
                        <div class="stat-number"><?php echo $stats['in_preparation'] ?? 0; ?></div>
                        <div class="stat-label">В подготовке</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Карточки действий -->
        <div class="actions-row">
            <div class="action-card-compact" onclick="location.href='check_status.php'">
                <div class="action-icon">📋</div>
                <div class="action-title">Статус подготовки</div>
                <div class="action-description">Проверить анализы и этапы</div>
            </div>
            
            <div class="action-card-compact" onclick="location.href='schedule.php'">
                <div class="action-icon">📅</div>
                <div class="action-title">Расписание</div>
                <div class="action-description">Даты операций</div>
            </div>
            
            <div class="action-card-compact" onclick="location.href='profile.php'">
                <div class="action-icon">👤</div>
                <div class="action-title">Профиль</div>
                <div class="action-description">Личные данные</div>
            </div>
        </div>

        <!-- Прогресс подготовки -->
        <?php if (($test_stats['total_tests'] ?? 0) > 0): 
            $progress_percent = ($test_stats['completed_tests'] / $test_stats['total_tests']) * 100;
        ?>
        <div class="progress-section">
            <div class="progress-header">
                <span style="font-weight: bold; color: #1e3c72;">Прогресс подготовки</span>
                <span style="color: #2a5298;"><?php echo $test_stats['completed_tests']; ?>/<?php echo $test_stats['total_tests']; ?> анализов</span>
            </div>
            <div class="progress-bar-large">
                <div class="progress-fill-large" style="width: <?php echo $progress_percent; ?>%;"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Ближайшая операция -->
        <?php if ($next_surgery): ?>
        <div style="background: linear-gradient(135deg, #28a745, #20c997); color: white; border-radius: 15px; padding: 1.5rem; margin: 2rem 0;">
            <h3 style="color: white; margin-bottom: 0.5rem;">Ближайшая операция</h3>
            <div style="font-size: 1.5rem; font-weight: bold;"><?php echo date('d.m.Y', strtotime($next_surgery['surgery_date'])); ?></div>
            <div style="margin-top: 0.5rem;"><?php echo htmlspecialchars($next_surgery['diagnosis']); ?></div>
        </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; 2026 ОКОЛО</p>
    </footer>

    <script src="assets/js/script.js"></script>
</body>
</html>

<?php 
// ============================================
// ДЛЯ РАЙОННОГО ОФТАЛЬМОЛОГА
// ============================================
elseif ($role === 'ophthalmologist'): 
    // Получаем статистику офтальмолога
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT p.id) as total_patients,
            COUNT(DISTINCT s.id) as total_surgeries,
            COUNT(DISTINCT CASE WHEN s.status = 'preparation' THEN s.id END) as in_preparation,
            COUNT(DISTINCT CASE WHEN s.status = 'approved' THEN s.id END) as approved,
            COUNT(DISTINCT CASE WHEN s.status = 'rejected' THEN s.id END) as rejected
        FROM patients p
        LEFT JOIN surgeries s ON p.id = s.patient_id
        WHERE p.doctor_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
    // Получаем пациентов, требующих внимания
    $stmt = $pdo->prepare("
        SELECT p.id, u.full_name, u.district, s.surgery_type, s.status, d.name as diagnosis,
            (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id AND status = 'uploaded') as tests_completed,
            (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id) as tests_total
        FROM patients p
        JOIN users u ON p.user_id = u.id
        JOIN surgeries s ON p.id = s.patient_id
        JOIN diseases d ON s.disease_id = d.id
        WHERE p.doctor_id = ? AND s.status IN ('preparation', 'review')
        ORDER BY s.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $patients = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Дашборд офтальмолога - ОКОЛО</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <div class="logo">
            <img src="assets/img/logo.png" alt="ОКОЛО" width="85" height="55">
            ОКОЛО
        </div>
        <nav>
            <div class="nav-links">
                <a href="dashboard.php" class="active">Дашборд</a>
                <a href="patients.php">Мои пациенты</a>
                <a href="schedule.php">Расписание</a>
                <a href="profile.php">Профиль</a>
            </div>
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($full_name); ?></span>
                <span class="role-badge">Районный офтальмолог</span>
                <a href="logout.php" class="logout-btn">Выйти</a>
            </div>
        </nav>
    </header>

    <main class="container">
        <section class="welcome-section">
            <h1>Добро пожаловать, <?php echo htmlspecialchars($full_name); ?></h1>
            <p>Обзор ваших пациентов и текущих задач</p>
        </section>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Всего пациентов</h3>
                <div class="stat-number"><?php echo $stats['total_patients'] ?? 0; ?></div>
            </div>
            <div class="stat-card preparation">
                <h3>На подготовке</h3>
                <div class="stat-number"><?php echo $stats['in_preparation'] ?? 0; ?></div>
            </div>
            <div class="stat-card approved">
                <h3>Одобрены</h3>
                <div class="stat-number"><?php echo $stats['approved'] ?? 0; ?></div>
            </div>
            <div class="stat-card revision">
                <h3>Доработка</h3>
                <div class="stat-number"><?php echo $stats['rejected'] ?? 0; ?></div>
            </div>
        </div>

        <h2 class="section-title">Требуют внимания</h2>
        
        <div class="patients-grid">
            <?php if (empty($patients)): ?>
            <div class="empty-schedule" style="grid-column: 1/-1; text-align: center; padding: 3rem;">
                <p>Нет пациентов, требующих внимания</p>
            </div>
            <?php else: ?>
                <?php foreach ($patients as $patient): 
                    $progress = $patient['tests_total'] > 0 ? 
                        round(($patient['tests_completed'] / $patient['tests_total']) * 100) : 0;
                ?>
                <div class="patient-card">
                    <div class="patient-header">
                        <span class="patient-name"><?php echo htmlspecialchars($patient['full_name']); ?></span>
                        <span class="patient-district"><?php echo htmlspecialchars($patient['district']); ?></span>
                    </div>
                    <div class="patient-diagnosis"><?php echo htmlspecialchars($patient['diagnosis']); ?></div>
                    <div class="analysis-progress">
                        <div class="progress-label">
                            <span>Анализы: <?php echo $patient['tests_completed']; ?>/<?php echo $patient['tests_total']; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                        </div>
                    </div>
                    <span class="surgery-type"><?php echo htmlspecialchars($patient['surgery_type']); ?></span>
                    <div style="margin-top: 1rem;">
                        <a href="patient_detail.php?id=<?php echo $patient['id']; ?>" class="btn-small">Подробнее</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 ОКОЛО</p>
    </footer>

    <script src="assets/js/script.js"></script>
</body>
</html>

<?php 
// ============================================
// ДЛЯ ХИРУРГА-КУРАТОРА
// ============================================
elseif ($role === 'surgeon'): 
    // Получаем статистику хирурга
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT p.id) as total_patients,
            COUNT(DISTINCT s.id) as total_surgeries,
            COUNT(DISTINCT CASE WHEN s.status = 'review' THEN s.id END) as pending_review,
            COUNT(DISTINCT CASE WHEN s.status = 'approved' AND s.surgery_date >= CURDATE() THEN s.id END) as upcoming_surgeries
        FROM patients p
        LEFT JOIN surgeries s ON p.id = s.patient_id
        WHERE p.surgeon_id = ? OR (p.surgeon_id IS NULL AND s.status = 'review')
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
    // Получаем операции, требующие проверки
    $stmt = $pdo->prepare("
        SELECT p.id, u.full_name, u.district, s.surgery_type, s.status, d.name as diagnosis,
            (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id AND status = 'uploaded') as tests_completed,
            (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id) as tests_total
        FROM patients p
        JOIN users u ON p.user_id = u.id
        JOIN surgeries s ON p.id = s.patient_id
        JOIN diseases d ON s.disease_id = d.id
        WHERE s.status = 'review' AND (p.surgeon_id = ? OR p.surgeon_id IS NULL)
        ORDER BY s.created_at ASC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $pending_surgeries = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Дашборд хирурга - ОКОЛО</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <div class="logo">
            <img src="assets/img/logo.png" alt="ОКОЛО" width="85" height="55">
            ОКОЛО
        </div>
        <nav>
            <div class="nav-links">
                <a href="dashboard.php" class="active">Дашборд</a>
                <a href="review.php">На проверку</a>
                <a href="schedule.php">Расписание</a>
                <a href="profile.php">Профиль</a>
            </div>
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($full_name); ?></span>
                <span class="role-badge">Хирург-куратор</span>
                <a href="logout.php" class="logout-btn">Выйти</a>
            </div>
        </nav>
    </header>

    <main class="container">
        <section class="welcome-section">
            <h1>Добро пожаловать, <?php echo htmlspecialchars($full_name); ?></h1>
            <p>Панель управления хирурга-куратора</p>
        </section>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Всего пациентов</h3>
                <div class="stat-number"><?php echo $stats['total_patients'] ?? 0; ?></div>
            </div>
            <div class="stat-card review">
                <h3>На проверке</h3>
                <div class="stat-number"><?php echo $stats['pending_review'] ?? 0; ?></div>
            </div>
            <div class="stat-card approved">
                <h3>Предстоит операций</h3>
                <div class="stat-number"><?php echo $stats['upcoming_surgeries'] ?? 0; ?></div>
            </div>
        </div>

        <h2 class="section-title">Ожидают проверки</h2>
        
        <div class="patients-grid">
            <?php if (empty($pending_surgeries)): ?>
            <div class="empty-schedule" style="grid-column: 1/-1; text-align: center; padding: 3rem;">
                <p>Нет операций, ожидающих проверки</p>
            </div>
            <?php else: ?>
                <?php foreach ($pending_surgeries as $surgery): 
                    $progress = $surgery['tests_total'] > 0 ? 
                        round(($surgery['tests_completed'] / $surgery['tests_total']) * 100) : 0;
                ?>
                <div class="patient-card">
                    <div class="patient-header">
                        <span class="patient-name"><?php echo htmlspecialchars($surgery['full_name']); ?></span>
                        <span class="patient-district"><?php echo htmlspecialchars($surgery['district']); ?></span>
                    </div>
                    <div class="patient-diagnosis"><?php echo htmlspecialchars($surgery['diagnosis']); ?></div>
                    <div class="analysis-progress">
                        <div class="progress-label">
                            <span>Анализы: <?php echo $surgery['tests_completed']; ?>/<?php echo $surgery['tests_total']; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                        </div>
                    </div>
                    <span class="surgery-type"><?php echo htmlspecialchars($surgery['surgery_type']); ?></span>
                    <div style="margin-top: 1rem;">
                        <a href="patient_detail.php?id=<?php echo $surgery['id']; ?>" class="btn-small">Проверить</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 ОКОЛО</p>
    </footer>

    <script src="assets/js/script.js"></script>
</body>
</html>

<?php 
endif; 
?>