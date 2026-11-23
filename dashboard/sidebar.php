<?php
$user = current_user();
$role = $user['role'] ?? ROLE_PATIENT;
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');

$dashboardLink = $role === ROLE_PATIENT
    ? 'patient-dashboard.php'
    : 'index.php';

$menuBase = 'flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-all duration-200';
$activeCls = 'bg-blue-600 text-white shadow-lg shadow-blue-950/30';
$inactiveCls = 'text-slate-300 hover:bg-white/10 hover:text-white';

function sidebarLink($file, $label, $icon, $current, $menuBase, $activeCls, $inactiveCls)
{
    $isActive = $current === $file;

    return '
        <a href="' . htmlspecialchars($file) . '"
           class="' . $menuBase . ' ' . ($isActive ? $activeCls : $inactiveCls) . '">
            <span class="text-lg w-6 text-center">' . $icon . '</span>
            <span>' . htmlspecialchars($label) . '</span>
            ' . ($isActive ? '<span class="ml-auto text-xs">●</span>' : '') . '
        </a>
    ';
}
?>

<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<style>
    [x-cloak] {
        display: none !important;
    }
</style>

<div x-data="{ open: false }">

    <!-- Mobile Topbar -->
    <header class="fixed inset-x-0 top-0 z-40 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 shadow-sm md:hidden">

        <button @click="open = !open"
            class="rounded-lg border border-slate-200 p-2 text-slate-700 hover:bg-slate-100"
            aria-label="Toggle menu">
            ☰
        </button>

        <div class="flex items-center gap-2">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-xl font-bold text-white">
                M
            </span>
            <span class="font-extrabold text-blue-950">MediFlow</span>
        </div>

        <span class="text-xs font-semibold capitalize text-slate-500">
            <?= htmlspecialchars($role) ?>
        </span>

    </header>

    <!-- Mobile Header Space -->
    <div class="h-16 md:hidden"></div>

    <!-- Mobile Overlay -->
    <div x-show="open"
        x-cloak
        x-transition.opacity
        @click="open = false"
        class="fixed inset-0 z-40 bg-slate-950/60 md:hidden">
    </div>

    <!-- Sidebar -->
    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-[#102F5B] text-white shadow-2xl transition-transform duration-300"
        :class="open ? 'translate-x-0' : '-translate-x-full md:translate-x-0'">

        <!-- Brand -->
        <div class="border-b border-white/10 px-5 py-6">

            <a href="<?= htmlspecialchars($dashboardLink) ?>"
                class="flex items-center gap-3">

                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-500 text-3xl font-bold shadow-lg">
                    +
                </span>

                <span>
                    <strong class="block text-xl font-extrabold tracking-wide">
                        MediFlow
                    </strong>

                    <small class="text-xs text-blue-200">
                        Hospital Management
                    </small>
                </span>

            </a>

        </div>

        <!-- User Profile -->
        <div class="mx-4 mt-5 rounded-xl border border-white/10 bg-white/10 p-4">

            <p class="text-[10px] font-bold uppercase tracking-widest text-blue-200">
                Signed in as
            </p>

            <p class="mt-2 truncate font-semibold text-white">
                <?= htmlspecialchars($user['username'] ?? 'User') ?>
            </p>

            <div class="mt-2 flex items-center gap-2">

                <span class="h-2 w-2 rounded-full bg-green-400"></span>

                <span class="text-xs capitalize text-blue-200">
                    <?= htmlspecialchars($role) ?>
                </span>

            </div>

        </div>

        <!-- Navigation -->
        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-6">

            <p class="mb-3 px-4 text-[10px] font-bold uppercase tracking-[2px] text-blue-300">
                Main Menu
            </p>

            <!-- Dashboard -->
            <?= sidebarLink(
                $dashboardLink,
                'Dashboard',
                '▦',
                $current,
                $menuBase,
                $activeCls,
                $inactiveCls
            ) ?>

            <?php if ($role === ROLE_ADMIN): ?>

                <p class="mb-3 mt-7 px-4 text-[10px] font-bold uppercase tracking-[2px] text-blue-300">
                    Management
                </p>

                <?= sidebarLink('manage-patients.php', 'Patients', '👥', $current, $menuBase, $activeCls, $inactiveCls) ?>

                <?= sidebarLink('manage-doctors.php', 'Doctors', '👨‍⚕️', $current, $menuBase, $activeCls, $inactiveCls) ?>

                <?= sidebarLink('manage-appointments.php', 'Appointments', '📅', $current, $menuBase, $activeCls, $inactiveCls) ?>

                <?= sidebarLink('manage-treatments.php', 'Treatments', '🩺', $current, $menuBase, $activeCls, $inactiveCls) ?>

                <?= sidebarLink('manage-prescriptions.php', 'Prescriptions', '💊', $current, $menuBase, $activeCls, $inactiveCls) ?>

                <?= sidebarLink('manage-medications.php', 'Medications', '💉', $current, $menuBase, $activeCls, $inactiveCls) ?>

                <?= sidebarLink('manage-departments.php', 'Departments', '🏢', $current, $menuBase, $activeCls, $inactiveCls) ?>

                <?= sidebarLink('manage-rooms.php', 'Rooms & Admissions', '🛏️', $current, $menuBase, $activeCls, $inactiveCls) ?>

                <?= sidebarLink('manage-users.php', 'Users', '👤', $current, $menuBase, $activeCls, $inactiveCls) ?>

            <?php elseif ($role === ROLE_DOCTOR): ?>

                <p class="mb-3 mt-7 px-4 text-[10px] font-bold uppercase tracking-[2px] text-blue-300">
                    Doctor Menu
                </p>

                <?= sidebarLink('manage-appointments.php', 'Appointments', '📅', $current, $menuBase, $activeCls, $inactiveCls) ?>

                <?= sidebarLink('manage-treatments.php', 'Treatments', '🩺', $current, $menuBase, $activeCls, $inactiveCls) ?>

                <?= sidebarLink('manage-prescriptions.php', 'Prescriptions', '💊', $current, $menuBase, $activeCls, $inactiveCls) ?>

                <?= sidebarLink('manage-patients.php', 'Patients', '👥', $current, $menuBase, $activeCls, $inactiveCls) ?>

            <?php elseif ($role === ROLE_PATIENT): ?>

                <p class="mb-3 mt-7 px-4 text-[10px] font-bold uppercase tracking-[2px] text-blue-300">
                    Patient Menu
                </p>

            <?php endif; ?>

        </nav>

        <!-- Bottom Section -->
        <div class="border-t border-white/10 p-4">

            <div class="mb-4 rounded-xl bg-white/5 p-3">

                <p class="text-xs font-semibold text-white">
                    MediFlow Hospital
                </p>

                <p class="mt-1 text-[10px] text-blue-200">
                    Smarter Healthcare Management
                </p>

            </div>

            <!-- Secure Logout -->
            <form method="post" action="../auth/logout.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(csrf_token()) ?>">

                <button
                    type="submit"
                    class="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold text-red-300 transition hover:bg-red-500/20 hover:text-white">

                    <span class="text-lg">⇥</span>

                    <span>Logout</span>

                </button>

            </form>

        </div>

    </aside>

</div>