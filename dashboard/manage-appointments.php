<?php
require_once __DIR__ . '/../database.php';
require_any_role([ROLE_ADMIN, ROLE_DOCTOR]);
$isDoctor = has_role(ROLE_DOCTOR);
$user = current_user();
$doctorId = null;
if ($isDoctor) {
  $doctorId = (int)($user['linked_doctor_id'] ?? 0) ?: null;
}
$doctorUnlinked = $isDoctor && !$doctorId;

$patients = patients_options();
$doctors  = doctors_options();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
  if ($isDoctor) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
  verify_csrf_or_die();
  appointments_create($_POST);
  flash_add('success', 'Appointment created.');
  redirect('./manage-appointments.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
  verify_csrf_or_die();
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    if ($isDoctor) {
      if ($doctorUnlinked) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
      }
      $row = appointments_get($id);
      if (!$row || !$doctorId || (int)$row['doctor_id'] !== $doctorId) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
      }
      appointments_update($id, [
        'patient_id' => $row['patient_id'],
        'doctor_id' => $row['doctor_id'],
        'appointment_date' => $row['appointment_date'],
        'status' => $_POST['status'] ?? $row['status'],
        'notes' => $_POST['notes'] ?? $row['notes'],
      ]);
    } else {
      appointments_update($id, $_POST);
    }
    flash_add('success', 'Appointment updated.');
  }
  redirect('./manage-appointments.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  verify_csrf_or_die();

  if ($isDoctor) {
    http_response_code(403);
    exit('Forbidden');
  }

  $delId = (int)($_POST['id'] ?? 0);

  if ($delId > 0) {
    appointments_delete($delId);
    flash_add('success', 'Appointment deleted.');
  }

  redirect('./manage-appointments.php');
}

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = $editId ? appointments_get($editId) : null;

