<?php
require_once __DIR__ . '/../database.php';

if (isset($_SESSION['user']) && $_SESSION['user'] !== null) {
    redirect('../dashboard/index.php');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $identifier = trim($_POST['identifier'] ?? '');
    $password   = (string)($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        flash_add('error', 'Please provide username/email and password.');
        redirect('login.php?error=missing');
        exit;
    }

    try {
        $user = find_user_by_username_or_email($identifier);
    } catch (Throwable $e) {
        flash_add('error', 'Database connection failed. Make sure MySQL is running and schema.sql is imported.');
        redirect('login.php?error=db');
        exit;
    }
    if (!$user || !password_verify($password, $user['password_hash'])) {
        flash_add('error', 'Invalid credentials.');
        redirect('login.php?error=invalid');
        exit;
    }

    $allowedRoles = [
        ROLE_ADMIN,
        ROLE_DOCTOR,
        ROLE_PATIENT
    ];

    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role'],
        'linked_doctor_id' => $user['linked_doctor_id'] !== null ? (int)$user['linked_doctor_id'] : null,
        'linked_patient_id' => $user['linked_patient_id'] !== null ? (int)$user['linked_patient_id'] : null,
    ];

    if ($user['role'] === ROLE_PATIENT) {
        redirect('../dashboard/patient-dashboard.php');
    }
    redirect('../dashboard/index.php');
    exit;
} 

else {
        include_once __DIR__ . '/../config.php'; 
?>
    <!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediFlow | Login</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
        }

        .login-bg {
            background: linear-gradient(135deg, #0f2b5b, #1765b5, #2588d8);
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.10);
            border: 1px solid rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
        }
    </style>
</head>

<body class="min-h-screen bg-slate-100">

    <main class="min-h-screen flex items-center justify-center p-4 lg:p-8">

        <div class="w-full max-w-6xl bg-white rounded-3xl shadow-2xl overflow-hidden grid grid-cols-1 lg:grid-cols-2">

            <!-- LEFT SIDE -->
            <section class="login-bg text-white p-8 md:p-12 lg:p-14 flex flex-col justify-between">

                <div>
                    <!-- Logo -->
                    <div class="flex items-center gap-3 mb-14">
                        <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center text-3xl">
    🏥
</div>

                        <div>
                            <h1 class="text-2xl font-bold tracking-wide">MediFlow</h1>
                            <p class="text-blue-100 text-xs">Hospital Management System</p>
                        </div>
                    </div>

                    <!-- Heading -->
                    <div class="mb-10">
                        <p class="text-blue-200 uppercase tracking-widest text-sm font-semibold mb-3">
                            Welcome to MediFlow
                        </p>

                        <h2 class="text-4xl md:text-5xl font-bold leading-tight mb-5">
                            Smarter Decisions for a Healthier Tomorrow.
                        </h2>

                        <p class="text-blue-100 leading-relaxed max-w-md">
                            Manage hospital operations, coordinate medical resources
                            and improve patient care through one intelligent platform.
                        </p>
                    </div>

                    <!-- Features -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                        <div class="feature-card rounded-xl p-4">
                            <div class="text-2xl mb-2">🏥</div>
                            <h3 class="font-semibold mb-1">Bed & Ward Allocation</h3>
                            <p class="text-sm text-blue-100">Manage hospital beds efficiently.</p>
                        </div>

                        <div class="feature-card rounded-xl p-4">
                            <div class="text-2xl mb-2">⚕️</div>
                            <h3 class="font-semibold mb-1">Patient Priority</h3>
                            <p class="text-sm text-blue-100">Support smarter patient management.</p>
                        </div>

                        <div class="feature-card rounded-xl p-4">
                            <div class="text-2xl mb-2">👨‍⚕️</div>
                            <h3 class="font-semibold mb-1">Doctor Recommendation</h3>
                            <p class="text-sm text-blue-100">Find suitable medical specialists.</p>
                        </div>

                        <div class="feature-card rounded-xl p-4">
                            <div class="text-2xl mb-2">💊</div>
                            <h3 class="font-semibold mb-1">Medicine Stock Alert</h3>
                            <p class="text-sm text-blue-100">Monitor medicine availability.</p>
                        </div>

                    </div>
                </div>

                <p class="text-blue-200 text-xs mt-12">
                    © <?= date('Y') ?> MediFlow. All rights reserved.
                </p>

            </section>


            <!-- RIGHT SIDE -->
            <section class="p-8 md:p-12 lg:p-16 flex flex-col justify-center">

                <div class="max-w-md w-full mx-auto">

                    <div class="mb-8">
                        <div class="w-14 h-14 bg-blue-100 text-blue-700 rounded-2xl flex items-center justify-center text-2xl mb-5">
                            🔐
                        </div>

                        <h2 class="text-3xl font-bold text-slate-800 mb-2">
                            Welcome Back!
                        </h2>

                        <p class="text-slate-500">
                            Sign in to access your MediFlow account.
                        </p>
                    </div>

                    <?php render_flash(); ?>

                    <form method="post" action="login.php" class="space-y-5">

                        <!-- Username / Email -->
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">
                                Username or Email
                            </label>

                            <input
                                type="text"
                                name="identifier"
                                placeholder="Enter your username or email"
                                required
                                autocomplete="username"
                                class="w-full px-4 py-3 rounded-xl border border-slate-300
                                focus:outline-none focus:ring-2 focus:ring-blue-500
                                focus:border-blue-500 transition">
                        </div>

                        <!-- Password -->
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">
                                Password
                            </label>

                            <input
                                type="password"
                                name="password"
                                placeholder="Enter your password"
                                required
                                autocomplete="current-password"
                                class="w-full px-4 py-3 rounded-xl border border-slate-300
                                focus:outline-none focus:ring-2 focus:ring-blue-500
                                focus:border-blue-500 transition">
                        </div>

                        <!-- CSRF Token -->
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(csrf_token()) ?>">

                        <!-- Login Button -->
                        <button
                            type="submit"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white
                            font-semibold py-3 rounded-xl shadow-lg
                            shadow-blue-200 transition duration-200">
                            Sign In to MediFlow
                        </button>

                    </form>

                    <!-- Signup -->
                    <div class="text-center mt-7">
                        <p class="text-sm text-slate-500">
                            Don't have an account?

                            <a href="signup.php"
                                class="text-blue-600 font-semibold hover:underline">
                                Create Account
                            </a>
                        </p>
                    </div>

                    <!-- Home -->
                    <div class="text-center mt-5">
                        <a href="../"
                            class="text-sm text-slate-500 hover:text-blue-600 transition">
                            ← Back to Home
                        </a>
                    </div>

                    <!-- Security Notice -->
                    <div class="mt-10 bg-blue-50 border border-blue-100 rounded-xl p-4 text-center">
                        <p class="text-xs text-blue-800 font-medium">
                            🔒 Authorized Personnel Only
                        </p>

                        <p class="text-xs text-slate-500 mt-1">
                            Your account information is protected.
                        </p>
                    </div>

                </div>

            </section>

        </div>

    </main>

</body>

</html>

<?php
    exit;
}