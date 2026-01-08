<?php
require_once "conn.php";
session_start();
$login_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    $user_captcha = $_POST['captcha_answer'] ?? '';

    // Verify CAPTCHA first
    if ($identifier === '' || $password === '') {
        $login_error = 'Please provide email/username and password.';
    } elseif ((int)$user_captcha !== $_SESSION['captcha_total']) {
        $login_error = 'Incorrect CAPTCHA answer. Please try again.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1");
        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($user = $res->fetch_assoc()) {
            if (password_verify($password, $user['password_hash'])) {
                // ... (Keep your existing session and logging logic here) ...
                
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Success! Redirecting...
                if (strtolower($user['role']) === 'admin') {
                    header('Location: admin.php');
                    exit;
                } else {
                    header('Location: modules/dashboard.php');
                    exit;
                }
            } else {
                $login_error = 'Invalid credentials.';
            }
        } else {
            $login_error = 'User not found.';
        }
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
  <meta name="description" content="Login page for a Property Management System (PMS)." />
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/index.css" />
  <style>
    :root {
      --brand-color: #0d6efd;
      --brand-sub: #6c757d;
    }

    @media (prefers-color-scheme: dark) {
      :root {
        --brand-color: #9ad0ff;
        --brand-sub: #cfe8ff;
      }
    }

    .panel.brand .logo {
      display: flex;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      gap: 8px;
      padding: 32px 12px
    }

    .panel.brand .logo img {
      width: 140px;
      max-width: 60%;
      height: auto;
      display: block
    }

    .panel.brand .brand-text {
      text-align: center
    }

    .panel.brand .brand-text h1 {
      margin: 0;
      font-size: 1.6rem;
      color: var(--brand-color);
      font-weight: 700
    }

    .panel.brand .brand-text span {
      color: var(--brand-sub);
      display: block;
      margin-top: 6px;
      font-size: 1rem
    }

    .panel.brand .hero,
    .panel.brand .stats,
    .panel.brand .footnote {
      display: none
    }
  </style>
</head>

<body>
  <main class="shell">

    <!-- Auth / Form -->
    <section class="panel auth" aria-label="Login form">
      <div style="display:flex;flex-direction:column;align-items:center;gap:12px">
        <img src="assets/img/logo3.png" alt="KCI logo" style="width:120px;height:auto;display:block" />
        <h3></h3>
        <?php if (!empty($login_error)): ?>
          <div style="color:#fff;background:#d9534f;padding:8px;border-radius:6px;margin-bottom:12px">
            <?= htmlspecialchars($login_error) ?>
          </div>
        <?php endif; ?>

        <form class="row" method="POST" autocomplete="on">
          <div>
            <label for="identifier">
              Email or Username
            </label>
            <div class="field">
              <svg class="icon" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 4-8 5L4 8V6l8 5 8-5v2Z" />
              </svg>
              <input id="identifier" name="identifier" type="text" placeholder="Email or Username" required />
            </div>
          </div>

          <div>
            <label for="password">
              Password
            </label>
            <div class="field">
              <svg class="icon" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  d="M17 9h-1V7a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2Zm-7 0V7a2 2 0 1 1 4 0v2h-4Z" />
              </svg>
              <input id="password" name="password" type="password" placeholder="Password" />
            </div>
            <div style="margin-bottom: 15px;">
              <label for="captcha_answer">
                Security Question: <strong>
                  <?php echo $num1 . " + " . $num2; ?> = ?
                </strong>
              </label>
              <div class="field">
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true">
                  <path
                    d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z" />
                </svg>
                <input id="captcha_answer" name="captcha_answer" type="number" placeholder="Enter sum" required />
              </div>
            </div>

           
          </div>
          <button class="btn" type="submit">Sign in</button>
        </form>
        <a href="#" onclick="forgotPassword()">Forgot Password?</a>
      </div>

    </section>
  </main>


</body>
<script>
  function forgotPassword() {
    alert('Please contact your administrator to reset your password.');
  }
</script>

</html>