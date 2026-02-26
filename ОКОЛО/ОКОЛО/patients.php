<?php
require_once 'includes/auth.php';
requireLogin();

$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];
$user_id = $_SESSION['user_id'];

// Получаем список пациентов в зависимости от роли
$patients = []; // Инициализируем пустым массивом

if ($role === 'ophthalmologist') {
    // Для районного офтальмолога - его пациенты
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            u.full_name,
            u.district,
            u.phone,
            u.email,
            s.id as surgery_id,
            s.surgery_type,
            s.status,
            s.surgery_date,
            d.name as diagnosis,
            d.code as diagnosis_code,
            (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id) as tests_total,
            (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id AND status = 'uploaded') as tests_uploaded,
            (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id AND status = 'approved') as tests_approved,
            (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id AND status = 'rejected') as tests_rejected,
            (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id AND status = 'pending') as tests_pending
        FROM patients p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN surgeries s ON p.id = s.patient_id
        LEFT JOIN diseases d ON s.disease_id = d.id
        WHERE p.doctor_id = ?
        ORDER BY 
            CASE 
                WHEN s.status = 'preparation' THEN 1
                WHEN s.status = 'review' THEN 2
                WHEN s.status = 'new' THEN 3
                ELSE 4
            END,
            u.full_name ASC
    ");
    $stmt->execute([$user_id]);
    $patients = $stmt->fetchAll();
    
} elseif ($role === 'surgeon') {
    // Для хирурга - его пациенты (одобренные и на проверке)
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            u.full_name,
            u.district,
            u.phone,
            u.email,
            s.id as surgery_id,
            s.surgery_type,
            s.status,
            s.surgery_date,
            d.name as diagnosis,
            d.code as diagnosis_code,
            (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id) as tests_total,
            (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id AND status = 'approved') as tests_approved,
            (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id AND status = 'uploaded') as tests_uploaded,
            doc.full_name as doctor_name
        FROM patients p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN surgeries s ON p.id = s.patient_id
        LEFT JOIN diseases d ON s.disease_id = d.id
        LEFT JOIN users doc ON p.doctor_id = doc.id
        WHERE p.surgeon_id = ? OR (s.status = 'review' AND p.surgeon_id IS NULL)
        ORDER BY 
            CASE 
                WHEN s.status = 'review' THEN 1
                WHEN s.status = 'approved' AND s.surgery_date >= CURDATE() THEN 2
                ELSE 3
            END,
            s.surgery_date ASC
    ");
    $stmt->execute([$user_id]);
    $patients = $stmt->fetchAll();
    
} elseif ($role === 'patient') {
    // Для пациента - его собственные данные (перенаправляем или показываем свои данные)
    header('Location: profile.php');
    exit();
}

