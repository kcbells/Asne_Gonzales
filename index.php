<?php
ob_start(); 
require_once "conn.php";
session_start();

$login_error = '';
$identifier = ''; // Initialize to prevent Undefined Variable warning

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    $user_captcha = $_POST['captcha_answer'] ?? '';
    
    // ... rest of your code ...

    // Verify CAPTCHA first
    if ($identifier === '' || $password === '') {
        $login_error = 'Please provide email/username and password.';
    } elseif (!isset($_SESSION['captcha_total']) || (int)$user_captcha !== $_SESSION['captcha_total']) {
        $login_error = 'Incorrect CAPTCHA answer. Please try again.';
    } else {
        // Prepare statement to find user by email or username
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1");
        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($user = $res->fetch_assoc()) {
            // Verify the hashed password
            if (password_verify($password, $user['password_hash'])) {
                
                // Regenerate session ID for security
                session_regenerate_id(true);  

                // Store critical user data in Session
                $_SESSION['user_id']  = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = strtolower(trim($user['role'])); // Clean and normalize role

                // Success! Redirect based on normalized role
                switch ($_SESSION['role']) {
                    case 'admin':
                        header('Location: admin.php');
                        exit;
                        
                    case 'owner':
                       
                        header('Location: owner_modules/owner.php');
                        exit;
                        
                    case 'tenant':
                        
                        header('Location: tenant_modules/tenant.php');
                        exit;

                    default:
                        $login_error = 'User role "' . htmlspecialchars($user['role']) . '" not recognized. Contact Admin.';
                        break;
                }
            } else {
                $login_error = 'Invalid password. Please try again.';
            }
        } else {
            $login_error = 'No account found with that email or username.';
        }
        $stmt->close();
    }
}

// Generate new CAPTCHA numbers for the form display
$num1 = rand(1, 10);
$num2 = rand(1, 10);
$_SESSION['captcha_total'] = $num1 + $num2;
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>PMS Login</title>
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/index.css" />
  <style>
    :root { --brand-color: #0d6efd; --brand-sub: #6c757d; }
    body { font-family: 'Inter', sans-serif; }
    .auth-error { color:#fff; background:#d9534f; padding:10px; border-radius:6px; margin-bottom:15px; text-align:center; width:100%; }
    .field { position: relative; margin-bottom: 15px; }
    .field input { width: 100%; padding: 10px 10px 10px 40px; border: 1px solid #ddd; border-radius: 4px; }
    .field .icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 20px; fill: #6c757d; }
  </style>
</head>

<body>
  <main class="shell">
    <section class="panel auth" style="max-width: 400px; margin: 50px auto; padding: 20px;">
      <div style="display:flex;flex-direction:column;align-items:center;gap:12px">
        <img src="assets/img/logo3.png" alt="KCI logo" style="width:120px;height:auto;" />
        <h4 class="mb-3">Sign In</h4>
        
        <?php if (!empty($login_error)): ?>
          <div class="auth-error">
            <?= htmlspecialchars($login_error) ?>
          </div>
        <?php endif; ?>

        <form method="POST" style="width: 100%;">
          <div>
            <label class="form-label">Email or Username</label>
            <div class="field">
              <svg class="icon" viewBox="0 0 24 24"><path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 4-8 5L4 8V6l8 5 8-5v2Z" /></svg>
              <input name="identifier" type="text" placeholder="Enter Email or Username" required value="<?= htmlspecialchars($identifier) ?>" />
            </div>
          </div>

          <div>
            <label class="form-label">Password</label>
            <div class="field">
              <svg class="icon" viewBox="0 0 24 24"><path d="M17 9h-1V7a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2Zm-7 0V7a2 2 0 1 1 4 0v2h-4Z" /></svg>
              <input name="password" type="password" placeholder="Enter Password" required />
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Security Check: <strong><?= $num1 ?> + <?= $num2 ?> = ?</strong></label>
            <div class="field">
              <svg class="icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z" /></svg>
              <input name="captcha_answer" type="number" placeholder="Result" required />
            </div>
          </div>

          <button class="btn btn-primary w-100 py-2" type="submit">Sign in</button>
        </form>
        
        <div class="mt-3">
            <a href="javascript:void(0)" onclick="alert('Contact Admin at support@example.com to reset password.')" class="text-decoration-none small">Forgot Password?</a>
        </div>
      </div>
    </section>
  </main>
</body>
</html>