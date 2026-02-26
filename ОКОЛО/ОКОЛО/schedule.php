<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];
$user_id = $_SESSION['user_id'];

// Получаем текущий месяц и год
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Корректировка месяца
if ($month < 1) {
    $month = 12;
    $year--;
} elseif ($month > 12) {
    $month = 1;
    $year++;
}

// Названия месяцев на русском
$months = [
    1 => 'Январь',
    2 => 'Февраль',
    3 => 'Март',
    4 => 'Апрель',
    5 => 'Май',
    6 => 'Июнь',
    7 => 'Июль',
    8 => 'Август',
    9 => 'Сентябрь',
    10 => 'Октябрь',
    11 => 'Ноябрь',
    12 => 'Декабрь'
];

// Получаем операции в зависимости от роли
if ($role === 'patient') {
    // Для пациента - показываем только его операции
    $stmt = $pdo->prepare("
        SELECT s.*, u.full_name as patient_name, d.name as diagnosis,
            (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id AND status = 'uploaded') as tests_completed,
            (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id) as tests_total,
            doc.full_name as doctor_name,
            surg.full_name as surgeon_name
        FROM surgeries s
        JOIN patients p ON s.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        JOIN diseases d ON s.disease_id = d.id
        LEFT JOIN users doc ON p.doctor_id = doc.id
        LEFT JOIN users surg ON p.surgeon_id = surg.id
        WHERE p.user_id = ?
        ORDER BY 
            CASE WHEN s.surgery_date IS NULL THEN 1 ELSE 0 END,
            s.surgery_date ASC,
            s.created_at DESC
    ");
    $stmt->execute([$user_id]);
    
} elseif ($role === 'ophthalmologist') {
    // Для офтальмолога - пациенты этого врача
    $stmt = $pdo->prepare("
        SELECT s.*, u.full_name as patient_name, u.district, d.name as diagnosis,
            (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id AND status = 'uploaded') as tests_completed,
            (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id) as tests_total
        FROM surgeries s
        JOIN patients p ON s.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        JOIN diseases d ON s.disease_id = d.id
        WHERE p.doctor_id = ?
        ORDER BY 
            CASE WHEN s.surgery_date IS NULL THEN 1 ELSE 0 END,
            s.surgery_date ASC,
            s.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    
} elseif ($role === 'surgeon') {
    // Для хирурга - все операции
    $stmt = $pdo->prepare("
        SELECT s.*, u.full_name as patient_name, u.district, d.name as diagnosis,
            (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id AND status = 'uploaded') as tests_completed,
            (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id) as tests_total,
            doc.full_name as doctor_name
        FROM surgeries s
        JOIN patients p ON s.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        JOIN diseases d ON s.disease_id = d.id
        LEFT JOIN users doc ON p.doctor_id = doc.id
        ORDER BY 
            CASE WHEN s.surgery_date IS NULL THEN 1 ELSE 0 END,
            s.surgery_date ASC,
            s.created_at DESC
    ");
    $stmt->execute();
}

$surgeries = $stmt->fetchAll();

// Получаем статистику
$stats = [
    'total' => count($surgeries),
    'scheduled' => 0,
    'completed' => 0,
    'pending' => 0
];

foreach ($surgeries as $s) {
    if ($s['surgery_date']) {
        $stats['scheduled']++;
        if ($s['surgery_date'] < date('Y-m-d')) {
            $stats['completed']++;
        }
    } else {
        $stats['pending']++;
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Расписание операций - ОКОЛО</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .schedule-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .month-navigation {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: white;
            padding: 0.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .month-nav-btn {
            background: #f0f4f8;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.2rem;
            color: #1e3c72;
            transition: all 0.3s ease;
        }
        
        .month-nav-btn:hover {
            background: #1e3c72;
            color: white;
            transform: scale(1.05);
        }
        
        .current-month {
            font-size: 1.5rem;
            font-weight: bold;
            color: #1e3c72;
            min-width: 200px;
            text-align: center;
        }
        
        .schedule-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .schedule-stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .schedule-stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #1e3c72;
        }
        
        .schedule-stat-label {
            color: #666;
            margin-top: 0.3rem;
        }
        
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .schedule-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .schedule-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .schedule-card.scheduled {
            border-left: 5px solid #28a745;
        }
        
        .schedule-card.pending {
            border-left: 5px solid #ffc107;
        }
        
        .schedule-card.completed {
            border-left: 5px solid #6c757d;
            opacity: 0.8;
        }
        
        .schedule-date {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #f0f4f8;
            padding: 0.3rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            color: #1e3c72;
            font-weight: 500;
        }
        
        .schedule-card h3 {
            color: #1e3c72;
            margin-bottom: 0.5rem;
            padding-right: 100px;
        }
        
        .schedule-district {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .schedule-diagnosis {
            color: #444;
            margin-bottom: 1rem;
            font-style: italic;
        }
        
        .schedule-details {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin: 1rem 0;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .schedule-detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .detail-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .detail-value {
            color: #1e3c72;
            font-weight: 500;
        }
        
        .empty-schedule {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 15px;
            color: #666;
            grid-column: 1/-1;
        }
        
        .btn-small {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #1e3c72;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .btn-small:hover {
            background: #2a5298;
            transform: translateY(-2px);
        }
        
        /* Стили для навигации пациента */
        .patient-nav {
            display: flex;
            gap: 1rem;
        }
        
        .patient-nav a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .patient-nav a:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .patient-nav a.active {
            background: rgba(255,255,255,0.3);
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .schedule-grid {
                grid-template-columns: 1fr;
            }
            
            .month-navigation {
                width: 100%;
                justify-content: space-between;
            }
            
            .current-month {
                min-width: auto;
                font-size: 1.2rem;
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
                <?php if ($role === 'patient'): ?>
                    <!-- Для пациента - только Дашборд -->
                    <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Дашборд</a>
                <?php elseif ($role === 'ophthalmologist'): ?>
                    <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Дашборд</a>
                    <a href="patients.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'patients.php' ? 'active' : ''; ?>">Мои пациенты</a>
                    <a href="schedule.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'schedule.php' ? 'active' : ''; ?>">Расписание</a>
                    <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">Профиль</a>
                <?php elseif ($role === 'surgeon'): ?>
                    <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Дашборд</a>
                    <a href="patients.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'patients.php' ? 'active' : ''; ?>">Мои пациенты</a>
                    <a href="schedule.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'schedule.php' ? 'active' : ''; ?>">Расписание</a>
                    <a href="review.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'review.php' ? 'active' : ''; ?>">На проверку</a>
                    <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">Профиль</a>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($full_name); ?></span>
                <span class="role-badge">
                    <?php 
                    $roles = [
                        'patient' => 'Пациент',
                        'ophthalmologist' => 'Офтальмолог',
                        'surgeon' => 'Хирург'
                    ];
                    echo $roles[$role] ?? $role;
                    ?>
                </span>
                <a href="logout.php" class="logout-btn">Выйти</a>
            </div>
        </nav>
    </header>

    <main class="container">
        <div class="schedule-header">
            <h1 class="section-title">Расписание операций</h1>
            
            <div class="month-navigation">
                <button class="month-nav-btn" onclick="changeMonth(-1)">←</button>
                <span class="current-month"><?php echo $months[$month] . ' ' . $year; ?></span>
                <button class="month-nav-btn" onclick="changeMonth(1)">→</button>
            </div>
        </div>

        <div class="schedule-stats">
            <div class="schedule-stat-card">
                <div class="schedule-stat-value"><?php echo $stats['total']; ?></div>
                <div class="schedule-stat-label">Всего операций</div>
            </div>
            <div class="schedule-stat-card">
                <div class="schedule-stat-value"><?php echo $stats['scheduled']; ?></div>
                <div class="schedule-stat-label">Запланировано</div>
            </div>
            <div class="schedule-stat-card">
                <div class="schedule-stat-value"><?php echo $stats['pending']; ?></div>
                <div class="schedule-stat-label">Ожидают даты</div>
            </div>
            <div class="schedule-stat-card">
                <div class="schedule-stat-value"><?php echo $stats['completed']; ?></div>
                <div class="schedule-stat-label">Выполнено</div>
            </div>
        </div>

        <div class="schedule-grid">
            <?php if (empty($surgeries)): ?>
                <div class="empty-schedule">
                    <h3>Нет запланированных операций</h3>
                    <p>В данном периоде нет операций для отображения</p>
                </div>
            <?php else: ?>
                <?php foreach ($surgeries as $surgery): 
                    $cardClass = $surgery['surgery_date'] ? 
                        ($surgery['surgery_date'] < date('Y-m-d') ? 'completed' : 'scheduled') : 
                        'pending';
                ?>
                <div class="schedule-card <?php echo $cardClass; ?>">
                    <?php if ($surgery['surgery_date']): ?>
                    <div class="schedule-date">
                        <?php echo date('d.m.Y', strtotime($surgery['surgery_date'])); ?>
                    </div>
                    <?php endif; ?>
                    
                    <h3><?php echo htmlspecialchars($surgery['patient_name']); ?></h3>
                    
                    <?php if (isset($surgery['district'])): ?>
                    <div class="schedule-district">
                        <?php echo htmlspecialchars($surgery['district']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="schedule-diagnosis">
                        <?php echo htmlspecialchars($surgery['diagnosis']); ?>
                    </div>
                    
                    <?php if ($surgery['tests_total'] > 0): ?>
                    <div class="analysis-progress">
                        <div class="progress-label">
                            <span>Анализы: <?php echo $surgery['tests_completed']; ?>/<?php echo $surgery['tests_total']; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo ($surgery['tests_completed'] / $surgery['tests_total']) * 100; ?>%"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="schedule-details">
                        <div class="schedule-detail-item">
                            <span class="detail-label">Операция:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($surgery['surgery_type']); ?></span>
                        </div>
                        
                        <?php if ($role === 'patient' && isset($surgery['doctor_name'])): ?>
                        <div class="schedule-detail-item">
                            <span class="detail-label">Офтальмолог:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($surgery['doctor_name'] ?: 'Не назначен'); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($role === 'patient' && isset($surgery['surgeon_name'])): ?>
                        <div class="schedule-detail-item">
                            <span class="detail-label">Хирург:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($surgery['surgeon_name'] ?: 'Не назначен'); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="schedule-detail-item">
                            <span class="detail-label">Статус:</span>
                            <span class="detail-value">
                                <span class="status-badge status-<?php echo $surgery['status']; ?>">
                                    <?php 
                                    $statuses = [
                                        'new' => 'Новый',
                                        'preparation' => 'Подготовка',
                                        'review' => 'Проверка',
                                        'approved' => 'Одобрен',
                                        'rejected' => 'Отклонен'
                                    ];
                                    echo $statuses[$surgery['status']] ?? $surgery['status'];
                                    ?>
                                </span>
                            </span>
                        </div>
                    </div>
                    
                    <?php if ($role !== 'patient'): ?>
                    <div style="margin-top: 1rem;">
                        <a href="patient_detail.php?id=<?php echo $surgery['patient_id']; ?>" class="btn-small">Подробнее</a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 ОКОЛО</p>
    </footer>

    <script src="assets/js/script.js"></script>
    <script>
        function changeMonth(delta) {
            let month = <?php echo $month; ?>;
            let year = <?php echo $year; ?>;
            
            month += delta;
            
            if (month < 1) {
                month = 12;
                year--;
            } else if (month > 12) {
                month = 1;
                year++;
            }
            
            window.location.href = `schedule.php?month=${month}&year=${year}`;
        }
    </script>
</body>
</html>