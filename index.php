<?php
require_once __DIR__ . '/database.php';

$user = current_user();
$isLoggedIn = is_logged_in();
$isAdmin = has_role(ROLE_ADMIN);
$isPatient = has_role(ROLE_PATIENT);
$isDoctor = has_role(ROLE_DOCTOR);

// Global hospital statistics are shown only to Admin accounts.
$stats = ($isLoggedIn && $isAdmin) ? simple_stats() : [];

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102F5B">
    <title>MediFlow | Hospital Operations Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">

<header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/95 backdrop-blur">
    <div class="mx-auto flex min-h-[76px] max-w-7xl items-center justify-between gap-4 px-5 sm:px-8">
        <a href="./" class="flex shrink-0 items-center gap-3">
            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#102F5B] text-xl font-black text-white shadow-lg shadow-blue-950/15">✚</span>
            <span>
                <span class="block text-xl font-extrabold tracking-tight text-[#102F5B]">MediFlow</span>
                <span class="hidden text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400 sm:block">Hospital Operations</span>
            </span>
        </a>

        <nav class="flex items-center gap-2 sm:gap-3">
            <?php if ($isLoggedIn): ?>
                <span class="hidden text-sm font-semibold text-slate-600 sm:inline">
                    Hi, <?= h($user['username'] ?? ($user['email'] ?? 'User')) ?>
                </span>
                <a href="dashboard/index.php"
                   class="inline-flex items-center rounded-xl bg-[#102F5B] px-3.5 py-2.5 text-sm font-bold text-white transition hover:bg-[#174477] sm:px-4">
                    Dashboard <span class="ml-1.5" aria-hidden="true">→</span>
                </a>
                <form method="post" action="auth/logout.php">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <button type="submit"
                            class="inline-flex items-center rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm font-bold text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700">
                        Logout
                    </button>
                </form>
            <?php else: ?>
                <a href="auth/login.php" class="rounded-xl px-3 py-2.5 text-sm font-bold text-[#102F5B] transition hover:bg-blue-50 sm:px-4">
                    Sign in
                </a>
                <a href="auth/signup.php" class="rounded-xl bg-[#102F5B] px-3.5 py-2.5 text-sm font-bold text-white shadow-md shadow-blue-950/10 transition hover:bg-[#174477] sm:px-4">
                    Create account
                </a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="mx-auto max-w-7xl space-y-8 px-5 py-7 sm:px-8 sm:py-10">
    <?php render_flash(); ?>

    <?php if (!$isLoggedIn): ?>
        <section class="relative isolate overflow-hidden rounded-[2rem] bg-gradient-to-br from-[#102F5B] via-[#174477] to-[#1D5B88] px-6 py-12 text-white shadow-xl shadow-blue-950/10 sm:px-10 lg:px-14 lg:py-16">
            <div class="pointer-events-none absolute -right-24 -top-24 -z-10 h-96 w-96 rounded-full border-[55px] border-white/5"></div>
            <div class="pointer-events-none absolute -bottom-40 right-1/3 -z-10 h-96 w-96 rounded-full bg-cyan-300/10 blur-3xl"></div>
            <div class="grid items-center gap-12 lg:grid-cols-[1.15fr_.85fr]">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3.5 py-1.5 text-[11px] font-bold uppercase tracking-[0.18em] text-blue-100">
                        <span class="h-2 w-2 rounded-full bg-emerald-300"></span>
                        Smart Hospital Management
                    </span>
                    <h1 class="mt-6 max-w-2xl text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl lg:text-[3.5rem]">
                        Better hospital operations, <span class="text-cyan-200">one connected system.</span>
                    </h1>
                    <p class="mt-6 max-w-xl text-sm leading-7 text-blue-100 sm:text-base">
                        Manage patients, doctors, appointments, treatments, prescriptions and hospital resources through one organized platform.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="auth/login.php" class="inline-flex items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-extrabold text-[#102F5B] shadow-lg transition hover:bg-blue-50">
                            Sign in to MediFlow <span class="ml-2" aria-hidden="true">→</span>
                        </a>
                        <a href="auth/signup.php" class="inline-flex items-center justify-center rounded-xl border border-white/25 bg-white/10 px-5 py-3 text-sm font-bold text-white transition hover:bg-white/15">
                            Create patient account
                        </a>
                    </div>
                    <div class="mt-9 flex flex-wrap gap-x-6 gap-y-3 text-xs font-medium text-blue-100">
                        <span class="inline-flex items-center gap-2"><span class="text-emerald-300">✓</span> Role-based access</span>
                        <span class="inline-flex items-center gap-2"><span class="text-emerald-300">✓</span> Organized patient records</span>
                        <span class="inline-flex items-center gap-2"><span class="text-emerald-300">✓</span> Resource monitoring</span>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/15 bg-white/10 p-5 shadow-2xl backdrop-blur-sm sm:p-6">
                    <div class="flex items-center justify-between border-b border-white/15 pb-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-200">Platform overview</p>
                            <h2 class="mt-1 text-lg font-extrabold">Hospital modules</h2>
                        </div>
                        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 text-2xl">✚</span>
                    </div>
                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <?php
                        $modules = [
                            ['Patients', 'Patient profiles and records', '01'],
                            ['Appointments', 'Appointment scheduling', '02'],
                            ['Treatments', 'Doctor treatment records', '03'],
                            ['Prescriptions', 'Medicine and dosage details', '04'],
                            ['Doctors', 'Doctor and department data', '05'],
                            ['Rooms & Admissions', 'Hospital room allocation', '06'],
                        ];
                        foreach ($modules as [$title, $description, $number]):
                        ?>
                            <div class="rounded-2xl border border-white/10 bg-[#0B2A50]/35 p-4">
                                <p class="text-[10px] font-extrabold tracking-[0.18em] text-cyan-200"><?= h($number) ?></p>
                                <h3 class="mt-2 text-sm font-bold text-white"><?= h($title) ?></h3>
                                <p class="mt-1 text-xs leading-5 text-blue-100/80"><?= h($description) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="mt-5 text-center text-[11px] text-blue-100/70">A centralized workspace for hospital operations</p>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-3">
            <article class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-lg text-blue-700">▦</span>
                <h3 class="mt-4 font-extrabold text-[#102F5B]">Appointment Management</h3>
                <p class="mt-2 text-sm leading-6 text-slate-500">Keep appointment information organized and accessible to authorized users.</p>
            </article>
            <article class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-lg text-emerald-700">✚</span>
                <h3 class="mt-4 font-extrabold text-[#102F5B]">Patient Care Records</h3>
                <p class="mt-2 text-sm leading-6 text-slate-500">Maintain treatment and prescription records in a connected system.</p>
            </article>
            <article class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-lg text-violet-700">⌂</span>
                <h3 class="mt-4 font-extrabold text-[#102F5B]">Resource Coordination</h3>
                <p class="mt-2 text-sm leading-6 text-slate-500">Support room, admission, medicine and department management.</p>
            </article>
        </section>

    <?php else: ?>
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#102F5B] to-[#1D5B88] p-6 text-white shadow-lg shadow-blue-950/10 sm:p-8">
            <div class="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full border-[38px] border-white/5"></div>
            <div class="relative flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-200">MediFlow workspace</p>
                    <h1 class="mt-2 text-2xl font-extrabold tracking-tight sm:text-3xl">
                        Welcome back, <?= h($user['username'] ?? 'User') ?>.
                    </h1>
                    <p class="mt-2 text-sm text-blue-100">
                        <?php if ($isAdmin): ?>
                            Review hospital operations and manage the system from your dashboard.
                        <?php elseif ($isDoctor): ?>
                            Access your appointments, treatments, prescriptions and assigned patients.
                        <?php elseif ($isPatient): ?>
                            View your appointments, prescriptions and personal medical history.
                        <?php else: ?>
                            Open your MediFlow workspace.
                        <?php endif; ?>
                    </p>
                </div>
                <a href="dashboard/index.php" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-extrabold text-[#102F5B] transition hover:bg-blue-50">
                    Open Dashboard <span class="ml-2" aria-hidden="true">→</span>
                </a>
            </div>
        </section>

        <?php if ($isAdmin): ?>
            <section>
                <div class="mb-4">
                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-blue-600">System overview</p>
                    <h2 class="mt-1 text-xl font-extrabold text-[#102F5B]">Hospital Statistics</h2>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <?php
                    $statCards = [
                        ['Patients', 'patients', 'bg-blue-50 text-blue-700', '01'],
                        ['Doctors', 'doctors', 'bg-emerald-50 text-emerald-700', '02'],
                        ['Appointments', 'appointments', 'bg-violet-50 text-violet-700', '03'],
                        ['Treatments', 'treatments', 'bg-amber-50 text-amber-700', '04'],
                        ['Rooms', 'rooms', 'bg-cyan-50 text-cyan-700', '05'],
                        ['Admissions', 'admissions', 'bg-rose-50 text-rose-700', '06'],
                    ];
                    foreach ($statCards as [$label, $key, $color, $number]):
                    ?>
                        <article class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                            <div>
                                <p class="text-sm font-semibold text-slate-500"><?= h($label) ?></p>
                                <p class="mt-2 text-3xl font-extrabold tracking-tight text-[#102F5B]"><?= (int)($stats[$key] ?? 0) ?></p>
                            </div>
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl <?= h($color) ?> text-xs font-extrabold"><?= h($number) ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php else: ?>
            <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6">
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-blue-600">Your workspace</p>
                <h2 class="mt-1 text-lg font-extrabold text-[#102F5B]">
                    <?= $isDoctor ? 'Doctor workspace' : ($isPatient ? 'Patient portal' : 'Account dashboard') ?>
                </h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                    <?= $isDoctor
                        ? 'Your dashboard provides access to your assigned appointments, treatment records, prescriptions and patient information.'
                        : ($isPatient
                            ? 'Your patient dashboard provides access to your recent appointments, prescriptions, treatment history and profile information.'
                            : 'Use your dashboard to access the features available to your account.') ?>
                </p>
            </section>
        <?php endif; ?>

        <section>
            <div class="mb-4">
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-blue-600">Navigation</p>
                <h2 class="mt-1 text-xl font-extrabold text-[#102F5B]">Quick Actions</h2>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <?php if ($isAdmin || $isDoctor): ?>
                    <a href="dashboard/manage-appointments.php" class="group rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-700">▦</span>
                        <h3 class="mt-4 font-extrabold text-[#102F5B]">Manage Appointments</h3>
                        <p class="mt-1 text-xs text-slate-500">View and manage appointment records.</p>
                    </a>
                    <a href="dashboard/manage-treatments.php" class="group rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-violet-200 hover:shadow-md">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-700">▤</span>
                        <h3 class="mt-4 font-extrabold text-[#102F5B]">Manage Treatments</h3>
                        <p class="mt-1 text-xs text-slate-500">Access treatment records.</p>
                    </a>
                    <a href="dashboard/manage-prescriptions.php" class="group rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">✚</span>
                        <h3 class="mt-4 font-extrabold text-[#102F5B]">Manage Prescriptions</h3>
                        <p class="mt-1 text-xs text-slate-500">Review prescription records.</p>
                    </a>
                <?php endif; ?>

                <?php if ($isAdmin): ?>
                    <a href="dashboard/manage-patients.php" class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-700">♙</span>
                        <h3 class="mt-4 font-extrabold text-[#102F5B]">Manage Patients</h3>
                        <p class="mt-1 text-xs text-slate-500">Maintain patient profiles.</p>
                    </a>
                    <a href="dashboard/manage-rooms.php" class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-cyan-200 hover:shadow-md">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-cyan-50 text-cyan-700">⌂</span>
                        <h3 class="mt-4 font-extrabold text-[#102F5B]">Rooms & Admissions</h3>
                        <p class="mt-1 text-xs text-slate-500">Manage rooms and admissions.</p>
                    </a>
                    <a href="dashboard/manage-doctors.php" class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">♙</span>
                        <h3 class="mt-4 font-extrabold text-[#102F5B]">Manage Doctors</h3>
                        <p class="mt-1 text-xs text-slate-500">Manage doctor information.</p>
                    </a>
                    <a href="dashboard/manage-departments.php" class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-amber-200 hover:shadow-md">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-700">▦</span>
                        <h3 class="mt-4 font-extrabold text-[#102F5B]">Departments</h3>
                        <p class="mt-1 text-xs text-slate-500">Maintain hospital departments.</p>
                    </a>
                    <a href="dashboard/manage-medications.php" class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-rose-200 hover:shadow-md">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-700">✚</span>
                        <h3 class="mt-4 font-extrabold text-[#102F5B]">Medications</h3>
                        <p class="mt-1 text-xs text-slate-500">Manage medicine inventory records.</p>
                    </a>
                    <a href="dashboard/manage-users.php" class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-700">♧</span>
                        <h3 class="mt-4 font-extrabold text-[#102F5B]">User Accounts</h3>
                        <p class="mt-1 text-xs text-slate-500">Manage system user accounts.</p>
                    </a>
                <?php elseif ($isPatient): ?>
                    <a href="dashboard/patient-dashboard.php" class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-700">♡</span>
                        <h3 class="mt-4 font-extrabold text-[#102F5B]">My Patient Dashboard</h3>
                        <p class="mt-1 text-xs text-slate-500">View your personal healthcare information.</p>
                    </a>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</main>

<footer class="mt-8 border-t border-slate-200 bg-white">
    <div class="mx-auto flex max-w-7xl flex-col gap-2 px-5 py-6 text-center text-xs text-slate-400 sm:px-8">
        <p class="font-bold text-[#102F5B]">MediFlow</p>
        <p>© <?= date('Y') ?> MediFlow Hospital Operations Management System</p>
    </div>
</footer>

</body>
</html>
