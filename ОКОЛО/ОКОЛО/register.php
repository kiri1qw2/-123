<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Основные поля пользователя
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $role = $_POST['role'] ?? 'patient';
    $district = $_POST['district'] ?? '';
    
    // Дополнительные поля для пациента
    $passport_series = $_POST['passport_series'] ?? '';
    $passport_number = $_POST['passport_number'] ?? '';
    $passport_issued = $_POST['passport_issued'] ?? '';
    $passport_date = $_POST['passport_date'] ?? '';
    $snils = $_POST['snils'] ?? '';
    $polis = $_POST['polis'] ?? '';
    $birth_date = $_POST['birth_date'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $address = $_POST['address'] ?? '';
    $emergency_contact = $_POST['emergency_contact'] ?? '';
    $blood_type = $_POST['blood_type'] ?? '';
    $allergies = $_POST['allergies'] ?? '';
    
    // Валидация
    if (empty($username) || empty($password) || empty($full_name) || empty($email)) {
        $error = 'Пожалуйста, заполните все обязательные поля';
    } elseif ($password !== $confirm_password) {
        $error = 'Пароли не совпадают';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль должен содержать минимум 6 символов';
    } else {
        // Проверка уникальности username и email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = 'Имя пользователя или email уже используются';
        } else {
            // Хеширование пароля
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Вставка пользователя с телефоном
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, full_name, email, phone, role, district) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$username, $hashed_password, $full_name, $email, $phone, $role, $district])) {
                $user_id = $pdo->lastInsertId();
                
                // Если это пациент, создаем запись в таблице patients со всеми данными
                if ($role === 'patient') {
                    $stmt = $pdo->prepare("
                        INSERT INTO patients (
                            user_id, district, passport_series, passport_number, 
                            passport_issued, passport_date, snils, polis, 
                            birth_date, gender, address, emergency_contact, 
                            blood_type, allergies
                        ) VALUES (
                            ?, ?, ?, ?, 
                            ?, ?, ?, ?, 
                            ?, ?, ?, ?, 
                            ?, ?
                        )
                    ");
                    $stmt->execute([
                        $user_id, $district, $passport_series, $passport_number,
                        $passport_issued, $passport_date, $snils, $polis,
                        $birth_date, $gender, $address, $emergency_contact,
                        $blood_type, $allergies
                    ]);
                }
                
                $success = 'Регистрация успешна! Теперь вы можете войти в систему.';
                
                // Очистка формы
                $_POST = [];
            } else {
                $error = 'Ошибка при регистрации. Пожалуйста, попробуйте позже.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация - ОКОЛО</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .register-container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            animation: slideIn 0.5s ease-out;
        }
        
        .register-container h2 {
            color: #1e3c72;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 2rem;
        }
        
        .form-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid #1e3c72;
        }
        
        .form-section h3 {
            color: #1e3c72;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #2a5298;
            box-shadow: 0 0 0 3px rgba(42,82,152,0.1);
        }
        
        .form-group input[readonly] {
            background: #f8f9fa;
            cursor: not-allowed;
        }
        
        .password-requirements {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.3rem;
            padding-left: 1rem;
        }
        
        .password-requirements ul {
            margin-top: 0.3rem;
            list-style-type: disc;
            padding-left: 1.5rem;
        }
        
        .role-info {
            background: #e8f0fe;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        
        .role-info p {
            margin: 0.5rem 0;
            color: #1e3c72;
        }
        
        .role-info i {
            font-weight: 500;
        }
        
        .btn-register {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1rem;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            animation: slideIn 0.3s ease-out;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e0e0e0;
        }
        
        .login-link a {
            color: #2a5298;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .district-hint {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.3rem;
        }
        
        .required::after {
            content: " *";
            color: #dc3545;
        }
        
        .emias-badge {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 5px;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .register-container {
                margin: 1rem;
                padding: 1.5rem;
            }
            
            .form-row {
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
                <a href="index.php">Главная</a>
                <a href="login.php">Вход</a>
                <a href="register.php" class="active">Регистрация</a>
                <a href="check_status.php">Статус подготовки</a>
            </div>
        </nav>
    </header>

    <main class="container">
        <div class="register-container">
            <h2>Регистрация в системе</h2>
            
            <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
                <br>
                <a href="login.php" style="color: #155724; font-weight: 500;">Перейти к входу</a>
            </div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm">
                <!-- Основная информация -->
                <div class="form-section">
                    <h3>📋 Основная информация</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">Имя пользователя</label>
                            <input type="text" name="username" 
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                                   required minlength="3" maxlength="50"
                                   pattern="[a-zA-Z0-9_]+" 
                                   title="Только латинские буквы, цифры и знак подчеркивания">
                            <small>Только латинские буквы, цифры и _</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Введите полное ФИО</label>
                            <input type="text" name="full_name" 
                                   value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">Email</label>
                            <input type="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label>Телефон</label>
                            <input type="text" name="phone" id="phone"
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                                   placeholder="+7 (___) ___-__-__">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">Роль в системе</label>
                            <select name="role" id="role" required onchange="togglePatientFields()">
                                <option value="patient" <?php echo ($_POST['role'] ?? 'patient') === 'patient' ? 'selected' : ''; ?>>Пациент</option>
                                <option value="ophthalmologist" <?php echo ($_POST['role'] ?? '') === 'ophthalmologist' ? 'selected' : ''; ?>>Районный офтальмолог</option>
                                <option value="surgeon" <?php echo ($_POST['role'] ?? '') === 'surgeon' ? 'selected' : ''; ?>>Хирург-куратор</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Район</label>
                            <select name="district" id="district" required>
                                <option value="">Выберите район</option>
                                <option value="Кировский" <?php echo ($_POST['district'] ?? '') === 'Кировский' ? 'selected' : ''; ?>>Кировский район</option>
                                <option value="Первомайский" <?php echo ($_POST['district'] ?? '') === 'Первомайский' ? 'selected' : ''; ?>>Первомайский район</option>
                                <option value="Октябрьский" <?php echo ($_POST['district'] ?? '') === 'Октябрьский' ? 'selected' : ''; ?>>Октябрьский район</option>
                                <option value="Свердловский" <?php echo ($_POST['district'] ?? '') === 'Свердловский' ? 'selected' : ''; ?>>Свердловский район</option>
                                <option value="Ленинский" <?php echo ($_POST['district'] ?? '') === 'Ленинский' ? 'selected' : ''; ?>>Ленинский район</option>
                                <option value="Областной центр" <?php echo ($_POST['district'] ?? '') === 'Областной центр' ? 'selected' : ''; ?>>Областной центр</option>
                            </select>
                            <div class="district-hint" id="district-hint">Укажите ваш район</div>
                        </div>
                    </div>
                </div>

                <!-- Пароль -->
                <div class="form-section">
                    <h3>🔐 Безопасность</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">Пароль</label>
                            <input type="password" name="password" id="password" required minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Подтверждение пароля</label>
                            <input type="password" name="confirm_password" id="confirm_password" required>
                        </div>
                    </div>
                    
                    <div class="password-requirements">
                        <strong>Требования к паролю:</strong>
                        <ul>
                            <li>Минимум 6 символов</li>
                            <li>Содержит буквы и цифры</li>
                            <li>Не должен содержать личные данные</li>
                        </ul>
                    </div>
                </div>

                <!-- Документы (только для пациентов) -->
                <div id="patient-fields">
                    <div class="form-section">
                        <h3>🪪 Паспортные данные</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Серия паспорта</label>
                                <input type="text" name="passport_series" id="passport_series"
                                       value="<?php echo htmlspecialchars($_POST['passport_series'] ?? ''); ?>" 
                                       maxlength="4" placeholder="0000">
                            </div>
                            
                            <div class="form-group">
                                <label>Номер паспорта</label>
                                <input type="text" name="passport_number" id="passport_number"
                                       value="<?php echo htmlspecialchars($_POST['passport_number'] ?? ''); ?>" 
                                       maxlength="6" placeholder="000000">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Кем выдан</label>
                            <input type="text" name="passport_issued" 
                                   value="<?php echo htmlspecialchars($_POST['passport_issued'] ?? ''); ?>" 
                                   placeholder="Наименование отделения">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Дата выдачи</label>
                                <input type="date" name="passport_date" 
                                       value="<?php echo $_POST['passport_date'] ?? ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Код подразделения</label>
                                <input type="text" value="000-000" readonly class="readonly" placeholder="Заглушка ЕМИАС">
                                <small class="emias-badge">ЕМИАС</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>📄 СНИЛС и полис</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>СНИЛС</label>
                                <input type="text" name="snils" id="snils"
                                       value="<?php echo htmlspecialchars($_POST['snils'] ?? ''); ?>" 
                                       placeholder="000-000-000 00">
                                <small>Интеграция с ЕМИАС</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Полис ОМС</label>
                                <input type="text" name="polis" id="polis"
                                       value="<?php echo htmlspecialchars($_POST['polis'] ?? ''); ?>" 
                                       placeholder="0000000000000000" maxlength="16">
                                <small>ЕМИАС: проверка данных</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>👤 Личные данные</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Дата рождения</label>
                                <input type="date" name="birth_date" 
                                       value="<?php echo $_POST['birth_date'] ?? ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Пол</label>
                                <select name="gender">
                                    <option value="">Не указан</option>
                                    <option value="Мужской" <?php echo ($_POST['gender'] ?? '') === 'Мужской' ? 'selected' : ''; ?>>Мужской</option>
                                    <option value="Женский" <?php echo ($_POST['gender'] ?? '') === 'Женский' ? 'selected' : ''; ?>>Женский</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Адрес проживания</label>
                            <input type="text" name="address" 
                                   value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>" 
                                   placeholder="Город, улица, дом, квартира">
                        </div>
                        
                        <div class="form-group">
                            <label>Контакт для экстренных случаев</label>
                            <input type="text" name="emergency_contact" 
                                   value="<?php echo htmlspecialchars($_POST['emergency_contact'] ?? ''); ?>" 
                                   placeholder="ФИО, телефон">
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>⚕️ Медицинские данные</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Группа крови</label>
                                <select name="blood_type">
                                    <option value="">Не указана</option>
                                    <option value="0(I)" <?php echo ($_POST['blood_type'] ?? '') === '0(I)' ? 'selected' : ''; ?>>0(I)</option>
                                    <option value="A(II)" <?php echo ($_POST['blood_type'] ?? '') === 'A(II)' ? 'selected' : ''; ?>>A(II)</option>
                                    <option value="B(III)" <?php echo ($_POST['blood_type'] ?? '') === 'B(III)' ? 'selected' : ''; ?>>B(III)</option>
                                    <option value="AB(IV)" <?php echo ($_POST['blood_type'] ?? '') === 'AB(IV)' ? 'selected' : ''; ?>>AB(IV)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Аллергии</label>
                                <input type="text" name="allergies" 
                                       value="<?php echo htmlspecialchars($_POST['allergies'] ?? ''); ?>" 
                                       placeholder="Через запятую">
                            </div>
                        </div>
                    </div>
                </div>

                

                <button type="submit" class="btn-register">Зарегистрироваться</button>

                <div class="login-link">
                    Уже есть аккаунт? <a href="login.php">Войти в систему</a>
                </div>
            </form>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 ОКОЛО. Интеграция с ЕМИАС </p>
    </footer>

    <script src="assets/js/script.js"></script>
    <script>
        function togglePatientFields() {
            const role = document.getElementById('role').value;
            const patientFields = document.getElementById('patient-fields');
            const districtSelect = document.getElementById('district');
            const districtHint = document.getElementById('district-hint');
            
            if (role === 'patient') {
                patientFields.style.display = 'block';
                districtHint.innerHTML = 'Укажите район проживания';
                districtSelect.disabled = false;
            } else if (role === 'surgeon') {
                patientFields.style.display = 'none';
                districtSelect.value = 'Областной центр';
                districtSelect.disabled = true;
                districtHint.innerHTML = 'Хирурги работают в областном центре';
            } else {
                patientFields.style.display = 'none';
                districtSelect.disabled = false;
                districtHint.innerHTML = 'Укажите район работы';
            }
        }
        
        // Форматирование телефона
        document.getElementById('phone')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                if (value.length <= 1) {
                    value = '+7' + value;
                } else if (value.length <= 4) {
                    value = '+7 (' + value.substring(1, 4);
                } else if (value.length <= 7) {
                    value = '+7 (' + value.substring(1, 4) + ') ' + value.substring(4, 7);
                } else if (value.length <= 9) {
                    value = '+7 (' + value.substring(1, 4) + ') ' + value.substring(4, 7) + '-' + value.substring(7, 9);
                } else {
                    value = '+7 (' + value.substring(1, 4) + ') ' + value.substring(4, 7) + '-' + value.substring(7, 9) + '-' + value.substring(9, 11);
                }
                e.target.value = value;
            }
        });
        
        // Форматирование СНИЛС
        document.getElementById('snils')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 3) {
                value = value.substring(0,3) + '-' + value.substring(3);
            }
            if (value.length > 7) {
                value = value.substring(0,7) + '-' + value.substring(7);
            }
            if (value.length > 11) {
                value = value.substring(0,11) + ' ' + value.substring(11,13);
            }
            e.target.value = value;
        });
        
        // Форматирование полиса
        document.getElementById('polis')?.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0,16);
        });
        
        // Форматирование паспорта
        document.getElementById('passport_series')?.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0,4);
        });
        
        document.getElementById('passport_number')?.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0,6);
        });
        
        // Валидация формы
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const username = document.getElementById('username').value;
            
            // Проверка пароля
            if (password.length < 6) {
                e.preventDefault();
                alert('Пароль должен содержать минимум 6 символов');
                return;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Пароли не совпадают');
                return;
            }
            
            // Проверка username
            const usernameRegex = /^[a-zA-Z0-9_]+$/;
            if (!usernameRegex.test(username)) {
                e.preventDefault();
                alert('Имя пользователя может содержать только латинские буквы, цифры и знак подчеркивания');
                return;
            }
            
            // Проверка email
            const email = document.getElementById('email').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Введите корректный email адрес');
                return;
            }
        });
        
        // Инициализация при загрузке
        document.addEventListener('DOMContentLoaded', function() {
            togglePatientFields();
        });
    </script>
</body>
</html>