// Массив статусов для отображения
$status_labels = [
    'new' => 'Новый',
    'preparation' => 'На подготовке',
    'review' => 'На проверке',
    'approved' => 'Одобрен',
    'rejected' => 'Отклонен'
];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои пациенты - ОКОЛО</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .patients-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 2rem 0;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .page-header h1 {
            color: #1e3c72;
            font-size: 2rem;
        }
        
        .search-box {
            display: flex;
            gap: 0.5rem;
            background: white;
            padding: 0.3rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .search-box input {
            border: none;
            padding: 0.7rem 1rem;
            width: 300px;
            font-size: 0.95rem;
        }
        
        .search-box input:focus {
            outline: none;
        }
        
        .search-box button {
            background: #1e3c72;
            color: white;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .stats-mini {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .stat-mini {
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .stat-mini .number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #1e3c72;
        }
        
        .stat-mini .label {
            color: #666;
        }
        
        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .filter-tab {
            padding: 0.5rem 1.5rem;
            border: none;
            background: #f0f4f8;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .filter-tab:hover {
            background: #e0e7f0;
        }
        
        .filter-tab.active {
            background: #1e3c72;
            color: white;
        }
        
        .patients-table {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            color: #1e3c72;
            font-weight: 600;
            border-bottom: 2px solid #1e3c72;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: middle;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .patient-name {
            font-weight: 600;
            color: #1e3c72;
        }
        
        .patient-contact {
            font-size: 0.8rem;
            color: #666;
        }
        
        .mkb-code {
            display: inline-block;
            background: #e8f0fe;
            color: #1e3c72;
            padding: 0.2rem 0.5rem;
            border-radius: 5px;
            font-family: monospace;
            font-size: 0.8rem;
            margin-right: 0.3rem;
        }
        
        .tests-progress {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .progress-bar-mini {
            width: 60px;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .progress-fill-mini {
            height: 100%;
            background: linear-gradient(90deg, #2a5298, #1e3c72);
            border-radius: 3px;
        }
        
        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-align: center;
            display: inline-block;
        }
        
        .status-new { background: #e0e0e0; color: #666; }
        .status-preparation { background: #fff3cd; color: #856404; }
        .status-review { background: #cce5ff; color: #004085; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        
        .action-buttons {
            display: flex;
            gap: 0.3rem;
        }
        
        .btn-icon {
            padding: 0.4rem 0.8rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.8rem;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-view {
            background: #6c757d;
            color: white;
        }
        
        .btn-edit {
            background: #ffc107;
            color: #333;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                width: 100%;
            }
            
            .search-box input {
                width: 100%;
            }
            
            .filter-tabs {
                justify-content: center;
            }
            
            table {
                font-size: 0.9rem;
            }
            
            .action-buttons {
                flex-direction: column;
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
                <a href="dashboard.php">Дашборд</a>
                <a href="patients.php" class="active">Мои пациенты</a>
                <?php if ($role === 'surgeon'): ?>
                <a href="review.php">На проверку</a>
                <?php endif; ?>
                <a href="schedule.php">Расписание</a>
                <a href="profile.php">Профиль</a>
            </div>
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($full_name); ?></span>
                <span class="role-badge">
                    <?php 
                    $roles = [
                        'ophthalmologist' => 'Офтальмолог',
                        'surgeon' => 'Хирург',
                        'patient' => 'Пациент'
                    ];
                    echo $roles[$role] ?? $role;
                    ?>
                </span>
                <a href="logout.php" class="logout-btn">Выйти</a>
            </div>
        </nav>
    </header>

    <main class="container patients-container">
        <div class="page-header">
            <h1>Мои пациенты</h1>
            
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Поиск по имени или району...">
                <button onclick="searchPatients()">🔍 Найти</button>
            </div>
        </div>

        <!-- Мини-статистика -->
        <div class="stats-mini">
            <div class="stat-mini">
                <span class="number"><?php echo count($patients); ?></span>
                <span class="label">Всего пациентов</span>
            </div>
            <div class="stat-mini">
                <span class="number">
                    <?php 
                    $preparation = array_filter($patients, function($p) { 
                        return ($p['status'] ?? '') === 'preparation'; 
                    });
                    echo count($preparation);
                    ?>
                </span>
                <span class="label">На подготовке</span>
            </div>
            <div class="stat-mini">
                <span class="number">
                    <?php 
                    $review = array_filter($patients, function($p) { 
                        return ($p['status'] ?? '') === 'review'; 
                    });
                    echo count($review);
                    ?>
                </span>
                <span class="label">На проверке</span>
            </div>
            <div class="stat-mini">
                <span class="number">
                    <?php 
                    $approved = array_filter($patients, function($p) { 
                        return ($p['status'] ?? '') === 'approved'; 
                    });
                    echo count($approved);
                    ?>
                </span>
                <span class="label">Одобрено</span>
            </div>
        </div>

        <!-- Фильтры по статусу -->
        <div class="filter-tabs">
            <button class="filter-tab active" onclick="filterPatients('all')">Все</button>
            <button class="filter-tab" onclick="filterPatients('preparation')">На подготовке</button>
            <button class="filter-tab" onclick="filterPatients('review')">На проверке</button>
            <button class="filter-tab" onclick="filterPatients('approved')">Одобренные</button>
            <button class="filter-tab" onclick="filterPatients('rejected')">Отклоненные</button>
        </div>

        <!-- Таблица пациентов -->
        <div class="patients-table">
            <?php if (empty($patients)): ?>
                <div class="empty-state">
                    <h3>Нет пациентов</h3>
                    <p>У вас пока нет назначенных пациентов</p>
                </div>
            <?php else: ?>
            <table id="patientsTable">
                <thead>
                    <tr>
                        <th>Пациент</th>
                        <th>Район</th>
                        <th>Диагноз (МКБ-10)</th>
                        <th>Операция</th>
                        <th>Анализы</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $patient): 
                        $progress = ($patient['tests_total'] ?? 0) > 0 ? 
                            round((($patient['tests_uploaded'] ?? 0) / $patient['tests_total']) * 100) : 0;
                    ?>
                    <tr data-status="<?php echo $patient['status'] ?? 'new'; ?>">
                        <td>
                            <div class="patient-name"><?php echo htmlspecialchars($patient['full_name'] ?? 'Неизвестно'); ?></div>
                            <div class="patient-contact"><?php echo htmlspecialchars($patient['phone'] ?? ''); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($patient['district'] ?? 'Не указан'); ?></td>
                        <td>
                            <?php if (!empty($patient['diagnosis_code'])): ?>
                                <span class="mkb-code"><?php echo htmlspecialchars($patient['diagnosis_code']); ?></span>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($patient['diagnosis'] ?? 'Диагноз не указан'); ?>
                        </td>
                        <td><?php echo htmlspecialchars($patient['surgery_type'] ?? 'Не назначена'); ?></td>
                        <td>
                            <div class="tests-progress">
                                <span><?php echo $patient['tests_uploaded'] ?? 0; ?>/<?php echo $patient['tests_total'] ?? 0; ?></span>
                                <div class="progress-bar-mini">
                                    <div class="progress-fill-mini" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $patient['status'] ?? 'new'; ?>">
                                <?php echo $status_labels[$patient['status'] ?? 'new'] ?? 'Новый'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="patient_detail.php?id=<?php echo $patient['id']; ?>" class="btn-icon btn-view">👁️</a>
                                <?php if ($role === 'ophthalmologist'): ?>
                                <a href="edit_patient.php?id=<?php echo $patient['id']; ?>" class="btn-icon btn-edit">✏️</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 ОКОЛО</p>
    </footer>

    <script src="assets/js/script.js"></script>
    <script>
        function searchPatients() {
            const searchText = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#patientsTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchText)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        function filterPatients(status) {
            const rows = document.querySelectorAll('#patientsTable tbody tr');
            const tabs = document.querySelectorAll('.filter-tab');
            
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            rows.forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Поиск при нажатии Enter
        document.getElementById('searchInput').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                searchPatients();
            }
        });
    </script>
</body>
</html>