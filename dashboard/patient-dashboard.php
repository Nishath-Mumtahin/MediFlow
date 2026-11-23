<?php
require_once __DIR__ . '/../database.php';
require_any_role([ROLE_PATIENT]);

$user = current_user();
$patientId = (int)($user['linked_patient_id'] ?? 0);
$patient = null;
$appointments = $prescriptions = $treatments = [];
$errorMessage = '';

if ($patientId > 0) {
    $stmt = db()->prepare('SELECT id, first_name, last_name, gender, birthdate, phone, email, address FROM patients WHERE id = ? LIMIT 1');
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch() ?: null;

    if ($patient) {
        $stmt = db()->prepare('SELECT a.id, a.appointment_date, a.status, d.first_name AS doctor_first, d.last_name AS doctor_last, d.specialization FROM appointments a LEFT JOIN doctors d ON d.id = a.doctor_id WHERE a.patient_id = ? ORDER BY a.appointment_date DESC LIMIT 5');
        $stmt->execute([$patientId]);
        $appointments = $stmt->fetchAll();

        $stmt = db()->prepare('SELECT pr.id, pr.dosage, pr.frequency, pr.duration_days, m.name AS medicine_name, t.treatment_date FROM prescriptions pr INNER JOIN treatments t ON t.id = pr.treatment_id LEFT JOIN medications m ON m.id = pr.medication_id WHERE t.patient_id = ? ORDER BY pr.id DESC LIMIT 5');
        $stmt->execute([$patientId]);
        $prescriptions = $stmt->fetchAll();

        $stmt = db()->prepare('SELECT t.id, t.treatment_date, t.diagnosis, d.first_name AS doctor_first, d.last_name AS doctor_last FROM treatments t LEFT JOIN doctors d ON d.id = t.doctor_id WHERE t.patient_id = ? ORDER BY t.treatment_date DESC, t.id DESC LIMIT 5');
        $stmt->execute([$patientId]);
        $treatments = $stmt->fetchAll();
    } else {
        $errorMessage = 'Your patient profile could not be found. Please contact the administrator.';
    }
} else {
    $errorMessage = 'Your account is not linked to a patient profile yet. Please contact the administrator.';
}

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function status_class(string $status): string
{
    return match (strtolower($status)) {
        'completed' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'cancelled' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        default => 'bg-blue-50 text-blue-700 ring-blue-600/20',
    };
}

function display_date($value, string $format = 'd M Y, h:i A'): string
{
    if (!$value) return 'Not available';
    $timestamp = strtotime((string)$value);
    return $timestamp ? date($format, $timestamp) : 'Not available';
}

$patientName = $patient ? trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')) : ($user['username'] ?? 'Patient');
$initial = strtoupper(substr($patientName !== '' ? $patientName : 'P', 0, 1));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MediFlow | Patient Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
<?php include __DIR__ . '/sidebar.php'; ?>

