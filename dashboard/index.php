<?php
require_once __DIR__ . '/../database.php';
require_login();
$user = current_user();
if (($user['role'] ?? null) === ROLE_PATIENT) {
    redirect('patient-dashboard.php');
}
$isAdmin = ($user['role'] === ROLE_ADMIN);
$isPatient = ($user['role'] === ROLE_PATIENT);
$patientId = $isPatient ? (int)($user['linked_patient_id'] ?? 0) : 0;
$isDoctor = ($user['role'] === ROLE_DOCTOR);
$doctorId = $isDoctor ? (int)($user['linked_doctor_id'] ?? 0) : 0;
$doctorUnlinked = $isDoctor && !$doctorId;

$isPatient = ($user['role'] === ROLE_PATIENT);

$patientId = $isPatient
    ? (int)($user['linked_patient_id'] ?? 0)
    : 0;

$patientUnlinked = $isPatient && !$patientId;

$patientAppointments = [];
$patientTreatments = [];
$patientAdmissions = [];

if ($isPatient && $patientId) {

    // Patient's appointments
    $stmt = db()->prepare("
        SELECT a.*, d.first_name AS doctor_first,
               d.last_name AS doctor_last
        FROM appointments a
        JOIN doctors d ON d.id = a.doctor_id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC
        LIMIT 10
    ");
    $stmt->execute([$patientId]);
    $patientAppointments = $stmt->fetchAll();

    // Patient's treatment history
    $stmt = db()->prepare("
        SELECT t.*, d.first_name AS doctor_first,
               d.last_name AS doctor_last
        FROM treatments t
        LEFT JOIN doctors d ON d.id = t.doctor_id
        WHERE t.patient_id = ?
        ORDER BY t.treatment_date DESC
        LIMIT 10
    ");
    $stmt->execute([$patientId]);
    $patientTreatments = $stmt->fetchAll();

    // Patient's admission information
    $stmt = db()->prepare("
        SELECT a.*, r.room_number, r.type AS room_type
        FROM admissions a
        JOIN rooms r ON r.id = a.room_id
        WHERE a.patient_id = ?
        ORDER BY a.admitted_on DESC
        LIMIT 5
    ");
    $stmt->execute([$patientId]);
    $patientAdmissions = $stmt->fetchAll();
}

$stats = ($isAdmin || $isDoctor) ? simple_stats() : [];
$todayApptCounts = dashboard_appointments_today_status_counts($isDoctor && $doctorId ? $doctorId : null);
$todayAppts = dashboard_appointments_today($isDoctor && $doctorId ? $doctorId : null, 10);
$upcoming7 = dashboard_appointments_upcoming_count($isDoctor && $doctorId ? $doctorId : null, 7);
$roomOcc = $isAdmin ? rooms_occupancy_counts() : null;
$openAdmissions = $isAdmin ? admissions_open_list(8) : [];
$docTreatments = $isDoctor && $doctorId ? doctor_recent_treatments($doctorId, 8) : [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-slate-50 text-slate-800">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="md:ml-64 min-h-screen p-4 sm:p-6 lg:p-8">
        <?php if ($doctorUnlinked): ?>
            <div class="mb-4 border border-amber-200 bg-amber-50 text-amber-900 px-3 py-2 rounded">Your account isn’t linked to a doctor profile. Please contact an administrator.</div>
        <?php endif; ?>

        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5 mb-8">

    <div>
        <p class="text-sm font-semibold text-blue-600 mb-1">
            MEDIFLOW HOSPITAL MANAGEMENT
        </p>

        <h1 class="text-3xl font-bold text-slate-800 tracking-tight">
            Dashboard Overview
        </h1>

        <p class="text-sm text-slate-500 mt-2">
            Monitor hospital activities and manage daily operations.
        </p>
    </div>

    <?php if ($isAdmin): ?>
        <div class="flex flex-wrap gap-2">

            <a href="manage-appointments.php"
               class="inline-flex items-center gap-2 rounded-xl bg-blue-600 text-white px-4 py-3 text-sm font-semibold shadow-sm hover:bg-blue-700 transition">
                + New Appointment
            </a>

            <a href="manage-patients.php"
               class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 text-white px-4 py-3 text-sm font-semibold shadow-sm hover:bg-emerald-700 transition">
                + New Patient
            </a>

            <a href="manage-doctors.php"
               class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 text-white px-4 py-3 text-sm font-semibold shadow-sm hover:bg-indigo-700 transition">
                + New Doctor
            </a>

        </div>
    <?php endif; ?>

</div>
        <?php if (!$isPatient): ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">

        <!-- Total Patients -->
        <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Total Patients</p>
                    <h3 class="text-3xl font-bold text-slate-800 mt-2">
                        <?= (int)$stats['patients'] ?>
                    </h3>
                    <p class="text-xs text-slate-400 mt-2">Registered patients</p>
                </div>

                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-2xl">
                    ♙
                </div>
            </div>
        </div>

        <!-- Total Doctors -->
        <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Total Doctors</p>
                    <h3 class="text-3xl font-bold text-slate-800 mt-2">
                        <?= (int)$stats['doctors'] ?>
                    </h3>
                    <p class="text-xs text-slate-400 mt-2">Available medical staff</p>
                </div>

                <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl">
                    ⚕
                </div>
            </div>
        </div>

        <!-- Today's Appointments -->
        <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Today's Appointments</p>

                    <h3 class="text-3xl font-bold text-slate-800 mt-2">
                        <?= (int)$todayApptCounts['scheduled'] +
                            (int)$todayApptCounts['completed'] +
                            (int)$todayApptCounts['cancelled'] ?>
                    </h3>

                    <p class="text-xs text-slate-400 mt-2">Scheduled for today</p>
                </div>

                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl">
                    ▦
                </div>
            </div>

            <div class="flex flex-wrap gap-2 mt-4 text-xs">
                <span class="text-blue-700 bg-blue-50 px-2 py-1 rounded-lg">
                    Scheduled: <b id="count-scheduled"><?= (int)$todayApptCounts['scheduled'] ?></b>
                </span>

                <span class="text-emerald-700 bg-emerald-50 px-2 py-1 rounded-lg">
                    Completed: <b id="count-completed"><?= (int)$todayApptCounts['completed'] ?></b>
                </span>

                <span class="text-red-700 bg-red-50 px-2 py-1 rounded-lg">
                    Cancelled: <b id="count-cancelled"><?= (int)$todayApptCounts['cancelled'] ?></b>
                </span>
            </div>
        </div>

        <!-- Upcoming Appointments -->
        <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Upcoming 7 Days</p>
                    <h3 class="text-3xl font-bold text-slate-800 mt-2">
                        <?= (int)$upcoming7 ?>
                    </h3>
                    <p class="text-xs text-slate-400 mt-2">Upcoming appointments</p>
                </div>

                <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl">
                    ◷
                </div>
            </div>
        </div>

        <!-- Room Statistics -->
        <?php if ($roomOcc): ?>
            <div class="sm:col-span-2 xl:col-span-4 bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">

                <h3 class="font-semibold text-slate-800 mb-4">
                    Hospital Room Overview
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                    <div class="rounded-xl bg-emerald-50 p-4">
                        <p class="text-sm text-emerald-700">Available Rooms</p>
                        <h3 class="text-2xl font-bold text-emerald-800 mt-1">
                            <?= (int)$roomOcc['available'] ?>
                        </h3>
                    </div>

                    <div class="rounded-xl bg-amber-50 p-4">
                        <p class="text-sm text-amber-700">Occupied Rooms</p>
                        <h3 class="text-2xl font-bold text-amber-800 mt-1">
                            <?= (int)$roomOcc['occupied'] ?>
                        </h3>
                    </div>

                    <div class="rounded-xl bg-slate-100 p-4">
                        <p class="text-sm text-slate-600">Maintenance</p>
                        <h3 class="text-2xl font-bold text-slate-800 mt-1">
                            <?= (int)$roomOcc['maintenance'] ?>
                        </h3>
                    </div>

                </div>
            </div>
        <?php endif; ?>

    </div>

<?php endif; ?>

            <?php if ($isPatient && $patientId): ?>

                <section class="mb-6 rounded border bg-white">

                    <div class="px-4 py-3 border-b">
                        <h3 class="font-semibold">My Appointments</h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">

                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="text-left px-4 py-2">Date</th>
                                    <th class="text-left px-4 py-2">Doctor</th>
                                    <th class="text-left px-4 py-2">Status</th>
                                    <th class="text-left px-4 py-2">Notes</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($patientAppointments as $a): ?>
                                    <tr class="border-t">
                                        <td class="px-4 py-2">
                                            <?= htmlspecialchars($a['appointment_date']) ?>
                                        </td>

                                        <td class="px-4 py-2">
                                            <?= htmlspecialchars($a['doctor_first'] . ' ' . $a['doctor_last']) ?>
                                        </td>

                                        <td class="px-4 py-2">
                                            <?= htmlspecialchars(ucfirst($a['status'])) ?>
                                        </td>

                                        <td class="px-4 py-2">
                                            <?= htmlspecialchars($a['notes'] ?? '') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (!$patientAppointments): ?>
                                    <tr>
                                        <td colspan="4" class="px-4 py-3">
                                            No appointments found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>

                        </table>
                    </div>

                </section>

            <?php endif; ?>

            <?php if ($isPatient && $patientId): ?>

                <section class="mb-6 rounded border bg-white">

                    <div class="px-4 py-3 border-b">
                        <h3 class="font-semibold">My Treatment History</h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">

                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="text-left px-4 py-2">Date</th>
                                    <th class="text-left px-4 py-2">Doctor</th>
                                    <th class="text-left px-4 py-2">Diagnosis</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($patientTreatments as $t): ?>
                                    <tr class="border-t">
                                        <td class="px-4 py-2">
                                            <?= htmlspecialchars($t['treatment_date']) ?>
                                        </td>

                                        <td class="px-4 py-2">
                                            <?= htmlspecialchars(trim(($t['doctor_first'] ?? '') . ' ' . ($t['doctor_last'] ?? ''))) ?>
                                        </td>

                                        <td class="px-4 py-2">
                                            <?= htmlspecialchars($t['diagnosis'] ?? '') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (!$patientTreatments): ?>
                                    <tr>
                                        <td colspan="3" class="px-4 py-3">
                                            No treatment history found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>

                        </table>
                    </div>

                </section>

            <?php endif; ?>

            <?php if ($isPatient && $patientId): ?>

                <section class="mb-6 rounded border bg-white">

                    <div class="px-4 py-3 border-b">
                        <h3 class="font-semibold">My Admissions</h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">

                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="text-left px-4 py-2">Room</th>
                                    <th class="text-left px-4 py-2">Admitted On</th>
                                    <th class="text-left px-4 py-2">Discharged On</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($patientAdmissions as $a): ?>
                                    <tr class="border-t">
                                        <td class="px-4 py-2">
                                            <?= htmlspecialchars($a['room_number']) ?>
                                        </td>

                                        <td class="px-4 py-2">
                                            <?= htmlspecialchars($a['admitted_on']) ?>
                                        </td>

                                        <td class="px-4 py-2">
                                            <?= htmlspecialchars($a['discharged_on'] ?? 'Currently Admitted') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (!$patientAdmissions): ?>
                                    <tr>
                                        <td colspan="3" class="px-4 py-3">
                                            No admission records found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>

                        </table>
                    </div>

                </section>

            <?php endif; ?>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">

    <!-- Today's Appointments -->
    <?php if (!$isPatient): ?>
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <div>
                    <h3 class="text-base font-bold text-slate-800">
                        Today's Appointments
                    </h3>
                    <p class="text-xs text-slate-400 mt-1">
                        Manage today's patient appointments
                    </p>
                </div>

                <a href="manage-appointments.php"
                   class="text-sm font-semibold text-blue-600 hover:text-blue-800">
                    View all →
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">

                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="text-left px-5 py-3 font-semibold">Time</th>
                            <th class="text-left px-5 py-3 font-semibold">Patient</th>

                            <?php if (!$isDoctor): ?>
                                <th class="text-left px-5 py-3 font-semibold">Doctor</th>
                            <?php endif; ?>

                            <th class="text-left px-5 py-3 font-semibold">Status</th>
                            <th class="text-right px-5 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">

                        <?php foreach ($todayAppts as $a): ?>

                            <?php
                            $st = $a['status'];

                            $cls = $st === 'completed'
                                ? 'border-emerald-200 text-emerald-700 bg-emerald-50'
                                : ($st === 'cancelled'
                                    ? 'border-red-200 text-red-700 bg-red-50'
                                    : 'border-blue-200 text-blue-700 bg-blue-50');
                            ?>

                            <tr class="hover:bg-slate-50 transition"
                                data-appointment-row="<?= (int)$a['id'] ?>">

                                <td class="px-5 py-4 font-medium text-slate-700 whitespace-nowrap">
                                    <?= htmlspecialchars(substr($a['appointment_date'], 11, 5)) ?>
                                </td>

                                <td class="px-5 py-4 font-medium text-slate-800 whitespace-nowrap">
                                    <?= htmlspecialchars($a['patient_first'] . ' ' . $a['patient_last']) ?>
                                </td>

                                <?php if (!$isDoctor): ?>
                                    <td class="px-5 py-4 text-slate-600 whitespace-nowrap">
                                        <?= htmlspecialchars($a['doctor_first'] . ' ' . $a['doctor_last']) ?>
                                    </td>
                                <?php endif; ?>

                                <td class="px-5 py-4 whitespace-nowrap">
                                    <span
                                        data-role="status-badge"
                                        data-status="<?= htmlspecialchars($st) ?>"
                                        class="inline-flex items-center px-3 py-1 rounded-full border text-xs font-semibold <?= $cls ?>">
                                        <?= htmlspecialchars(ucfirst($st)) ?>
                                    </span>
                                </td>

                                <td class="px-5 py-4 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">

                                        <?php $aid = (int)$a['id']; ?>

                                        <?php if ($isDoctor): ?>

                                            <button
                                                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition"
                                                data-action="status"
                                                data-id="<?= $aid ?>"
                                                data-status="completed">
                                                Complete
                                            </button>

                                            <button
                                                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-50 text-red-700 hover:bg-red-100 transition"
                                                data-action="status"
                                                data-id="<?= $aid ?>"
                                                data-status="cancelled">
                                                Cancel
                                            </button>

                                        <?php elseif ($isAdmin): ?>

                                            <button
                                                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition"
                                                data-action="status"
                                                data-id="<?= $aid ?>"
                                                data-status="completed">
                                                Complete
                                            </button>

                                            <button
                                                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 transition"
                                                data-action="status"
                                                data-id="<?= $aid ?>"
                                                data-status="scheduled">
                                                Schedule
                                            </button>

                                            <button
                                                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-50 text-red-700 hover:bg-red-100 transition"
                                                data-action="status"
                                                data-id="<?= $aid ?>"
                                                data-status="cancelled">
                                                Cancel
                                            </button>

                                        <?php endif; ?>

                                    </div>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <?php if (!$todayAppts): ?>
                            <tr>
                                <td colspan="<?= $isDoctor ? 4 : 5 ?>"
                                    class="px-5 py-10 text-center">

                                    <p class="text-slate-400 text-sm">
                                        No appointments today.
                                    </p>

                                </td>
                            </tr>
                        <?php endif; ?>

                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>


    <!-- Open Admissions -->
    <?php if ($isAdmin): ?>

        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">

                <div>
                    <h3 class="text-base font-bold text-slate-800">
                        Open Admissions
                    </h3>

                    <p class="text-xs text-slate-400 mt-1">
                        Currently admitted patients
                    </p>
                </div>

                <a href="manage-rooms.php"
                   class="text-sm font-semibold text-blue-600 hover:text-blue-800">
                    Manage →
                </a>

            </div>

            <div class="overflow-x-auto">

                <table class="min-w-full text-sm">

                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="text-left px-5 py-3 font-semibold">Admitted</th>
                            <th class="text-left px-5 py-3 font-semibold">Patient</th>
                            <th class="text-left px-5 py-3 font-semibold">Room</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">

                        <?php foreach ($openAdmissions as $a): ?>

                            <tr class="hover:bg-slate-50 transition">

                                <td class="px-5 py-4 text-slate-600 whitespace-nowrap">
                                    <?= htmlspecialchars($a['admitted_on']) ?>
                                </td>

                                <td class="px-5 py-4 font-medium text-slate-800 whitespace-nowrap">
                                    <?= htmlspecialchars($a['pf'] . ' ' . $a['pl']) ?>
                                </td>

                                <td class="px-5 py-4">
                                    <span class="inline-flex px-3 py-1 rounded-lg bg-blue-50 text-blue-700 text-xs font-semibold">
                                        <?= htmlspecialchars($a['room_number']) ?>
                                    </span>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <?php if (!$openAdmissions): ?>

                            <tr>
                                <td colspan="3" class="px-5 py-10 text-center text-slate-400">
                                    No open admissions.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>
        </section>

    <?php endif; ?>


    <!-- Doctor Recent Treatments -->
    <?php if ($isDoctor && $doctorId): ?>

        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden xl:col-span-2">

            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">

                <div>
                    <h3 class="text-base font-bold text-slate-800">
                        Recent Treatments
                    </h3>

                    <p class="text-xs text-slate-400 mt-1">
                        Your recently recorded patient treatments
                    </p>
                </div>

                <a href="manage-treatments.php"
                   class="text-sm font-semibold text-blue-600 hover:text-blue-800">
                    Manage →
                </a>

            </div>

            <div class="overflow-x-auto">

                <table class="min-w-full text-sm">

                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="text-left px-5 py-3 font-semibold">Date</th>
                            <th class="text-left px-5 py-3 font-semibold">Patient</th>
                            <th class="text-left px-5 py-3 font-semibold">Diagnosis</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">

                        <?php foreach ($docTreatments as $t): ?>

                            <tr class="hover:bg-slate-50 transition">

                                <td class="px-5 py-4 text-slate-600 whitespace-nowrap">
                                    <?= htmlspecialchars($t['treatment_date']) ?>
                                </td>

                                <td class="px-5 py-4 font-medium text-slate-800 whitespace-nowrap">
                                    <?= htmlspecialchars($t['pf'] . ' ' . $t['pl']) ?>
                                </td>

                                <td class="px-5 py-4 text-slate-600">
                                    <?= htmlspecialchars($t['diagnosis'] ?? '') ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <?php if (!$docTreatments): ?>

                            <tr>
                                <td colspan="3" class="px-5 py-10 text-center text-slate-400">
                                    No recent treatments.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>
        </section>

    <?php endif; ?>

</div>
            <input type="hidden" id="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>" />
            <div id="toast" class="fixed bottom-4 right-4 z-50 hidden">
                <div id="toast-content" class="rounded border px-3 py-2 text-sm shadow bg-white"></div>
            </div>
            <script>
                (function() {
                    const token = document.getElementById('csrf_token')?.value || '';
                    const toastEl = document.getElementById('toast');
                    const toastContent = document.getElementById('toast-content');

                    function showToast(msg, type) {
                        if (!toastEl || !toastContent) return;
                        toastContent.textContent = msg;
                        toastContent.className = 'rounded border px-3 py-2 text-sm shadow ' + (type === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800');
                        toastEl.classList.remove('hidden');
                        setTimeout(() => toastEl.classList.add('hidden'), 1800);
                    }

                    function updateCounts(oldStatus, newStatus) {
                        const ids = {
                            scheduled: 'count-scheduled',
                            completed: 'count-completed',
                            cancelled: 'count-cancelled'
                        };
                        if (ids[oldStatus]) {
                            const el = document.getElementById(ids[oldStatus]);
                            if (el) {
                                const v = parseInt(el.textContent || '0', 10);
                                el.textContent = Math.max(0, v - 1);
                            }
                        }
                        if (ids[newStatus]) {
                            const el = document.getElementById(ids[newStatus]);
                            if (el) {
                                const v = parseInt(el.textContent || '0', 10);
                                el.textContent = v + 1;
                            }
                        }
                    }

                    function applyStatusBadge(badge, status) {
                        if (!badge) return;
                        badge.dataset.status = status;
                        badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                        badge.className = 'inline-flex items-center px-2 py-0.5 rounded border text-xs ' + (
                            status === 'completed' ? 'border-emerald-200 text-emerald-800 bg-emerald-50' :
                            status === 'cancelled' ? 'border-red-200 text-red-800 bg-red-50' :
                            'border-slate-200'
                        );
                    }

                    document.addEventListener('click', async (e) => {
                        const t = e.target;
                        if (!(t instanceof HTMLElement)) return;
                        if (t.dataset?.action !== 'status') return;
                        e.preventDefault();
                        const id = t.dataset.id;
                        const status = t.dataset.status;
                        if (!id || !status) return;
                        t.disabled = true;
                        const row = t.closest('tr');
                        const badge = row ? row.querySelector('[data-role="status-badge"]') : null;
                        const oldStatus = badge ? (badge.getAttribute('data-status') || '') : '';
                        try {
                            const res = await fetch('api/appointment_status.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: new URLSearchParams({
                                    id,
                                    status,
                                    csrf_token: token
                                }).toString(),
                                credentials: 'same-origin'
                            });
                            if (!res.ok) throw new Error('network');
                            const js = await res.json();
                            if (js && js.ok) {
                                applyStatusBadge(badge, status);
                                if (oldStatus && oldStatus !== status) updateCounts(oldStatus, status);
                                if (row) {
                                    row.classList.add('bg-amber-50');
                                    setTimeout(() => row.classList.remove('bg-amber-50'), 600);
                                }
                                showToast('Status updated to ' + status + '.', 'success');
                            } else {
                                showToast('Could not update status.', 'error');
                            }
                        } catch (err) {
                            showToast('Could not update status.', 'error');
                        } finally {
                            t.disabled = false;
                        }
                    });
                })();
            </script>
    </main>
</body>

</html>