<?php
require_once 'includes/auth.php';
requireLogin();

$patient_id = $_GET['id'] ?? 0;
$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];

// Получаем информацию о пациенте
$stmt = $pdo->prepare("
    SELECT p.*, u.full_name, u.district, u.email, u.username,
        d.name as diagnosis, d.description as diagnosis_desc,
        s.id as surgery_id, s.surgery_type, s.status, s.surgery_date, s.notes,
        (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id) as tests_total,
        (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id AND status = 'uploaded') as tests_uploaded,
        (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id AND status = 'approved') as tests_approved,
        doc.full_name as doctor_name,
        surg.full_name as surgeon_name
    FROM patients p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN surgeries s ON p.id = s.patient_id
    LEFT JOIN diseases d ON s.disease_id = d.id
    LEFT JOIN users doc ON p.doctor_id = doc.id
    LEFT JOIN users surg ON p.surgeon_id = surg.id
    WHERE p.id = ?
");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch();

if (!$patient) {
    header('Location: patients.php');
    exit();
}

// Получаем список анализов
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
$stmt->execute([$patient['surgery_id']]);
$tests = $stmt->fetchAll();

// Обработка загрузки анализа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_test'])) {
    $test_id = $_POST['test_id'];
    $status = $_POST['status'] ?? 'uploaded';
    
    // Здесь должна быть логика загрузки файла
    // В демо-версии просто обновляем статус
    
    $stmt = $pdo->prepare("UPDATE tests SET status = ?, uploaded_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $test_id]);
    
    header("Location: patient_detail.php?id=$patient_id&success=1");
    exit();
}

// Обработка обновления статуса операции
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $surgery_notes = $_POST['notes'] ?? '';
    
    $stmt = $pdo->prepare("UPDATE surgeries SET status = ?, notes = ? WHERE id = ?");
    $stmt->execute([$new_status, $surgery_notes, $patient['surgery_id']]);
    
    header("Location: patient_detail.php?id=$patient_id&updated=1");
    exit();
}

// Обработка назначения даты операции
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_surgery'])) {
    $surgery_date = $_POST['surgery_date'];
    
    $stmt = $pdo->prepare("UPDATE surgeries SET surgery_date = ?, status = 'approved' WHERE id = ?");
    $stmt->execute([$surgery_date, $patient['surgery_id']]);
    
    header("Location: patient_detail.php?id=$patient_id&scheduled=1");
    exit();
}