<main class="min-h-screen pt-16 md:ml-64 md:pt-0">
    <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/95 backdrop-blur">
        <div class="flex min-h-[76px] items-center justify-between gap-4 px-5 py-3 sm:px-8">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">MediFlow / Patient Portal</p>
                <h1 class="mt-1 text-xl font-extrabold tracking-tight text-[#102F5B] sm:text-2xl">My Dashboard</h1>
            </div>
            <div class="flex items-center gap-3">
                <div class="hidden text-right sm:block">
                    <p class="text-sm font-bold text-slate-700"><?= h($user['username'] ?? 'Patient') ?></p>
                    <p class="mt-0.5 text-xs text-slate-400"><?= date('d F Y') ?></p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-sm font-extrabold text-[#102F5B] ring-4 ring-blue-50">
                    <?= h($initial) ?>
                </div>
            </div>
        </div>
    </header>

    <div class="mx-auto max-w-7xl space-y-7 p-5 sm:p-7 lg:p-8">
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#102F5B] via-[#174477] to-[#1D5B88] p-6 text-white shadow-lg shadow-blue-950/10 sm:p-8">
            <div class="pointer-events-none absolute -right-12 -top-20 h-64 w-64 rounded-full border-[35px] border-white/5"></div>
            <div class="pointer-events-none absolute -bottom-28 right-28 h-64 w-64 rounded-full bg-cyan-300/10 blur-2xl"></div>
            <div class="relative flex flex-col justify-between gap-6 sm:flex-row sm:items-center">
                <div class="max-w-2xl">
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em] text-blue-100">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span> Patient Portal
                    </span>
                    <h2 class="mt-4 text-2xl font-extrabold tracking-tight sm:text-3xl">Welcome, <?= h($patient ? ($patient['first_name'] ?? 'Patient') : ($user['username'] ?? 'Patient')) ?>!</h2>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100 sm:text-base">Your health information, recent appointments and treatment records in one place.</p>
                </div>
                <div class="flex shrink-0 items-center gap-3 rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-sm">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/15 text-xl">✚</div>
                    <div>
                        <p class="text-xs font-medium text-blue-100">Your healthcare</p>
                        <p class="text-sm font-bold">Made easier with MediFlow</p>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($errorMessage !== ''): ?>
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5" role="alert">
                <div class="flex items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-lg text-amber-700">!</span>
                    <div>
                        <h3 class="font-bold text-amber-900">Patient profile unavailable</h3>
                        <p class="mt-1 text-sm leading-6 text-amber-800"><?= h($errorMessage) ?></p>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm shadow-slate-200/40">
                    <div class="flex items-start justify-between">
                        <div><p class="text-sm font-semibold text-slate-500">Recent Appointments</p><p class="mt-3 text-3xl font-extrabold tracking-tight text-[#102F5B]"><?= count($appointments) ?></p><p class="mt-1 text-xs text-slate-400">Latest 5 records maximum</p></div>
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-xl">▦</span>
                    </div>
                    <a href="#appointments" class="mt-4 inline-flex text-xs font-bold text-blue-700 hover:text-blue-900">View appointments <span class="ml-1">→</span></a>
                </article>
                <article class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm shadow-slate-200/40">
                    <div class="flex items-start justify-between">
                        <div><p class="text-sm font-semibold text-slate-500">Recent Prescriptions</p><p class="mt-3 text-3xl font-extrabold tracking-tight text-[#102F5B]"><?= count($prescriptions) ?></p><p class="mt-1 text-xs text-slate-400">Latest 5 records maximum</p></div>
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-xl">✚</span>
                    </div>
                    <a href="#prescriptions" class="mt-4 inline-flex text-xs font-bold text-emerald-700 hover:text-emerald-900">View prescriptions <span class="ml-1">→</span></a>
                </article>
                <article class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm shadow-slate-200/40">
                    <div class="flex items-start justify-between">
                        <div><p class="text-sm font-semibold text-slate-500">Recent Treatments</p><p class="mt-3 text-3xl font-extrabold tracking-tight text-[#102F5B]"><?= count($treatments) ?></p><p class="mt-1 text-xs text-slate-400">Latest 5 records maximum</p></div>
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-50 text-xl">▤</span>
                    </div>
                    <a href="#medical-history" class="mt-4 inline-flex text-xs font-bold text-violet-700 hover:text-violet-900">View medical history <span class="ml-1">→</span></a>
                </article>
                <article class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm shadow-slate-200/40">
                    <div class="flex items-start justify-between">
                        <div><p class="text-sm font-semibold text-slate-500">My Profile</p><p class="mt-3 max-w-[175px] truncate text-lg font-extrabold text-[#102F5B]"><?= h($patientName) ?></p><p class="mt-1 text-xs text-slate-400">Personal information</p></div>
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-xl">◎</span>
                    </div>
                    <a href="#my-profile" class="mt-4 inline-flex text-xs font-bold text-amber-700 hover:text-amber-900">View profile <span class="ml-1">→</span></a>
                </article>
            </section>

            <section id="appointments" class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm shadow-slate-200/40">
                <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div><p class="text-[11px] font-bold uppercase tracking-[0.16em] text-blue-600">Your schedule</p><h3 class="mt-1 text-lg font-extrabold text-[#102F5B]">Recent Appointments</h3></div>
                    <span class="w-fit rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">Last 5 records</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[650px] w-full text-left text-sm">
                        <thead class="bg-slate-50/80 text-[11px] font-bold uppercase tracking-wider text-slate-500"><tr><th class="px-6 py-3.5">Date &amp; Time</th><th class="px-6 py-3.5">Doctor</th><th class="px-6 py-3.5">Specialization</th><th class="px-6 py-3.5">Status</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php foreach ($appointments as $a): ?>
                            <tr class="transition hover:bg-slate-50/70"><td class="whitespace-nowrap px-6 py-4 font-semibold text-slate-700"><?= h(display_date($a['appointment_date'])) ?></td><td class="px-6 py-4 font-medium text-slate-700">Dr. <?= h(trim(($a['doctor_first'] ?? '') . ' ' . ($a['doctor_last'] ?? ''))) ?></td><td class="px-6 py-4 text-slate-500"><?= h($a['specialization'] ?? 'Not specified') ?></td><td class="px-6 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset <?= status_class((string)$a['status']) ?>"><?= h(ucfirst((string)$a['status'])) ?></span></td></tr>
                        <?php endforeach; ?>
                        <?php if (!$appointments): ?><tr><td colspan="4" class="px-6 py-10 text-center"><p class="font-semibold text-slate-500">No appointment records yet</p><p class="mt-1 text-xs text-slate-400">Your recent appointments will appear here.</p></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <div id="prescriptions" class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm shadow-slate-200/40">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-5"><div><p class="text-[11px] font-bold uppercase tracking-[0.16em] text-emerald-600">Medication details</p><h3 class="mt-1 text-lg font-extrabold text-[#102F5B]">Recent Prescriptions</h3></div><span class="rounded-xl bg-emerald-50 p-2.5 text-lg">✚</span></div>
                    <div class="divide-y divide-slate-100">
                    <?php foreach ($prescriptions as $prescription): ?>
                        <article class="flex items-start gap-3 px-5 py-4">
                            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">Rx</span>
                            <div class="min-w-0 flex-1"><p class="font-bold text-slate-800"><?= h($prescription['medicine_name'] ?? 'Medicine') ?></p><p class="mt-1 text-sm text-slate-500"><?= h($prescription['dosage'] ?? '') ?> <span class="text-slate-300">·</span> <?= h($prescription['frequency'] ?? '') ?></p><div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-400"><span>Duration: <?= (int)$prescription['duration_days'] ?> days</span><span>Treatment: <?= h(display_date($prescription['treatment_date'], 'd M Y')) ?></span></div></div>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$prescriptions): ?><div class="px-5 py-10 text-center"><p class="font-semibold text-slate-500">No prescriptions found</p><p class="mt-1 text-xs text-slate-400">Your prescription records will appear here.</p></div><?php endif; ?>
                    </div>
                </div>

                <div id="medical-history" class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm shadow-slate-200/40">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-5"><div><p class="text-[11px] font-bold uppercase tracking-[0.16em] text-violet-600">Care records</p><h3 class="mt-1 text-lg font-extrabold text-[#102F5B]">Medical History</h3></div><span class="rounded-xl bg-violet-50 p-2.5 text-lg">▤</span></div>
                    <div class="divide-y divide-slate-100">
                    <?php foreach ($treatments as $treatment): ?>
                        <article class="flex items-start gap-3 px-5 py-4">
                            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-700">+</span>
                            <div class="min-w-0 flex-1"><p class="font-bold text-slate-800"><?= h($treatment['diagnosis'] ?: 'Treatment record') ?></p><p class="mt-1 text-sm text-slate-500">Dr. <?= h(trim(($treatment['doctor_first'] ?? '') . ' ' . ($treatment['doctor_last'] ?? ''))) ?></p><p class="mt-2 text-xs text-slate-400"><?= h(display_date($treatment['treatment_date'], 'd M Y')) ?></p></div>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$treatments): ?><div class="px-5 py-10 text-center"><p class="font-semibold text-slate-500">No treatment history yet</p><p class="mt-1 text-xs text-slate-400">Your treatment records will appear here.</p></div><?php endif; ?>
                    </div>
                </div>
            </section>

            <section id="my-profile" class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm shadow-slate-200/40">
                <div class="border-b border-slate-100 px-5 py-5 sm:px-6"><p class="text-[11px] font-bold uppercase tracking-[0.16em] text-amber-600">Account information</p><h3 class="mt-1 text-lg font-extrabold text-[#102F5B]">My Patient Profile</h3></div>
                <div class="grid gap-x-8 gap-y-5 p-5 sm:grid-cols-2 sm:p-6 lg:grid-cols-3">
                    <div><p class="text-xs font-semibold text-slate-400">Full Name</p><p class="mt-1 font-semibold text-slate-800"><?= h($patientName) ?></p></div>
                    <div><p class="text-xs font-semibold text-slate-400">Email Address</p><p class="mt-1 break-all font-semibold text-slate-800"><?= h($patient['email'] ?: 'Not provided') ?></p></div>
                    <div><p class="text-xs font-semibold text-slate-400">Phone Number</p><p class="mt-1 font-semibold text-slate-800"><?= h($patient['phone'] ?: 'Not provided') ?></p></div>
                    <div><p class="text-xs font-semibold text-slate-400">Gender</p><p class="mt-1 font-semibold capitalize text-slate-800"><?= h($patient['gender'] ?: 'Not provided') ?></p></div>
                    <div><p class="text-xs font-semibold text-slate-400">Date of Birth</p><p class="mt-1 font-semibold text-slate-800"><?= h(display_date($patient['birthdate'], 'd M Y')) ?></p></div>
                    <div><p class="text-xs font-semibold text-slate-400">Address</p><p class="mt-1 font-semibold text-slate-800"><?= h($patient['address'] ?: 'Not provided') ?></p></div>
                </div>
            </section>
        <?php endif; ?>

        <footer class="pb-4 pt-1 text-center text-xs text-slate-400">© <?= date('Y') ?> MediFlow Hospital Operations Management System</footer>
    </div>
</main>
</body>
</html>
