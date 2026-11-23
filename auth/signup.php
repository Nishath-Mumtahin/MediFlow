<?php
require_once __DIR__ . '/../database.php';

if (isset($_SESSION['user']) && $_SESSION['user'] !== null) {
    redirect('../dashboard/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm_password'] ?? '');

    if ($username === '' || $email === '' || $password === '' || $confirm === '') {
        flash_add('error', 'All fields are required.');
        redirect('signup.php?error=missing');
        exit;
    }

    if ($password !== $confirm) {
        flash_add('error', 'Passwords do not match.');
        redirect('signup.php?error=nomatch');
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash_add('error', 'Please enter a valid email address.');
        redirect('signup.php?error=email');
        exit;
    }

    $existing = find_user_by_username_or_email($username) ?: find_user_by_username_or_email($email);
    if ($existing) {
        flash_add('error', 'A user with that username or email already exists.');
        redirect('signup.php?error=exists');
        exit;
    }

    try {
        $pdo = db();
        $pdo->beginTransaction();

        $patientId = patients_create([
            'first_name' => $username,
            'last_name'  => '',
            'email'      => $email,
        ]);

        create_user($username, $email, $password, ROLE_PATIENT, null, $patientId);
        $pdo->commit();
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash_add('error', 'Failed to register user.');
        redirect('signup.php?error=failed');
        exit;
    }

    flash_add('success', 'Registration successful. Please sign in.');
    redirect('login.php?registered=1');
    exit;
}

include_once __DIR__ . '/../config.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102F5B">
    <title>Create Account | MediFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <main class="grid min-h-screen lg:grid-cols-2">
        <section class="relative hidden overflow-hidden bg-gradient-to-br from-[#102F5B] via-[#174477] to-[#1D5B88] px-10 py-12 text-white lg:flex lg:flex-col lg:justify-between xl:px-16">
            <div class="pointer-events-none absolute -right-28 top-12 h-80 w-80 rounded-full border-[45px] border-white/5"></div>
            <div class="pointer-events-none absolute -bottom-36 -left-20 h-96 w-96 rounded-full bg-cyan-300/10 blur-3xl"></div>
            <a href="../index.php" class="relative inline-flex items-center gap-3">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 text-2xl font-black">✚</span>
                <span class="text-2xl font-extrabold tracking-tight">MediFlow</span>
            </a>
            <div class="relative max-w-xl py-12">
                <p class="text-xs font-bold uppercase tracking-[0.24em] text-blue-200">Patient Portal</p>
                <h1 class="mt-5 text-4xl font-extrabold leading-tight xl:text-5xl">Your healthcare journey, organized in one place.</h1>
                <p class="mt-6 max-w-lg text-base leading-8 text-blue-100">Create your patient account to access your appointments, prescriptions and treatment history through MediFlow.</p>
                <div class="mt-10 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-sm"><p class="text-lg">▦</p><p class="mt-2 font-bold">Appointments</p><p class="mt-1 text-sm text-blue-100">Keep track of recent visits.</p></div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-sm"><p class="text-lg">✚</p><p class="mt-2 font-bold">Health records</p><p class="mt-1 text-sm text-blue-100">Review prescriptions and care history.</p></div>
                </div>
            </div>
            <p class="relative text-xs text-blue-200">© <?= date('Y') ?> MediFlow Hospital Operations Management System</p>
        </section>

        <section class="flex items-center justify-center px-5 py-10 sm:px-8">
            <div class="w-full max-w-md">
                <a href="../index.php" class="mb-8 inline-flex items-center gap-2 text-sm font-bold text-[#102F5B] lg:hidden"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#102F5B] text-white">✚</span> MediFlow</a>
                <div class="mb-7">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-blue-600">Get started</p>
                    <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-[#102F5B]">Create your account</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Register as a patient to access your personal healthcare portal.</p>
                </div>

                <?php render_flash(); ?>

                <form method="post" action="signup.php" class="mt-6 space-y-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-8">
                    <div>
                        <label for="username" class="mb-2 block text-sm font-semibold text-slate-700">Username</label>
                        <input id="username" name="username" type="text" autocomplete="username" required maxlength="100" placeholder="Choose a username" value="<?= htmlspecialchars(trim($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100">
                    </div>
                    <div>
                        <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">Email address</label>
                        <input id="email" name="email" type="email" autocomplete="email" required maxlength="190" placeholder="you@example.com" value="<?= htmlspecialchars(trim($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100">
                    </div>
                    <div>
                        <label for="password" class="mb-2 block text-sm font-semibold text-slate-700">Password</label>
                        <div class="relative">
                            <input id="password" name="password" type="password" autocomplete="new-password" required placeholder="Create a password" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 pr-16 text-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100">
                            <button type="button" data-toggle-password="password" class="absolute inset-y-0 right-3 text-xs font-bold text-blue-700 hover:text-blue-900">Show</button>
                        </div>
                    </div>
                    <div>
                        <label for="confirm_password" class="mb-2 block text-sm font-semibold text-slate-700">Confirm password</label>
                        <div class="relative">
                            <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required placeholder="Enter your password again" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 pr-16 text-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100">
                            <button type="button" data-toggle-password="confirm_password" class="absolute inset-y-0 right-3 text-xs font-bold text-blue-700 hover:text-blue-900">Show</button>
                        </div>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#102F5B] px-4 py-3.5 text-sm font-bold text-white shadow-lg shadow-blue-950/15 transition hover:bg-[#174477] focus:outline-none focus:ring-4 focus:ring-blue-200">Create patient account <span aria-hidden="true">→</span></button>
                    <p class="text-center text-xs leading-5 text-slate-400">By creating an account, you are registering for a patient account on MediFlow.</p>
                </form>

                <p class="mt-6 text-center text-sm text-slate-500">Already have an account? <a href="login.php" class="font-bold text-blue-700 hover:text-blue-900 hover:underline">Sign in</a></p>
                <p class="mt-8 text-center text-xs text-slate-400">MediFlow · Hospital Operations Management System</p>
            </div>
        </section>
    </main>
    <script>
        document.querySelectorAll('[data-toggle-password]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.togglePassword);
                if (!input) return;
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.textContent = show ? 'Hide' : 'Show';
            });
        });
    </script>
</body>
</html>
