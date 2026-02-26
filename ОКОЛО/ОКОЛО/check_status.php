<?php
require_once 'includes/config.php';

$patient_data = null;
$search_performed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['full_name'])) {
    $full_name = $_POST['full_name'];
    $search_performed = true;
    
    $stmt = $pdo->prepare("
        SELECT u.*, p.id as patient_id, s.id as surgery_id, 
            s.status, s.surgery_date, d.name as diagnosis,
            (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id AND status IN ('uploaded', 'approved')) as tests_completed,
            (SELECT COUNT(*) FROM tests WHERE surgery_id = s.id) as tests_total
        FROM users u
        JOIN patients p ON u.id = p.user_id
        LEFT JOIN surgeries s ON p.id = s.patient_id
        LEFT JOIN diseases d ON s.disease_id = d.id
        WHERE u.full_name LIKE ? AND u.role = 'patient'
        ORDER BY s.created_at DESC
    ");
    $stmt->execute(["%$full_name%"]);
    $patient_data = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ru"></html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Проверка статуса подготовки - ОКОЛО</title>
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
                <a href="index.php">Главная</a>
                <a href="login.php">Вход</a>
                <a href="register.php">Регистрация</a>
                <a href="check_status.php" class="active">Статус подготовки</a>
            </div>
        </nav>
    </header>

    <main class="container">
        <div class="status-check">
            <h2>Проверка статуса подготовки</h2>
            <p>Введите ваше ФИО для просмотра статуса</p>
            
            <form method="POST" action="" class="search-box">
                <input type="text" name="full_name" placeholder="Иванов Пётр Сергеевич" required>
                <button type="submit" class="btn-small">Найти</button>
            </form>

            <?php if ($search_performed): ?>
                <?php if ($patient_data): ?>
                    <?php foreach ($patient_data as $patient): ?>
                    <div class="patient-status-card">
                        <h3><?php echo htmlspecialchars($patient['full_name']); ?></h3>
                        <p><strong><?php echo htmlspecialchars($patient['diagnosis']); ?></strong></p>
                        
                        <div class="progress-steps">
                            <span class="step <?php echo $patient['status'] != 'new' ? 'completed' : ''; ?>">Новый</span>
                            <span class="step <?php echo in_array($patient['status'], ['preparation', 'review', 'approved']) ? 'active' : ''; ?>">Подготовка</span>
                            <span class="step <?php echo in_array($patient['status'], ['review', 'approved']) ? 'active' : ''; ?>">Проверка</span>
                            <span class="step <?php echo $patient['status'] == 'approved' ? 'completed' : ''; ?>">Одобрен</span>
                        </div>
                        
                        <?php if ($patient['surgery_date']): ?>
                        <p><strong>Дата операции:</strong> <?php echo date('d.m.Y', strtotime($patient['surgery_date'])); ?></p>
                        <?php endif; ?>

                        <h4 style="margin-top: 2rem;">Анализы <?php echo $patient['tests_completed']; ?>/<?php echo $patient['tests_total']; ?></h4>
                        
                        <?php
                        // Получаем список анализов
                        $stmt = $pdo->prepare("
                            SELECT test_name, status 
                            FROM tests 
                            WHERE surgery_id = ?
                        ");
                        $stmt->execute([$patient['surgery_id']]);
                        $tests = $stmt->fetchAll();
                        ?>
                        
                        <ul class="tests-list">
                            <?php foreach ($tests as $test): ?>
                            <li class="test-item">
                                <span class="test-name"><?php echo htmlspecialchars($test['test_name']); ?></span>
                                <span class="test-status <?php echo $test['status']; ?>">
                                    <?php 
                                    $statuses = [
                                        'pending' => 'Ожидает',
                                        'uploaded' => 'Загружен',
                                        'approved' => 'Принят',
                                        'rejected' => 'Отклонен'
                                    ];
                                    echo $statuses[$test['status']] ?? $test['status'];
                                    ?>
                                </span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #dc3545;">Пациент не найден</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 ОКОЛО</p>
    </footer>

    <script src="assets/js/script.js"></script>
</body>
</html>