// Verify appointment ownership before showing edit page
if ($isDoctor && $editId > 0) {
    if (
        !$doctorId ||
        !$editRow ||
        (int)$editRow['doctor_id'] !== $doctorId
    ) {
        http_response_code(403);
        exit('Forbidden');
    }
}
$p = get_pagination_params(10);
if ($doctorUnlinked) {
  flash_add('error', 'Your account is not linked to a doctor profile. Please contact an administrator.');
  $rows = [];
  $pages = 1;
} elseif ($isDoctor && $doctorId !== null) {
  $data = appointments_list_for_doctor($doctorId, $p['q'], $p['limit'], $p['offset']);
  $rows = $data['rows'];
  $pages = (int) ceil(max(1, (int)$data['total']) / $p['per']);
} else {
  $data = appointments_list($p['q'], $p['limit'], $p['offset']);
  $rows = $data['rows'];
  $pages = (int) ceil(max(1, (int)$data['total']) / $p['per']);
}
?>
<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Appointments</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-slate-50 text-slate-800">

    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="md:ml-64 min-h-screen p-4 sm:p-6 lg:p-8">

        <!-- Page Header -->
        <div class="mb-6">

            <a href="index.php"
               class="inline-flex items-center gap-2 text-sm font-medium text-blue-600 hover:text-blue-800 mb-5">
                ← Back to Dashboard
            </a>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">

                <div>
                    <p class="text-sm font-semibold text-blue-600 mb-1">
                        MEDIFLOW HOSPITAL MANAGEMENT
                    </p>

                    <h1 class="text-3xl font-bold text-slate-800 tracking-tight">
                        Appointments
                    </h1>

                    <p class="text-sm text-slate-500 mt-2">
                        Manage patient appointments and schedules.
                    </p>
                </div>

                <?php if (!$isDoctor): ?>

                    <a href="./manage-appointments.php"
                       class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-blue-600 text-white text-sm font-semibold shadow-sm hover:bg-blue-700 transition">
                        + New Appointment
                    </a>

                <?php endif; ?>

            </div>
        </div>


        <!-- Flash Messages -->
        <?php render_flash(); ?>


        <!-- Search Section -->
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 mb-6">

            <form method="get" class="flex flex-col sm:flex-row gap-3">

                <div class="flex-1">

                    <label class="block text-sm font-semibold text-slate-700 mb-2">
                        Search Appointments
                    </label>

                    <input
                        type="text"
                        name="q"
                        placeholder="Search by patient, doctor, or status..."
                        value="<?= htmlspecialchars($p['q']) ?>"
                        class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500 transition"
                    >

                </div>

                <div class="flex items-end gap-2">

                    <button
                        type="submit"
                        class="px-5 py-3 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition">
                        Search
                    </button>

                    <a href="manage-appointments.php"
                       class="px-5 py-3 rounded-xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-50 transition">
                        Reset
                    </a>

                </div>

            </form>

        </section>


        <!-- Appointment Form -->
        <?php if (!($isDoctor && !$editRow)): ?>

            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">

                <div class="px-5 py-4 border-b border-slate-100">

                    <h2 class="text-lg font-bold text-slate-800">
                        <?= $editRow ? 'Edit Appointment' : 'Create New Appointment' ?>
                    </h2>

                    <p class="text-sm text-slate-500 mt-1">
                        <?= $editRow
                            ? 'Update the appointment information below.'
                            : 'Enter the details to schedule a new appointment.' ?>
                    </p>

                </div>


                <form method="post" class="p-5 space-y-5">

                    <input
                        type="hidden"
                        name="action"
                        value="<?= $editRow ? 'update' : 'create' ?>"
                    >

                    <?php if ($editRow): ?>

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int)$editRow['id'] ?>"
                        >

                    <?php endif; ?>


                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">

                        <!-- Patient -->
                        <div>

                            <label class="block text-sm font-semibold text-slate-700 mb-2">
                                Patient
                            </label>

                            <select
                                name="patient_id"
                                required
                                <?= $isDoctor ? 'disabled' : '' ?>
                                class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm bg-white outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500">

                                <?php foreach ($patients as $patientOption): ?>

                                    <option
                                        value="<?= (int)$patientOption['id'] ?>"
                                        <?= $editRow && (int)$editRow['patient_id'] === (int)$patientOption['id'] ? 'selected' : '' ?>>

                                        <?= htmlspecialchars($patientOption['first_name'] . ' ' . $patientOption['last_name']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- Doctor -->
                        <div>

                            <label class="block text-sm font-semibold text-slate-700 mb-2">
                                Doctor
                            </label>

                            <select
                                name="doctor_id"
                                required
                                <?= $isDoctor ? 'disabled' : '' ?>
                                class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm bg-white outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500">

                                <?php foreach ($doctors as $doctorOption): ?>

                                    <option
                                        value="<?= (int)$doctorOption['id'] ?>"
                                        <?= $editRow && (int)$editRow['doctor_id'] === (int)$doctorOption['id'] ? 'selected' : '' ?>>

                                        <?= htmlspecialchars($doctorOption['first_name'] . ' ' . $doctorOption['last_name']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- Date and Time -->
                        <?php

                        $dtVal = '';

                        if ($editRow) {

                            $dt = $editRow['appointment_date'];

                            if ($dt && strpos($dt, ' ') !== false) {

                                $dtVal = str_replace(' ', 'T', substr($dt, 0, 16));

                            } else {

                                $dtVal = $dt;

                            }

                        }

                        ?>

                        <div>

                            <label class="block text-sm font-semibold text-slate-700 mb-2">
                                Date & Time
                            </label>

                            <input
                                type="datetime-local"
                                name="appointment_date"
                                value="<?= htmlspecialchars($dtVal) ?>"
                                required
                                <?= $isDoctor ? 'disabled' : '' ?>
                                class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500"
                            >

                        </div>


                        <!-- Status -->
                        <div>

                            <label class="block text-sm font-semibold text-slate-700 mb-2">
                                Appointment Status
                            </label>

                            <select
                                name="status"
                                class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm bg-white outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500">

                                <?php foreach (['scheduled', 'completed', 'cancelled'] as $st): ?>

                                    <option
                                        value="<?= $st ?>"
                                        <?= ($editRow['status'] ?? 'scheduled') === $st ? 'selected' : '' ?>>

                                        <?= ucfirst($st) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>


                    <!-- Notes -->
                    <div>

                        <label class="block text-sm font-semibold text-slate-700 mb-2">
                            Notes
                        </label>

                        <textarea
                            name="notes"
                            rows="4"
                            placeholder="Enter appointment notes..."
                            class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500"><?= htmlspecialchars($editRow['notes'] ?? '') ?></textarea>

                    </div>


                    <?php if ($isDoctor): ?>

                        <div class="rounded-xl bg-blue-50 border border-blue-100 p-4">

                            <p class="text-sm text-blue-800 font-medium">
                                Doctor Permission
                            </p>

                            <p class="text-xs text-blue-700 mt-1">
                                You can update appointment Status and Notes only.
                            </p>

                        </div>

                    <?php endif; ?>


                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token()) ?>"
                    >


                    <!-- Form Buttons -->
                    <div class="flex flex-wrap items-center gap-3 pt-2">

                        <button
                            type="submit"
                            class="px-6 py-3 rounded-xl bg-blue-600 text-white text-sm font-semibold shadow-sm hover:bg-blue-700 transition">

                            <?= $editRow ? 'Update Appointment' : 'Create Appointment' ?>

                        </button>


                        <?php if ($editRow): ?>

                            <a href="./manage-appointments.php"
                               class="px-6 py-3 rounded-xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-50 transition">
                                Cancel
                            </a>

                        <?php endif; ?>

                    </div>

                </form>

            </section>


            <!-- Patient Summary -->
            <?php if ($isDoctor && $editRow): ?>

                <?php $pat = patients_get((int)$editRow['patient_id']); ?>

                <?php if ($pat): ?>

                    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 mb-8">

                        <h3 class="text-base font-bold text-slate-800 mb-4">
                            Patient Summary
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">

                            <div>
                                <p class="text-slate-400 text-xs mb-1">Patient Name</p>

                                <p class="font-semibold text-slate-800">
                                    <?= htmlspecialchars($pat['first_name'] . ' ' . $pat['last_name']) ?>
                                </p>
                            </div>


                            <div>
                                <p class="text-slate-400 text-xs mb-1">Gender</p>

                                <p class="font-semibold text-slate-800">
                                    <?= htmlspecialchars(ucfirst((string)($pat['gender'] ?? ''))) ?>
                                </p>
                            </div>


                            <div>
                                <p class="text-slate-400 text-xs mb-1">Birthdate</p>

                                <p class="font-semibold text-slate-800">
                                    <?= htmlspecialchars((string)($pat['birthdate'] ?? '')) ?>
                                </p>
                            </div>


                            <div>
                                <p class="text-slate-400 text-xs mb-1">Phone</p>

                                <p class="font-semibold text-slate-800">
                                    <?= htmlspecialchars((string)($pat['phone'] ?? '')) ?>
                                </p>
                            </div>


                            <div class="sm:col-span-2">

                                <p class="text-slate-400 text-xs mb-1">Address</p>

                                <p class="font-semibold text-slate-800">
                                    <?= htmlspecialchars((string)($pat['address'] ?? '')) ?>
                                </p>

                            </div>

                        </div>

                    </section>

                <?php endif; ?>

            <?php endif; ?>

        <?php endif; ?>


        <!-- Appointment List -->
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

            <div class="px-5 py-4 border-b border-slate-100">

                <h2 class="text-lg font-bold text-slate-800">
                    Appointment Records
                </h2>

                <p class="text-sm text-slate-500 mt-1">
                    View and manage appointment records.
                </p>

            </div>


            <div class="overflow-x-auto">

                <table class="min-w-full text-sm">

                    <thead class="bg-slate-50 text-slate-500">

                        <tr>

                            <th class="text-left px-5 py-4 font-semibold">
                                Date & Time
                            </th>

                            <th class="text-left px-5 py-4 font-semibold">
                                Patient
                            </th>

                            <?php if (!$isDoctor): ?>

                                <th class="text-left px-5 py-4 font-semibold">
                                    Doctor
                                </th>

                            <?php endif; ?>

                            <th class="text-left px-5 py-4 font-semibold">
                                Status
                            </th>

                            <th class="text-right px-5 py-4 font-semibold">
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-slate-100">

                        <?php foreach ($rows as $r): ?>

                            <?php

                            $st = (string)($r['status'] ?? 'scheduled');

                            $badgeCls = 'inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border ';

                            if ($st === 'completed') {

                                $badgeCls .= 'border-emerald-200 bg-emerald-50 text-emerald-700';

                            } elseif ($st === 'cancelled') {

                                $badgeCls .= 'border-red-200 bg-red-50 text-red-700';

                            } else {

                                $badgeCls .= 'border-amber-200 bg-amber-50 text-amber-700';

                            }

                            ?>

                            <tr class="hover:bg-slate-50 transition">

                                <td class="px-5 py-4 text-slate-600 whitespace-nowrap">
                                    <?= htmlspecialchars($r['appointment_date']) ?>
                                </td>


                                <td class="px-5 py-4 font-semibold text-slate-800 whitespace-nowrap">
                                    <?= htmlspecialchars($r['pf'] . ' ' . $r['pl']) ?>
                                </td>


                                <?php if (!$isDoctor): ?>

                                    <td class="px-5 py-4 text-slate-600 whitespace-nowrap">
                                        <?= htmlspecialchars($r['df'] . ' ' . $r['dl']) ?>
                                    </td>

                                <?php endif; ?>


                                <td class="px-5 py-4 whitespace-nowrap">

                                    <span class="<?= $badgeCls ?>">
                                        <?= htmlspecialchars(ucfirst($st)) ?>
                                    </span>

                                </td>


                                <td class="px-5 py-4 text-right whitespace-nowrap">

                                    <div class="flex justify-end items-center gap-2">

                                        <a
                                            href="?edit=<?= (int)$r['id'] ?>"
                                            class="px-3 py-2 rounded-lg border border-blue-200 text-blue-700 text-xs font-semibold hover:bg-blue-50 transition">
                                            Edit
                                        </a>


                                        <?php if ($isDoctor && $doctorId && (int)($r['doctor_id'] ?? 0) === (int)$doctorId && ($r['status'] ?? 'scheduled') !== 'cancelled'): ?>

                                            <a
                                                href="manage-treatments.php?appointment_id=<?= (int)$r['id'] ?>"
                                                class="px-3 py-2 rounded-lg border border-emerald-200 text-emerald-700 text-xs font-semibold hover:bg-emerald-50 transition">
                                                Add Treatment
                                            </a>

                                        <?php endif; ?>


                                        <?php if (!$isDoctor): ?>

                                            <form
                                                method="post"
                                                class="inline"
                                                onsubmit="return confirm('Delete appointment?')">

                                                <input type="hidden" name="action" value="delete">

                                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= htmlspecialchars(csrf_token()) ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="px-3 py-2 rounded-lg border border-red-200 text-red-700 text-xs font-semibold hover:bg-red-50 transition">
                                                    Delete
                                                </button>

                                            </form>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>


                        <?php if (!$rows): ?>

                            <tr>

                                <td
                                    colspan="<?= $isDoctor ? 4 : 5 ?>"
                                    class="px-5 py-12 text-center">

                                    <p class="text-slate-400 text-sm">
                                        No appointments found.
                                    </p>

                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- Pagination -->
        <?php if ($pages > 1): ?>

            <nav aria-label="pagination" class="mt-6">

                <div class="flex flex-wrap gap-2">

                    <?php for ($i = 1; $i <= $pages; $i++): ?>

                        <a
                            href="?page=<?= $i ?>&per=<?= $p['per'] ?>&q=<?= urlencode($p['q']) ?>"
                            class="min-w-10 text-center px-4 py-2 rounded-xl border text-sm font-semibold transition
                            <?= $i === $p['page']
                                ? 'bg-blue-600 text-white border-blue-600'
                                : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>">

                            <?= $i ?>

                        </a>

                    <?php endfor; ?>

                </div>

            </nav>

        <?php endif; ?>


    </main>

</body>

</html>