<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
requireRole('patient');

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

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
    $stmt = $pdo->prepare("INSERT INTO patients (user_id, district) VALUES (?, ?)");
    $stmt->execute([$user_id, $_SESSION['district'] ?? '']);
    $patient_id = $pdo->lastInsertId();
} else {
    $patient_id = $patient['patient_id'];
}

// Получаем информацию об операциях пациента
$stmt = $pdo->prepare("
    SELECT s.*, d.name as diagnosis, d.description,
        (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id) as tests_total,
        (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id AND status = 'uploaded') as tests_uploaded,
        (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id AND status = 'approved') as tests_approved,
        doc.full_name as doctor_name,
        surg.full_name as surgeon_name
    FROM surgeries s
    JOIN diseases d ON s.disease_id = d.id
    LEFT JOIN users doc ON s.doctor_id = doc.id
    LEFT JOIN users surg ON s.surgeon_id = surg.id
    WHERE s.patient_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute([$patient_id]);
$surgeries = $stmt->fetchAll();

// Получаем анализы для первой операции (если есть)
$tests = [];
if (!empty($surgeries)) {
    $stmt = $pdo->prepare("
        SELECT * FROM tests 
        WHERE surgery_id = ? 
        ORDER BY 
            CASE status 
                WHEN 'pending' THEN 1 
                WHEN 'uploaded' THEN 2 
                WHEN 'approved' THEN 3 
                WHEN 'rejected' THEN 4 
            END,
            test_name
    ");
    $stmt->execute([$surgeries[0]['id']]);
    $tests = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Статус подготовки - Окулус-Фельдшер</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .patient-status-page {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .status-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .status-header h1 {
            color: #1e3c72;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .status-header p {
            color: #666;
            font-size: 1.1rem;
        }
        
        .patient-info-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            animation: slideIn 0.5s ease-out;
        }
        
        .patient-name-large {
            font-size: 1.8rem;
            color: #1e3c72;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }
        
        .patient-diagnosis {
            color: #444;
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f4f8;
        }
        
        .progress-tracker {
            display: flex;
            justify-content: space-between;
            margin: 2rem 0;
            position: relative;
            padding: 0 1rem;
        }
        
        .progress-tracker::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 3px;
            background: #e0e0e0;
            z-index: 1;
            transform: translateY(-50%);
        }
        
        .tracker-step {
            position: relative;
            z-index: 2;
            background: white;
            padding: 0.8rem 1.5rem;
            border-radius: 30px;
            border: 2px solid #e0e0e0;
            color: #666;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .tracker-step.active {
            border-color: #2a5298;
            background: #2a5298;
            color: white;
            transform: scale(1.05);
        }
        
        .tracker-step.completed {
            border-color: #28a745;
            background: #28a745;
            color: white;
        }
        
        .surgery-date {
            text-align: center;
            margin: 2rem 0;
            padding: 1rem;
            background: #e8f0fe;
            border-radius: 10px;
            font-size: 1.2rem;
        }
        
        .surgery-date strong {
            color: #1e3c72;
            font-size: 1.3rem;
        }
        
        .tests-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 2rem;
            margin-top: 2rem;
        }
        
        .tests-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .tests-header h3 {
            color: #1e3c72;
            font-size: 1.3rem;
        }
        
        .tests-progress {
            background: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            color: #2a5298;
            font-weight: bold;
        }
        
        .tests-list {
            list-style: none;
        }
        
        .test-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: white;
            border-radius: 10px;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .test-item:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .test-item.uploaded {
            border-left-color: #28a745;
        }
        
        .test-item.pending {
            border-left-color: #ffc107;
        }
        
        .test-item.approved {
            border-left-color: #17a2b8;
        }
        
        .test-name {
            font-weight: 500;
            color: #333;
        }
        
        .test-status-badge {
            padding: 0.3rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .test-status-badge.uploaded {
            background: #d4edda;
            color: #155724;
        }
        
        .test-status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .test-status-badge.approved {
            background: #cce5ff;
            color: #004085;
        }
        
        .test-status-badge.rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: #666;
            font-size: 1.1rem;
        }
        
        .no-data i {
            font-size: 3rem;
            color: #2a5298;
            margin-bottom: 1rem;
            display: block;
        }
        
        .refresh-btn {
            text-align: center;
            margin-top: 2rem;
        }
        
        .btn-refresh {
            background: #f0f4f8;
            color: #1e3c72;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 10px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-refresh:hover {
            background: #1e3c72;
            color: white;
        }
        
        @media (max-width: 768px) {
            .progress-tracker {
                flex-direction: column;
                gap: 1rem;
            }
            
            .progress-tracker::before {
                display: none;
            }
            
            .tracker-step {
                text-align: center;
            }
            
            .test-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
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
                <!-- Только Дашборд в навигации -->
                <a href="dashboard.php">Дашборд</a>
            </div>
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($full_name); ?></span>
                <span class="role-badge">Пациент</span>
                <a href="logout.php" class="logout-btn">Выйти</a>
            </div>
        </nav>
    </header>

    <main class="container patient-status-page">
        <div class="status-header">
            <h1>Проверка статуса подготовки</h1>
            <p>Ваш персональный статус подготовки к операции</p>
        </div>

        <?php if (empty($surgeries)): ?>
            <div class="patient-info-card no-data">
                <div style="font-size: 4rem; margin-bottom: 1rem;">📋</div>
                <h3>У вас пока нет запланированных операций</h3>
                <p style="margin: 1rem 0;">Обратитесь к вашему офтальмологу для консультации</p>
            </div>
        <?php else: ?>
            <?php foreach ($surgeries as $surgery): ?>
                <div class="patient-info-card">
                    <div class="patient-name-large"><?php echo htmlspecialchars($full_name); ?></div>
                    <div class="patient-diagnosis">
                        <strong>Диагноз:</strong> <?php echo htmlspecialchars($surgery['diagnosis']); ?>
                    </div>

                    <!-- Статус операции -->
                    <?php
                    $current_status = $surgery['status'];
                    $status_steps = ['new', 'preparation', 'review', 'approved'];
                    $status_names = [
                        'new' => 'Новый',
                        'preparation' => 'Подготовка',
                        'review' => 'Проверка',
                        'approved' => 'Одобрен'
                    ];
                    
                    $current_index = array_search($current_status, $status_steps);
                    if ($current_index === false) $current_index = 0;
                    ?>

                    <div class="progress-tracker">
                        <?php foreach ($status_steps as $index => $step): ?>
                            <div class="tracker-step 
                                <?php echo $index < $current_index ? 'completed' : ''; ?>
                                <?php echo $index == $current_index ? 'active' : ''; ?>">
                                <?php echo $status_names[$step]; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($surgery['surgery_date']): ?>
                        <div class="surgery-date">
                            <strong>Дата операции:</strong> 
                            <?php echo date('d.m.Y H:i', strtotime($surgery['surgery_date'])); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Анализы -->
                    <div class="tests-section">
                        <div class="tests-header">
                            <h3>Анализы и обследования</h3>
                            <span class="tests-progress">
                                <?php echo $surgery['tests_uploaded'] + $surgery['tests_approved']; ?>/<?php echo $surgery['tests_total']; ?>
                            </span>
                        </div>

                        <?php
                        // Получаем анализы для этой операции
                        $stmt = $pdo->prepare("SELECT * FROM tests WHERE surgery_id = ? ORDER BY test_name");
                        $stmt->execute([$surgery['id']]);
                        $current_tests = $stmt->fetchAll();
                        ?>

                        <ul class="tests-list">
                            <?php foreach ($current_tests as $test): ?>
                                <li class="test-item <?php echo $test['status']; ?>">
                                    <span class="test-name"><?php echo htmlspecialchars($test['test_name']); ?></span>
                                    <span class="test-status-badge <?php echo $test['status']; ?>">
                                        <?php 
                                        $statuses = [
                                            'pending' => '⏳ Ожидает',
                                            'uploaded' => '📤 Загружен',
                                            'approved' => '✅ Принят',
                                            'rejected' => '❌ Отклонен'
                                        ];
                                        echo $statuses[$test['status']] ?? $test['status'];
                                        ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <?php if (empty($current_tests)): ?>
                            <p style="text-align: center; color: #666;">Нет назначенных анализов</p>
                        <?php endif; ?>
                    </div>

                    <!-- Примечания -->
                    <?php if ($surgery['notes']): ?>
                        <div style="margin-top: 1rem; padding: 1rem; background: #fff3cd; border-radius: 10px;">
                            <strong>Примечание:</strong><br>
                            <?php echo nl2br(htmlspecialchars($surgery['notes'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="refresh-btn">
            <button onclick="location.reload()" class="btn-refresh">🔄 Обновить статус</button>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 ОКОЛО. Все права защищены.</p>
    </footer>

    <script src="assets/js/script.js"></script>
</body>
</html>