$success = isset($_GET['success']);
$updated = isset($_GET['updated']);
$scheduled = isset($_GET['scheduled']);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Карта пациента - ОКОЛО</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .patient-profile {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f4f8;
        }
        
        .patient-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
        }
        
        .info-label {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }
        
        .info-value {
            color: #1e3c72;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .tests-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            margin: 2rem 0;
        }
        
        .test-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
            transition: all 0.3s ease;
        }
        
        .test-item:hover {
            background: white;
            transform: translateX(5px);
            border-radius: 8px;
        }
        
        .test-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-icon {
            padding: 0.3rem 0.8rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-icon:hover {
            transform: scale(1.05);
        }
        
        .status-selector {
            padding: 0.3rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-right: 0.5rem;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            animation: slideIn 0.3s ease-out;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
            animation: slideIn 0.3s ease-out;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .close-modal {
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        
        .close-modal:hover {
            color: #dc3545;
        }
        
        @media (max-width: 768px) {
            .test-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .test-actions {
                width: 100%;
                justify-content: flex-end;
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
        <a href="dashboard.php" <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'class="active"' : ''; ?>>Дашборд</a>
        
        <?php if ($role === 'ophthalmologist' || $role === 'surgeon'): ?>
            <a href="patients.php" <?php echo basename($_SERVER['PHP_SELF']) == 'patients.php' ? 'class="active"' : ''; ?>>Мои пациенты</a>
        <?php endif; ?>
        
        <?php if ($role === 'patient'): ?>
            <a href="check_status.php" <?php echo basename($_SERVER['PHP_SELF']) == 'check_status.php' ? 'class="active"' : ''; ?>>Статус подготовки</a>
        <?php endif; ?>
        
        <a href="schedule.php" <?php echo basename($_SERVER['PHP_SELF']) == 'schedule.php' ? 'class="active"' : ''; ?>>Расписание</a>
        
        <?php if ($role === 'surgeon'): ?>
            <a href="review.php" <?php echo basename($_SERVER['PHP_SELF']) == 'review.php' ? 'class="active"' : ''; ?>>На проверку</a>
        <?php endif; ?>
        
        <a href="profile.php" <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'class="active"' : ''; ?>>Профиль</a>
    </div>
    <div class="user-info">
        <span class="user-name"><?php echo htmlspecialchars($full_name); ?></span>
        <span class="role-badge"><?php 
            $roles = [
                'patient' => 'Пациент',
                'ophthalmologist' => 'Офтальмолог',
                'surgeon' => 'Хирург'
            ];
            echo $roles[$role] ?? $role;
        ?></span>
        <a href="logout.php" class="logout-btn">Выйти</a>
    </div>
</nav>
    </header>

    <main class="container">
        <?php if ($success): ?>
        <div class="success-message">Анализ успешно загружен!</div>
        <?php endif; ?>
        
        <?php if ($updated): ?>
        <div class="success-message">Статус операции обновлен!</div>
        <?php endif; ?>
        
        <?php if ($scheduled): ?>
        <div class="success-message">Дата операции назначена!</div>
        <?php endif; ?>

        <div class="patient-profile">
            <div class="profile-header">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div class="patient-avatar">
                        <?php echo mb_substr($patient['full_name'], 0, 1); ?>
                    </div>
                    <div>
                        <h1><?php echo htmlspecialchars($patient['full_name']); ?></h1>
                        <p style="color: #666;">ID: <?php echo str_pad($patient['id'], 6, '0', STR_PAD_LEFT); ?></p>
                    </div>
                </div>
                <span class="status-badge status-<?php echo $patient['status']; ?>">
                    <?php 
                    $statuses = [
                        'new' => 'Новый',
                        'preparation' => 'На подготовке',
                        'review' => 'На проверке',
                        'approved' => 'Одобрен',
                        'rejected' => 'Отклонен'
                    ];
                    echo $statuses[$patient['status']] ?? $patient['status'];
                    ?>
                </span>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Район</div>
                    <div class="info-value"><?php echo htmlspecialchars($patient['district']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?php echo htmlspecialchars($patient['email'] ?: 'Не указан'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Районный офтальмолог</div>
                    <div class="info-value"><?php echo htmlspecialchars($patient['doctor_name'] ?: 'Не назначен'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Хирург-куратор</div>
                    <div class="info-value"><?php echo htmlspecialchars($patient['surgeon_name'] ?: 'Не назначен'); ?></div>
                </div>
            </div>

            <div style="margin: 2rem 0;">
                <h2 class="section-title">Диагноз и операция</h2>
                <div style="background: #e8f0fe; padding: 1.5rem; border-radius: 10px;">
                    <p><strong>Диагноз:</strong> <?php echo htmlspecialchars($patient['diagnosis']); ?></p>
                    <p><strong>Описание:</strong> <?php echo htmlspecialchars($patient['diagnosis_desc'] ?: 'Нет описания'); ?></p>
                    <p><strong>Тип операции:</strong> <?php echo htmlspecialchars($patient['surgery_type']); ?></p>
                    <?php if ($patient['surgery_date']): ?>
                    <p><strong>Дата операции:</strong> <?php echo date('d.m.Y H:i', strtotime($patient['surgery_date'])); ?></p>
                    <?php endif; ?>
                    <?php if ($patient['notes']): ?>
                    <p><strong>Примечания:</strong> <?php echo nl2br(htmlspecialchars($patient['notes'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tests-section">
                <h2 style="margin-bottom: 1rem;">Анализы и обследования</h2>
                <div style="display: flex; gap: 2rem; margin-bottom: 2rem;">
                    <div>
                        <strong>Всего анализов:</strong> <?php echo $patient['tests_total']; ?>
                    </div>
                    <div>
                        <strong>Загружено:</strong> <?php echo $patient['tests_uploaded']; ?>
                    </div>
                    <div>
                        <strong>Принято:</strong> <?php echo $patient['tests_approved']; ?>
                    </div>
                </div>

                <div class="tests-list">
                    <?php foreach ($tests as $test): ?>
                    <div class="test-item" id="test-<?php echo $test['id']; ?>">
                        <div>
                            <strong><?php echo htmlspecialchars($test['test_name']); ?></strong>
                            <?php if ($test['uploaded_at']): ?>
                            <br><small>Загружен: <?php echo date('d.m.Y H:i', strtotime($test['uploaded_at'])); ?></small>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <span class="test-status <?php echo $test['status']; ?>">
                                <?php 
                                $test_statuses = [
                                    'pending' => 'Ожидает',
                                    'uploaded' => 'Загружен',
                                    'approved' => 'Принят',
                                    'rejected' => 'Отклонен'
                                ];
                                echo $test_statuses[$test['status']] ?? $test['status'];
                                ?>
                            </span>
                            
                            <?php if ($role === 'ophthalmologist' && $test['status'] === 'pending'): ?>
                            <form method="POST" style="display: inline;" enctype="multipart/form-data">
                                <input type="hidden" name="test_id" value="<?php echo $test['id']; ?>">
                                <input type="hidden" name="upload_test" value="1">
                                <input type="hidden" name="status" value="uploaded">
                                <button type="submit" class="btn-icon" style="background: #28a745; color: white;">
                                    Загрузить
                                </button>
                            </form>
                            <?php endif; ?>
                            
                            <?php if ($role === 'surgeon' && in_array($test['status'], ['uploaded', 'pending'])): ?>
                            <div class="test-actions">
                                <select class="status-selector" onchange="updateTestStatus(<?php echo $test['id']; ?>, this.value)">
                                    <option value="approved" <?php echo $test['status'] === 'approved' ? 'selected' : ''; ?>>Принять</option>
                                    <option value="rejected" <?php echo $test['status'] === 'rejected' ? 'selected' : ''; ?>>Отклонить</option>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($role === 'ophthalmologist' || $role === 'surgeon'): ?>
            <div class="action-buttons">
                <?php if ($role === 'ophthalmologist'): ?>
                <button class="btn" onclick="openModal('statusModal')" style="width: auto;">
                    Изменить статус
                </button>
                <?php endif; ?>
                
                <?php if ($role === 'surgeon' && $patient['tests_approved'] == $patient['tests_total']): ?>
                <button class="btn" onclick="openModal('scheduleModal')" style="width: auto; background: #28a745;">
                    Назначить дату операции
                </button>
                <?php endif; ?>
                
                <button class="btn" onclick="window.print()" style="width: auto; background: #6c757d;">
                    Печать карты
                </button>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Модальное окно изменения статуса -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Изменить статус операции</h2>
                <span class="close-modal" onclick="closeModal('statusModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="update_status" value="1">
                
                <div class="form-group">
                    <label for="status">Статус:</label>
                    <select name="status" id="status" class="status-selector" style="width: 100%; padding: 0.8rem;">
                        <option value="new" <?php echo $patient['status'] === 'new' ? 'selected' : ''; ?>>Новый</option>
                        <option value="preparation" <?php echo $patient['status'] === 'preparation' ? 'selected' : ''; ?>>На подготовке</option>
                        <option value="review" <?php echo $patient['status'] === 'review' ? 'selected' : ''; ?>>На проверке</option>
                        <option value="approved" <?php echo $patient['status'] === 'approved' ? 'selected' : ''; ?>>Одобрен</option>
                        <option value="rejected" <?php echo $patient['status'] === 'rejected' ? 'selected' : ''; ?>>Отклонен</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="notes">Примечания:</label>
                    <textarea name="notes" id="notes" rows="4" style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 5px;"><?php echo htmlspecialchars($patient['notes']); ?></textarea>
                </div>
                
                <button type="submit" class="btn">Сохранить изменения</button>
            </form>
        </div>
    </div>

    <!-- Модальное окно назначения даты операции -->
    <div id="scheduleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Назначить дату операции</h2>
                <span class="close-modal" onclick="closeModal('scheduleModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="schedule_surgery" value="1">
                
                <div class="form-group">
                    <label for="surgery_date">Дата и время операции:</label>
                    <input type="datetime-local" name="surgery_date" id="surgery_date" required 
                           style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 5px;">
                </div>
                
                <button type="submit" class="btn" style="background: #28a745;">Назначить</button>
            </form>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 ОКОЛО</p>
    </footer>

    <script src="assets/js/script.js"></script>
    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function updateTestStatus(testId, status) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const testIdInput = document.createElement('input');
            testIdInput.name = 'test_id';
            testIdInput.value = testId;
            
            const statusInput = document.createElement('input');
            statusInput.name = 'status';
            statusInput.value = status;
            
            const uploadInput = document.createElement('input');
            uploadInput.name = 'upload_test';
            uploadInput.value = '1';
            
            form.appendChild(testIdInput);
            form.appendChild(statusInput);
            form.appendChild(uploadInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Закрытие модального окна при клике вне его
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Установка минимальной даты для выбора (сегодня)
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('surgery_date');
            if (dateInput) {
                const now = new Date();
                now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                dateInput.min = now.toISOString().slice(0, 16);
            }
        });
    </script>
</body>
</html>