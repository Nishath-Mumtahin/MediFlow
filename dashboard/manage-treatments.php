<?php
require_once __DIR__ . '/../database.php';
require_any_role([ROLE_ADMIN, ROLE_DOCTOR]);
$isDoctor = has_role(ROLE_DOCTOR);
$user = current_user();
$doctorId = $isDoctor ? (int)($user['linked_doctor_id'] ?? 0) : null;
$doctorUnlinked = $isDoctor && !$doctorId;
if ($isDoctor && $doctorId) {
    $stmt = db()->prepare("
        SELECT DISTINCT p.id, p.first_name, p.last_name
        FROM patients p
        INNER JOIN appointments a ON a.patient_id = p.id
        WHERE a.doctor_id = ?
        ORDER BY p.first_name ASC
    ");
    $stmt->execute([$doctorId]);
    $patients = $stmt->fetchAll();
} elseif ($isDoctor) {
    $patients = [];
} else {
    $patients = patients_options();
}
$doctors  = doctors_options();
$apptsParamId = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
if ($isDoctor && $doctorId) {
    $stmt = db()->prepare("
        SELECT id, appointment_date, patient_id, doctor_id
        FROM appointments
        WHERE doctor_id = ?
        ORDER BY appointment_date DESC
        LIMIT 300
    ");
    $stmt->execute([$doctorId]);
    $appts = $stmt->fetchAll();
} elseif ($isDoctor) {
    $appts = [];
} else {
    $appts = appointments_minimal_recent(300);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    verify_csrf_or_die();

    $payload = $_POST;

    if ($isDoctor) {

        if (!$doctorId) {
            http_response_code(403);
            exit('Your account is not linked to a doctor profile.');
        }

        $patientId = (int)($_POST['patient_id'] ?? 0);
        $appointmentId = (int)($_POST['appointment_id'] ?? 0);

        // Check whether this patient belongs to this doctor's appointments
        $stmt = db()->prepare("
            SELECT COUNT(*)
            FROM appointments
            WHERE patient_id = ? AND doctor_id = ?
        ");
        $stmt->execute([$patientId, $doctorId]);

        if ((int)$stmt->fetchColumn() === 0) {
            http_response_code(403);
            exit('You are not authorized to treat this patient.');
        }

        // Validate selected appointment
        if ($appointmentId > 0) {
            $stmt = db()->prepare("
                SELECT COUNT(*)
                FROM appointments
                WHERE id = ? AND patient_id = ? AND doctor_id = ?
            ");
            $stmt->execute([$appointmentId, $patientId, $doctorId]);

            if ((int)$stmt->fetchColumn() === 0) {
                http_response_code(403);
                exit('Invalid appointment access.');
            }
        }

        $payload['doctor_id'] = $doctorId;
    }

    treatments_create($payload);
    flash_add('success', 'Treatment added.');
    redirect('./manage-treatments.php');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    verify_csrf_or_die();

    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {

        if ($isDoctor) {

            $row = treatments_get($id);

            // Check treatment ownership
            if (!$row || !$doctorId || (int)$row['doctor_id'] !== $doctorId) {
                http_response_code(403);
                exit('Forbidden');
            }

            $patientId = (int)($_POST['patient_id'] ?? 0);
            $appointmentId = (int)($_POST['appointment_id'] ?? 0);

            // Check patient access
            $stmt = db()->prepare("
                SELECT COUNT(*)
                FROM appointments
                WHERE patient_id = ? AND doctor_id = ?
            ");
            $stmt->execute([$patientId, $doctorId]);

            if ((int)$stmt->fetchColumn() === 0) {
                http_response_code(403);
                exit('You are not authorized to treat this patient.');
            }

            // Check appointment access
            if ($appointmentId > 0) {
                $stmt = db()->prepare("
                    SELECT COUNT(*)
                    FROM appointments
                    WHERE id = ? AND patient_id = ? AND doctor_id = ?
                ");
                $stmt->execute([$appointmentId, $patientId, $doctorId]);

                if ((int)$stmt->fetchColumn() === 0) {
                    http_response_code(403);
                    exit('Invalid appointment access.');
                }
            }
        }

        $payload = $_POST;

        if ($isDoctor) {
            $payload['doctor_id'] = $doctorId;
        }

        treatments_update($id, $payload);
        flash_add('success', 'Treatment updated.');
    }

    redirect('./manage-treatments.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if ($isDoctor) {
        http_response_code(403);
        exit('Forbidden');
    }

    verify_csrf_or_die();

    $delId = (int)($_POST['id'] ?? 0);

    if ($delId > 0) {
        treatments_delete($delId);
        flash_add('success', 'Treatment deleted.');
    }

    redirect('./manage-treatments.php');
}

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = $editId ? treatments_get($editId) : null;
$userOwnsEdit = true;
if ($isDoctor && $editId) {
  $userOwnsEdit = $editRow && $doctorId && (int)($editRow['doctor_id'] ?? 0) === (int)$doctorId;
  if (!$userOwnsEdit) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
}
$p = get_pagination_params(10);
if ($doctorUnlinked) {
  flash_add('error', 'Your account is not linked to a doctor profile. Please contact an administrator.');
  $rows = [];
  $pages = 1;
} else if ($isDoctor && $doctorId) {
  $data = treatments_list_for_doctor($doctorId, $p['q'], $p['limit'], $p['offset']);
  $rows = $data['rows'];
  $pages = (int) ceil(max(1, (int)$data['total']) / $p['per']);
} else {
  $data = treatments_list($p['q'], $p['limit'], $p['offset']);
  $rows = $data['rows'];
  $pages = (int) ceil(max(1, (int)$data['total']) / $p['per']);
}
?>
<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Treatments</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-50 min-h-screen">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="md:ml-64 min-h-screen p-4 sm:p-6 lg:p-8">

        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">

            <div>
                <p class="text-xs font-bold tracking-widest text-blue-600 uppercase mb-2">
                    MediFlow Hospital Management
                </p>

                <h1 class="text-2xl sm:text-3xl font-bold text-slate-800">
                    <?= $editRow ? 'Edit Treatment' : ($isDoctor ? 'My Treatments' : 'Treatment Management') ?>
                </h1>

                <p class="text-sm text-slate-500 mt-1">
                    Manage patient treatments, diagnoses and appointment records.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a href="index.php"
                   class="px-4 py-2.5 rounded-lg border border-slate-200 bg-white text-slate-700 text-sm font-medium hover:bg-slate-100 transition">
                    ← Dashboard
                </a>

                <?php if (!$doctorUnlinked && !$editRow): ?>
                    <a href="#treatment-form"
                       class="px-4 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 shadow-sm transition">
                        + New Treatment
                    </a>
                <?php endif; ?>
            </div>

        </div>

        <?php render_flash(); ?>


        <!-- Search -->
        <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 mb-6">

            <div class="flex items-center gap-3 mb-4">

                <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center">
                    🔍
                </div>

                <div>
                    <h2 class="font-semibold text-slate-800">Search Treatments</h2>
                    <p class="text-xs text-slate-500">
                        Search by patient, doctor or diagnosis.
                    </p>
                </div>

            </div>

            <form method="get" class="flex flex-col sm:flex-row gap-3">

                <input
                    type="text"
                    name="q"
                    placeholder="Search patient, doctor, diagnosis..."
                    value="<?= htmlspecialchars($p['q']) ?>"
                    class="flex-1 rounded-lg border border-slate-300 px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                >

                <div class="flex gap-2">

                    <button type="submit"
                        class="px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition">
                        Search
                    </button>

                    <a href="manage-treatments.php"
                       class="px-5 py-2.5 rounded-lg border border-slate-300 text-slate-600 text-sm font-medium hover:bg-slate-50 transition">
                        Reset
                    </a>

                </div>

            </form>

        </section>


        <!-- Doctor Patient Summary -->
        <?php if ($isDoctor && $editRow): ?>
            <?php $pat = patients_get((int)$editRow['patient_id']); ?>

            <?php if ($pat): ?>

                <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 mb-6">

                    <div class="flex items-center gap-3 mb-4">

                        <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                            👤
                        </div>

                        <div>
                            <h2 class="font-bold text-slate-800">Patient Summary</h2>
                            <p class="text-xs text-slate-500">Patient information</p>
                        </div>

                    </div>

                    <div class="grid sm:grid-cols-2 gap-3 text-sm">

                        <div>
                            <span class="text-slate-500">Name:</span>
                            <span class="font-medium text-slate-800">
                                <?= htmlspecialchars($pat['first_name'] . ' ' . $pat['last_name']) ?>
                            </span>
                        </div>

                        <div>
                            <span class="text-slate-500">Gender:</span>
                            <?= htmlspecialchars(ucfirst((string)($pat['gender'] ?? ''))) ?>
                        </div>

                        <div>
                            <span class="text-slate-500">Birthdate:</span>
                            <?= htmlspecialchars((string)($pat['birthdate'] ?? '')) ?>
                        </div>

                        <div>
                            <span class="text-slate-500">Phone:</span>
                            <?= htmlspecialchars((string)($pat['phone'] ?? '')) ?>
                        </div>

                        <div class="sm:col-span-2">
                            <span class="text-slate-500">Address:</span>
                            <?= htmlspecialchars((string)($pat['address'] ?? '')) ?>
                        </div>

                    </div>

                </section>

            <?php endif; ?>
        <?php endif; ?>


        <!-- Edit Appointment Summary -->
        <?php if ($editRow && (int)($editRow['appointment_id'] ?? 0) > 0): ?>

            <?php
            $appt = appointments_get((int)$editRow['appointment_id']);
            $patSummary = $appt ? patients_get((int)$appt['patient_id']) : null;
            $docSummary = $appt ? doctors_get((int)$appt['doctor_id']) : null;
            ?>

            <?php if ($appt): ?>

                <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 mb-6">

                    <div class="flex items-center gap-3 mb-4">

                        <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                            📅
                        </div>

                        <div>
                            <h2 class="font-bold text-slate-800">Appointment Summary</h2>
                            <p class="text-xs text-slate-500">Linked appointment information</p>
                        </div>

                    </div>

                    <div class="grid sm:grid-cols-2 gap-3 text-sm">

                        <div>
                            <span class="text-slate-500">When:</span>
                            <?= htmlspecialchars((string)($appt['appointment_date'] ?? '')) ?>
                        </div>

                        <?php if ($docSummary): ?>
                            <div>
                                <span class="text-slate-500">Doctor:</span>
                                <?= htmlspecialchars(($docSummary['first_name'] ?? '') . ' ' . ($docSummary['last_name'] ?? '')) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($patSummary): ?>
                            <div class="sm:col-span-2">
                                <span class="text-slate-500">Patient:</span>
                                <?= htmlspecialchars(($patSummary['first_name'] ?? '') . ' ' . ($patSummary['last_name'] ?? '')) ?>
                            </div>
                        <?php endif; ?>

                    </div>

                </section>

            <?php endif; ?>

        <?php endif; ?>


        <!-- Treatment Form -->
        <section id="treatment-form"
                 class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 sm:p-6 mb-6">

            <div class="flex items-center gap-3 mb-6">

                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-xl">
                    🩺
                </div>

                <div>
                    <h2 class="text-lg font-bold text-slate-800">
                        <?= $editRow ? 'Update Treatment' : 'Add New Treatment' ?>
                    </h2>

                    <p class="text-sm text-slate-500">
                        Record patient diagnosis and treatment date.
                    </p>
                </div>

            </div>


            <form method="post" class="space-y-5">

                <input type="hidden" name="action"
                       value="<?= $editRow ? 'update' : 'create' ?>">

                <?php if ($editRow): ?>
                    <input type="hidden" name="id"
                           value="<?= (int)$editRow['id'] ?>">
                <?php endif; ?>


                <?php
                $lockedAppointmentId = 0;
                $defaultPatientId = 0;
                $defaultDoctorId = 0;

                if ($apptsParamId > 0) {
                    foreach ($appts as $a) {
                        if ((int)$a['id'] === $apptsParamId) {
                            $lockedAppointmentId = (int)$a['id'];
                            $defaultPatientId = (int)$a['patient_id'];
                            $defaultDoctorId = (int)$a['doctor_id'];
                            break;
                        }
                    }
                }

                if ($isDoctor && $doctorId && !$defaultDoctorId) {
                    $defaultDoctorId = $doctorId;
                }
                ?>


                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

                    <!-- Patient -->
                    <div>

                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                            Patient <span class="text-red-500">*</span>
                        </label>

                        <select
                            name="patient_id"
                            id="patient_id"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm bg-white outline-none focus:ring-2 focus:ring-blue-500">

                            <?php foreach ($patients as $patientOption): ?>

                                <?php
                                $sel = $editRow
                                    ? (int)$editRow['patient_id'] === (int)$patientOption['id']
                                    : ($defaultPatientId && (int)$patientOption['id'] === $defaultPatientId);
                                ?>

                                <option value="<?= (int)$patientOption['id'] ?>"
                                    <?= $sel ? 'selected' : '' ?>>

                                    <?= htmlspecialchars($patientOption['first_name'] . ' ' . $patientOption['last_name']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- Doctor -->
                    <div>

                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                            Doctor
                        </label>

                        <select
                            name="doctor_id"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm bg-white outline-none focus:ring-2 focus:ring-blue-500"
                            <?= $isDoctor ? 'disabled' : '' ?>>

                            <option value="">Select Doctor</option>

                            <?php foreach ($doctors as $doctorOption): ?>

                                <?php
                                $docSel = $editRow
                                    ? (int)($editRow['doctor_id'] ?? 0) === (int)$doctorOption['id']
                                    : (($defaultDoctorId && (int)$doctorOption['id'] === $defaultDoctorId)
                                        || ($isDoctor && $doctorId === (int)$doctorOption['id']));
                                ?>

                                <option value="<?= (int)$doctorOption['id'] ?>"
                                    <?= $docSel ? 'selected' : '' ?>>

                                    <?= htmlspecialchars($doctorOption['first_name'] . ' ' . $doctorOption['last_name']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <?php if ($isDoctor && $doctorId): ?>
                            <input type="hidden" name="doctor_id"
                                   value="<?= (int)$doctorId ?>">
                        <?php endif; ?>

                    </div>


                    <!-- Appointment -->
                    <div>

                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                            Appointment
                        </label>

                        <select
                            name="appointment_id"
                            id="appointment_id"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm bg-white outline-none focus:ring-2 focus:ring-blue-500"
                            <?= $lockedAppointmentId ? 'disabled' : '' ?>>

                            <option value="">Select Appointment</option>

                            <?php foreach ($appts as $a): ?>

                                <?php
                                $keep = true;

                                if (!$editRow) {
                                    if ($defaultPatientId && (int)$a['patient_id'] !== $defaultPatientId) {
                                        $keep = false;
                                    }

                                    if ($defaultDoctorId && (int)$a['doctor_id'] !== $defaultDoctorId) {
                                        $keep = false;
                                    }
                                }

                                if ($keep):

                                    $selected = $editRow
                                        ? ((int)($editRow['appointment_id'] ?? 0) === (int)$a['id'])
                                        : ($lockedAppointmentId && (int)$a['id'] === $lockedAppointmentId);
                                ?>

                                    <option value="<?= (int)$a['id'] ?>"
                                        <?= $selected ? 'selected' : '' ?>>

                                        <?= htmlspecialchars($a['appointment_date']) ?>

                                    </option>

                                <?php endif; ?>

                            <?php endforeach; ?>

                        </select>

                        <?php if ($lockedAppointmentId): ?>
                            <input type="hidden" name="appointment_id"
                                   value="<?= (int)$lockedAppointmentId ?>">
                        <?php endif; ?>

                    </div>


                    <!-- Treatment Date -->
                    <div>

                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                            Treatment Date <span class="text-red-500">*</span>
                        </label>

                        <input
                            type="date"
                            name="treatment_date"
                            value="<?= htmlspecialchars($editRow['treatment_date'] ?? date('Y-m-d')) ?>"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                        >

                    </div>

                </div>


                <!-- Diagnosis -->
                <div>

                    <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        Diagnosis / Treatment Details
                    </label>

                    <textarea
                        name="diagnosis"
                        rows="4"
                        placeholder="Enter diagnosis and treatment details..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 resize-y"
                    ><?= htmlspecialchars($editRow['diagnosis'] ?? '') ?></textarea>

                </div>


                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars(csrf_token()) ?>">


                <div class="flex flex-wrap items-center gap-3 pt-4 border-t border-slate-100">

                    <button type="submit"
                        class="px-6 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition shadow-sm">

                        <?= $editRow ? 'Update Treatment' : 'Save Treatment' ?>

                    </button>

                    <?php if ($editRow): ?>

                        <a href="manage-treatments.php"
                           class="px-5 py-2.5 rounded-lg border border-slate-300 text-slate-600 text-sm font-medium hover:bg-slate-50 transition">
                            Cancel
                        </a>

                        <a href="manage-prescriptions.php?treatment_id=<?= (int)$editRow['id'] ?>"
                           class="px-5 py-2.5 rounded-lg border border-emerald-200 text-emerald-700 text-sm font-semibold hover:bg-emerald-50 transition">
                            + Add Prescription
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </section>


        <!-- New Treatment Appointment Summary -->
        <?php
        if (!$editRow && $lockedAppointmentId) {

            $appt = appointments_get((int)$lockedAppointmentId);
            $patSummary = null;
            $docSummary = null;

            if ($appt) {
                $patSummary = patients_get((int)$appt['patient_id']);
                $docSummary = doctors_get((int)$appt['doctor_id']);
            }
        ?>

            <?php if ($appt): ?>

                <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 mb-6">

                    <div class="font-bold text-slate-800 mb-3">
                        Appointment Summary
                    </div>

                    <div class="grid sm:grid-cols-2 gap-3 text-sm">

                        <div>
                            <span class="text-slate-500">When:</span>
                            <?= htmlspecialchars((string)($appt['appointment_date'] ?? '')) ?>
                        </div>

                        <?php if ($docSummary): ?>
                            <div>
                                <span class="text-slate-500">Doctor:</span>
                                <?= htmlspecialchars(($docSummary['first_name'] ?? '') . ' ' . ($docSummary['last_name'] ?? '')) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($patSummary): ?>
                            <div class="sm:col-span-2">
                                <span class="text-slate-500">Patient:</span>
                                <?= htmlspecialchars(($patSummary['first_name'] ?? '') . ' ' . ($patSummary['last_name'] ?? '')) ?>
                            </div>
                        <?php endif; ?>

                    </div>

                </section>

            <?php endif; ?>

        <?php } ?>


        <!-- New Treatment Patient Summary -->
        <?php

        if ($isDoctor && !$editRow) {

            $pid = 0;

            if ($lockedAppointmentId && !empty($appt)) {
                $pid = (int)($appt['patient_id'] ?? 0);
            } elseif (!empty($defaultPatientId)) {
                $pid = (int)$defaultPatientId;
            }

            if ($pid > 0) {
                $pat = patients_get($pid);
            }

            if (!empty($pat)):
        ?>

                <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 mb-6">

                    <div class="font-bold text-slate-800 mb-3">
                        Patient Summary
                    </div>

                    <div class="grid sm:grid-cols-2 gap-3 text-sm">

                        <div>
                            <span class="text-slate-500">Name:</span>
                            <?= htmlspecialchars(($pat['first_name'] ?? '') . ' ' . ($pat['last_name'] ?? '')) ?>
                        </div>

                        <div>
                            <span class="text-slate-500">Gender:</span>
                            <?= htmlspecialchars(ucfirst((string)($pat['gender'] ?? ''))) ?>
                        </div>

                        <div>
                            <span class="text-slate-500">Birthdate:</span>
                            <?= htmlspecialchars((string)($pat['birthdate'] ?? '')) ?>
                        </div>

                        <div>
                            <span class="text-slate-500">Phone:</span>
                            <?= htmlspecialchars((string)($pat['phone'] ?? '')) ?>
                        </div>

                        <div class="sm:col-span-2">
                            <span class="text-slate-500">Address:</span>
                            <?= htmlspecialchars((string)($pat['address'] ?? '')) ?>
                        </div>

                    </div>

                </section>

        <?php
            endif;
        }
        ?>


        <!-- Treatment Records -->
        <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">

            <div class="p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

                <div>
                    <h2 class="text-lg font-bold text-slate-800">
                        Treatment Records
                    </h2>

                    <p class="text-sm text-slate-500 mt-1">
                        View patient diagnoses and treatment history.
                    </p>
                </div>

                <span class="text-xs font-semibold bg-blue-50 text-blue-700 px-3 py-2 rounded-full">
                    <?= $isDoctor ? 'My Treatment Records' : 'All Treatments' ?>
                </span>

            </div>


            <div class="overflow-x-auto">

                <table class="min-w-full text-sm">

                    <thead class="bg-slate-50 text-slate-600">

                        <tr>

                            <th class="text-left px-5 py-4 font-semibold">Date</th>

                            <th class="text-left px-5 py-4 font-semibold">Patient</th>

                            <?php if (!$isDoctor): ?>
                                <th class="text-left px-5 py-4 font-semibold">Doctor</th>
                            <?php endif; ?>

                            <th class="text-left px-5 py-4 font-semibold">Diagnosis</th>

                            <th class="text-right px-5 py-4 font-semibold">Actions</th>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-slate-100">

                        <?php foreach ($rows as $r): ?>

                            <?php
                            $own = $isDoctor && $doctorId
                                && (int)($r['doctor_id'] ?? 0) === (int)$doctorId;
                            ?>

                            <tr class="hover:bg-slate-50 transition">

                                <td class="px-5 py-4 text-slate-600 whitespace-nowrap">
                                    <?= htmlspecialchars($r['treatment_date']) ?>
                                </td>


                                <td class="px-5 py-4">

                                    <span class="font-semibold text-slate-800 whitespace-nowrap">
                                        <?= htmlspecialchars($r['pf'] . ' ' . $r['pl']) ?>
                                    </span>

                                </td>


                                <?php if (!$isDoctor): ?>

                                    <td class="px-5 py-4 text-slate-600 whitespace-nowrap">
                                        <?= htmlspecialchars(($r['df'] ?? '') . ' ' . ($r['dl'] ?? '')) ?>
                                    </td>

                                <?php endif; ?>


                                <td class="px-5 py-4 text-slate-600 max-w-xs">
                                    <div class="line-clamp-2">
                                        <?= htmlspecialchars($r['diagnosis'] ?? '') ?: '-' ?>
                                    </div>
                                </td>


                                <td class="px-5 py-4">

                                    <div class="flex flex-wrap justify-end items-center gap-2">

                                        <a href="?edit=<?= (int)$r['id'] ?>"
                                           class="px-3 py-1.5 rounded-lg border border-blue-200 text-blue-700 text-xs font-semibold hover:bg-blue-50 transition">
                                            Edit
                                        </a>


                                        <?php if (!$isDoctor || $own): ?>

                                            <a href="manage-prescriptions.php?treatment_id=<?= (int)$r['id'] ?>"
                                               class="px-3 py-1.5 rounded-lg border border-emerald-200 text-emerald-700 text-xs font-semibold hover:bg-emerald-50 transition">
                                                Add Prescription
                                            </a>

                                        <?php endif; ?>


                                        <?php if (!$isDoctor): ?>

                                            <form method="post"
                                                  class="inline"
                                                  onsubmit="return confirm('Are you sure you want to delete this treatment?')">

                                                <input type="hidden" name="action" value="delete">

                                                <input type="hidden" name="id"
                                                       value="<?= (int)$r['id'] ?>">

                                                <input type="hidden" name="csrf_token"
                                                       value="<?= htmlspecialchars(csrf_token()) ?>">

                                                <button type="submit"
                                                    class="px-3 py-1.5 rounded-lg border border-red-200 text-red-600 text-xs font-semibold hover:bg-red-50 transition">
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

                                <td colspan="<?= $isDoctor ? 4 : 5 ?>"
                                    class="px-5 py-12 text-center">

                                    <div class="text-4xl mb-3">🩺</div>

                                    <p class="font-semibold text-slate-700">
                                        No treatments found
                                    </p>

                                    <p class="text-sm text-slate-500 mt-1">
                                        Try another search term or add a new treatment.
                                    </p>

                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>


            <!-- Pagination -->
            <?php if ($pages > 1): ?>

                <div class="p-5 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3">

                    <p class="text-sm text-slate-500">
                        Page <?= (int)$p['page'] ?> of <?= (int)$pages ?>
                    </p>

                    <nav aria-label="Pagination" class="flex flex-wrap gap-2">

                        <?php for ($i = 1; $i <= $pages; $i++): ?>

                            <a
                                href="?page=<?= $i ?>&per=<?= $p['per'] ?>&q=<?= urlencode($p['q']) ?>"
                                class="min-w-9 text-center px-3 py-2 rounded-lg border text-sm font-medium transition
                                <?= $i === $p['page']
                                    ? 'bg-blue-600 text-white border-blue-600'
                                    : 'border-slate-200 text-slate-600 hover:bg-slate-100' ?>"
                            >
                                <?= $i ?>
                            </a>

                        <?php endfor; ?>

                    </nav>

                </div>

            <?php endif; ?>

        </section>


        <!-- Appointment Dropdown Script -->
        <script>
            (function() {

                const patientSelect = document.getElementById('patient_id');
                const apptSelect = document.getElementById('appointment_id');

                if (!patientSelect || !apptSelect) return;

                if (apptSelect.hasAttribute('disabled')) return;

                const baseUrl = 'api/appointments_options.php';

                async function refreshAppointments() {

                    const pid = patientSelect.value || '';

                    if (!pid) {
                        apptSelect.innerHTML = '<option value="">Select Appointment</option>';
                        return;
                    }

                    apptSelect.innerHTML = '<option value="">Loading...</option>';

                    try {

                        const res = await fetch(
                            baseUrl + '?patient_id=' + encodeURIComponent(pid),
                            { credentials: 'same-origin' }
                        );

                        if (!res.ok) throw new Error('Network error');

                        const data = await res.json();

                        const opts = ['<option value="">Select Appointment</option>'];

                        for (const a of data) {

                            const label = a.appointment_date || ('Appointment #' + a.id);

                            opts.push(
                                `<option value="${a.id}">${label}</option>`
                            );

                        }

                        apptSelect.innerHTML = opts.join('');

                    } catch (e) {

                        apptSelect.innerHTML = '<option value="">Select Appointment</option>';

                    }

                }

                patientSelect.addEventListener('change', refreshAppointments);

            })();
        </script>

    </main>

</body>

</html>