<?php
require_once __DIR__ . '/../database.php';
require_any_role([ROLE_ADMIN, ROLE_DOCTOR]);
$isDoctor = has_role(ROLE_DOCTOR);
$user = current_user();
$doctorId = $isDoctor ? (int)($user['linked_doctor_id'] ?? 0) : null;
$doctorUnlinked = $isDoctor && !$doctorId;
$treatmentsParamId = isset($_GET['treatment_id']) ? (int)$_GET['treatment_id'] : 0;
$treatments = $isDoctor && $doctorId ? treatments_options_recent_with_patient_for_doctor($doctorId, 200) : treatments_options_recent_with_patient(200);
$meds = medications_options();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    verify_csrf_or_die();

    if ($isDoctor) {
        if (!$doctorId) {
            http_response_code(403);
            exit('Forbidden');
        }

        $t = treatments_get((int)($_POST['treatment_id'] ?? 0));

        if (!$t || (int)($t['doctor_id'] ?? 0) !== $doctorId) {
            http_response_code(403);
            exit('Forbidden');
        }
    }

    prescriptions_create($_POST);
    flash_add('success', 'Prescription added.');
    redirect('./manage-prescriptions.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    verify_csrf_or_die();

    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        if ($isDoctor) {
            if (!$doctorId) {
                http_response_code(403);
                exit('Forbidden');
            }

            // Check existing prescription ownership
            $row = prescriptions_get($id);
            $currentTreatment = $row
                ? treatments_get((int)$row['treatment_id'])
                : null;

            if (
                !$row ||
                !$currentTreatment ||
                (int)$currentTreatment['doctor_id'] !== $doctorId
            ) {
                http_response_code(403);
                exit('Forbidden');
            }

            // Check newly selected treatment ownership
            $newTreatment = treatments_get(
                (int)($_POST['treatment_id'] ?? 0)
            );

            if (
                !$newTreatment ||
                (int)$newTreatment['doctor_id'] !== $doctorId
            ) {
                http_response_code(403);
                exit('Forbidden');
            }
        }

        prescriptions_update($id, $_POST);
        flash_add('success', 'Prescription updated.');
    }

    redirect('./manage-prescriptions.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if ($isDoctor) {
        http_response_code(403);
        exit('Forbidden');
    }

    verify_csrf_or_die();

    $delId = (int)($_POST['id'] ?? 0);

    if ($delId > 0) {
        prescriptions_delete($delId);
        flash_add('success', 'Prescription deleted.');
    }

    redirect('./manage-prescriptions.php');
}

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = $editId ? prescriptions_get($editId) : null;
if ($isDoctor && $editRow) {
    $editTreatment = treatments_get((int)$editRow['treatment_id']);

    if (!$doctorId || !$editTreatment || (int)$editTreatment['doctor_id'] !== $doctorId) {
        http_response_code(403);
        exit('Forbidden');
    }
}
$p = get_pagination_params(10);
if ($doctorUnlinked) {
  flash_add('error', 'Your account is not linked to a doctor profile. Please contact an administrator.');
  $rows = [];
  $pages = 1;
} else if ($isDoctor && $doctorId) {
  $data = prescriptions_list_for_doctor($doctorId, $p['q'], $p['limit'], $p['offset']);
  $rows = $data['rows'];
  $pages = (int) ceil(max(1, (int)$data['total']) / $p['per']);
} else {
  $data = prescriptions_list($p['q'], $p['limit'], $p['offset']);
  $rows = $data['rows'];
  $pages = (int) ceil(max(1, (int)$data['total']) / $p['per']);
}
?>
<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Prescriptions</title>
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
                    <?= $editRow ? 'Edit Prescription' : ($isDoctor ? 'My Prescriptions' : 'Prescription Management') ?>
                </h1>

                <p class="text-sm text-slate-500 mt-1">
                    Manage patient prescriptions, medications and dosage information.
                </p>
            </div>

            <div class="flex items-center gap-2">

                <a href="index.php"
                   class="px-4 py-2.5 rounded-lg border border-slate-200 bg-white text-slate-700 text-sm font-medium hover:bg-slate-100 transition">
                    ← Dashboard
                </a>

                <?php if (!$doctorUnlinked && !$editRow): ?>
                    <a href="#prescription-form"
                       class="px-4 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 shadow-sm transition">
                        + New Prescription
                    </a>
                <?php endif; ?>

            </div>

        </div>

        <?php render_flash(); ?>


        <!-- Search Section -->
        <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 mb-6">

            <div class="flex items-center gap-3 mb-4">

                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-lg">
                    🔍
                </div>

                <div>
                    <h2 class="font-semibold text-slate-800">
                        Search Prescriptions
                    </h2>

                    <p class="text-xs text-slate-500">
                        Search by patient name or medication.
                    </p>
                </div>

            </div>

            <form method="get" class="flex flex-col sm:flex-row gap-3">

                <input
                    type="text"
                    name="q"
                    placeholder="Search patient or medication..."
                    value="<?= htmlspecialchars($p['q']) ?>"
                    class="flex-1 rounded-lg border border-slate-300 px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                >

                <div class="flex gap-2">

                    <button type="submit"
                        class="px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition">
                        Search
                    </button>

                    <a href="manage-prescriptions.php"
                       class="px-5 py-2.5 rounded-lg border border-slate-300 text-slate-600 text-sm font-medium hover:bg-slate-50 transition">
                        Reset
                    </a>

                </div>

            </form>

        </section>


        <!-- Linked Treatment Summary -->
        <?php
        $summaryTreatmentId = $editRow
            ? (int)$editRow['treatment_id']
            : $treatmentsParamId;

        $linkedTreatment = $summaryTreatmentId > 0
            ? treatments_get($summaryTreatmentId)
            : null;

        if (
    $isDoctor &&
    $linkedTreatment &&
    (!$doctorId || (int)$linkedTreatment['doctor_id'] !== $doctorId)
) 
{
    http_response_code(403);
    exit('Forbidden');
}

        $patientSummary = $linkedTreatment
            ? patients_get((int)$linkedTreatment['patient_id'])
            : null;

        $doctorSummary = $linkedTreatment
            ? doctors_get((int)$linkedTreatment['doctor_id'])
            : null;
        ?>

        <?php if ($linkedTreatment): ?>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 mb-6">

                <div class="flex items-center gap-3 mb-4">

                    <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center text-lg">
                        📋
                    </div>

                    <div>
                        <h2 class="font-bold text-slate-800">
                            Linked Treatment
                        </h2>

                        <p class="text-xs text-slate-500">
                            Information about the selected treatment.
                        </p>
                    </div>

                </div>

                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">

                    <div>
                        <p class="text-slate-500 mb-1">Treatment ID</p>
                        <p class="font-semibold text-slate-800">
                            #<?= (int)$linkedTreatment['id'] ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-slate-500 mb-1">Treatment Date</p>
                        <p class="font-semibold text-slate-800">
                            <?= htmlspecialchars((string)($linkedTreatment['treatment_date'] ?? '')) ?>
                        </p>
                    </div>

                    <?php if ($patientSummary): ?>
                        <div>
                            <p class="text-slate-500 mb-1">Patient</p>
                            <p class="font-semibold text-slate-800">
                                <?= htmlspecialchars(($patientSummary['first_name'] ?? '') . ' ' . ($patientSummary['last_name'] ?? '')) ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if ($doctorSummary): ?>
                        <div>
                            <p class="text-slate-500 mb-1">Doctor</p>
                            <p class="font-semibold text-slate-800">
                                <?= htmlspecialchars(($doctorSummary['first_name'] ?? '') . ' ' . ($doctorSummary['last_name'] ?? '')) ?>
                            </p>
                        </div>
                    <?php endif; ?>

                </div>

            </section>

        <?php endif; ?>


        <!-- Prescription Form -->
        <section id="prescription-form"
                 class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 sm:p-6 mb-6">

            <div class="flex items-center gap-3 mb-6">

                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-xl">
                    💊
                </div>

                <div>
                    <h2 class="text-lg font-bold text-slate-800">
                        <?= $editRow ? 'Update Prescription' : 'Add New Prescription' ?>
                    </h2>

                    <p class="text-sm text-slate-500">
                        Enter medication and dosage details.
                    </p>
                </div>

            </div>


            <form method="post" class="space-y-5">

                <input type="hidden"
                       name="action"
                       value="<?= $editRow ? 'update' : 'create' ?>">

                <?php if ($editRow): ?>
                    <input type="hidden"
                           name="id"
                           value="<?= (int)$editRow['id'] ?>">
                <?php endif; ?>


                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

                    <!-- Treatment -->
                    <div>

                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                            Treatment <span class="text-red-500">*</span>
                        </label>

                        <select
                            name="treatment_id"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm bg-white outline-none focus:ring-2 focus:ring-blue-500"
                            <?= $treatmentsParamId ? 'disabled' : '' ?>>

                            <option value="">
                                Select Treatment
                            </option>

                            <?php foreach ($treatments as $treatmentOption): ?>

                                <?php
                                $treatmentSelected = $editRow
                                    ? ((int)$editRow['treatment_id'] === (int)$treatmentOption['id'])
                                    : ($treatmentsParamId && (int)$treatmentOption['id'] === $treatmentsParamId);
                                ?>

                                <option
                                    value="<?= (int)$treatmentOption['id'] ?>"
                                    <?= $treatmentSelected ? 'selected' : '' ?>>

                                    #<?= (int)$treatmentOption['id'] ?>
                                    -
                                    <?= htmlspecialchars(
                                        $treatmentOption['treatment_date'] .
                                        ' - ' .
                                        $treatmentOption['first_name'] .
                                        ' ' .
                                        $treatmentOption['last_name']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <?php if ($treatmentsParamId): ?>
                            <input type="hidden"
                                   name="treatment_id"
                                   value="<?= (int)$treatmentsParamId ?>">
                        <?php endif; ?>

                    </div>


                    <!-- Medication -->
                    <div>

                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                            Medication <span class="text-red-500">*</span>
                        </label>

                        <select
                            name="medication_id"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm bg-white outline-none focus:ring-2 focus:ring-blue-500">

                            <option value="">
                                Select Medication
                            </option>

                            <?php foreach ($meds as $m): ?>

                                <option
                                    value="<?= (int)$m['id'] ?>"
                                    <?= $editRow && (int)$editRow['medication_id'] === (int)$m['id'] ? 'selected' : '' ?>>

                                    <?= htmlspecialchars($m['name']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- Dosage -->
                    <div>

                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                            Dosage <span class="text-red-500">*</span>
                        </label>

                        <input
                            type="text"
                            name="dosage"
                            placeholder="Example: 500mg"
                            value="<?= htmlspecialchars($editRow['dosage'] ?? '') ?>"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500">

                    </div>


                    <!-- Frequency -->
                    <div>

                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                            Frequency <span class="text-red-500">*</span>
                        </label>

                        <input
                            type="text"
                            name="frequency"
                            placeholder="Example: Twice daily"
                            value="<?= htmlspecialchars($editRow['frequency'] ?? '') ?>"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500">

                    </div>


                    <!-- Duration -->
                    <div>

                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                            Duration (Days) <span class="text-red-500">*</span>
                        </label>

                        <input
                            type="number"
                            name="duration_days"
                            min="1"
                            placeholder="Example: 7"
                            value="<?= htmlspecialchars((string)($editRow['duration_days'] ?? '')) ?>"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500">

                    </div>

                </div>


                <input type="hidden"
                       name="csrf_token"
                       value="<?= htmlspecialchars(csrf_token()) ?>">


                <div class="flex flex-wrap items-center gap-3 pt-4 border-t border-slate-100">

                    <button type="submit"
                        class="px-6 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition shadow-sm">

                        <?= $editRow ? 'Update Prescription' : 'Save Prescription' ?>

                    </button>

                    <?php if ($editRow): ?>

                        <a href="manage-prescriptions.php"
                           class="px-5 py-2.5 rounded-lg border border-slate-300 text-slate-600 text-sm font-medium hover:bg-slate-50 transition">
                            Cancel
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </section>


        <!-- Prescription Records -->
        <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">

            <div class="p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

                <div>
                    <h2 class="text-lg font-bold text-slate-800">
                        Prescription Records
                    </h2>

                    <p class="text-sm text-slate-500 mt-1">
                        View and manage prescribed medications.
                    </p>
                </div>

                <span class="text-xs font-semibold bg-blue-50 text-blue-700 px-3 py-2 rounded-full">
                    <?= $isDoctor ? 'My Prescriptions' : 'All Prescriptions' ?>
                </span>

            </div>


            <div class="overflow-x-auto">

                <table class="min-w-full text-sm">

                    <thead class="bg-slate-50 text-slate-600">

                        <tr>

                            <th class="text-left px-5 py-4 font-semibold">
                                ID
                            </th>

                            <th class="text-left px-5 py-4 font-semibold">
                                Patient
                            </th>

                            <th class="text-left px-5 py-4 font-semibold">
                                Medication
                            </th>

                            <th class="text-left px-5 py-4 font-semibold">
                                Dosage
                            </th>

                            <th class="text-left px-5 py-4 font-semibold">
                                Frequency
                            </th>

                            <th class="text-left px-5 py-4 font-semibold">
                                Duration
                            </th>

                            <th class="text-right px-5 py-4 font-semibold">
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-slate-100">

                        <?php foreach ($rows as $r): ?>

                            <tr class="hover:bg-slate-50 transition">

                                <td class="px-5 py-4 text-slate-500 font-medium">
                                    #<?= (int)$r['id'] ?>
                                </td>

                                <td class="px-5 py-4">

                                    <span class="font-semibold text-slate-800 whitespace-nowrap">
                                        <?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?>
                                    </span>

                                </td>

                                <td class="px-5 py-4">

                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 text-xs font-semibold">
                                        <?= htmlspecialchars($r['med_name']) ?>
                                    </span>

                                </td>

                                <td class="px-5 py-4 text-slate-600 whitespace-nowrap">
                                    <?= htmlspecialchars($r['dosage']) ?>
                                </td>

                                <td class="px-5 py-4 text-slate-600 whitespace-nowrap">
                                    <?= htmlspecialchars($r['frequency']) ?>
                                </td>

                                <td class="px-5 py-4 text-slate-600 whitespace-nowrap">
                                    <?= (int)$r['duration_days'] ?> Days
                                </td>

                                <td class="px-5 py-4">

                                    <div class="flex flex-wrap justify-end items-center gap-2">

                                        <a href="?edit=<?= (int)$r['id'] ?>"
                                           class="px-3 py-1.5 rounded-lg border border-blue-200 text-blue-700 text-xs font-semibold hover:bg-blue-50 transition">
                                            Edit
                                        </a>

                                        <?php if (!$isDoctor): ?>

                                            <form method="post"
                                                  class="inline"
                                                  onsubmit="return confirm('Are you sure you want to delete this prescription?')">

                                                <input type="hidden"
                                                       name="action"
                                                       value="delete">

                                                <input type="hidden"
                                                       name="id"
                                                       value="<?= (int)$r['id'] ?>">

                                                <input type="hidden"
                                                       name="csrf_token"
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

                                <td colspan="7" class="px-5 py-12 text-center">

                                    <div class="text-4xl mb-3">
                                        💊
                                    </div>

                                    <p class="font-semibold text-slate-700">
                                        No prescriptions found
                                    </p>

                                    <p class="text-sm text-slate-500 mt-1">
                                        Try another search term or add a new prescription.
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
                                    : 'border-slate-200 text-slate-600 hover:bg-slate-100' ?>">

                                <?= $i ?>

                            </a>

                        <?php endfor; ?>

                    </nav>

                </div>

            <?php endif; ?>

        </section>

    </main>

</body>
</html>