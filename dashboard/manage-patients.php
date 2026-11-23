<?php
require_once __DIR__ . '/../database.php';
require_any_role([ROLE_ADMIN, ROLE_DOCTOR]);
$isDoctor = has_role(ROLE_DOCTOR);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
  if ($isDoctor) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
  verify_csrf_or_die();
  patients_create($_POST);
  flash_add('success', 'Patient added.');
  redirect('./manage-patients.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
  if ($isDoctor) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
  verify_csrf_or_die();
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    patients_update($id, $_POST);
    flash_add('success', 'Patient updated.');
  }
  redirect('./manage-patients.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  if ($isDoctor) {
    http_response_code(403);
    exit('Forbidden');
  }

  verify_csrf_or_die();

  $delId = (int)($_POST['id'] ?? 0);

  if ($delId > 0) {
    patients_delete($delId);
    flash_add('success', 'Patient deleted.');
  }

  redirect('./manage-patients.php');
}

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = $editId ? patients_get($editId) : null;
if ($isDoctor && $editId > 0) {
    http_response_code(403);
    exit('Forbidden');
}
$p = get_pagination_params(10);
if ($isDoctor) {
    $user = current_user();
    $doctorId = (int)($user['linked_doctor_id'] ?? 0);

    if ($doctorId <= 0) {
        $rows = [];
        $pages = 1;
    } else {
        $like = '%' . $p['q'] . '%';

        $stmt = db()->prepare("
            SELECT DISTINCT p.*
            FROM patients p
            INNER JOIN appointments a ON a.patient_id = p.id
            WHERE a.doctor_id = ?
            AND (
                p.first_name LIKE ?
                OR p.last_name LIKE ?
                OR p.phone LIKE ?
                OR p.email LIKE ?
            )
            ORDER BY p.id DESC
            LIMIT " . (int)$p['limit'] . " OFFSET " . (int)$p['offset']
        );

        $stmt->execute([$doctorId, $like, $like, $like, $like]);
        $rows = $stmt->fetchAll();

        $count = db()->prepare("
            SELECT COUNT(DISTINCT p.id)
            FROM patients p
            INNER JOIN appointments a ON a.patient_id = p.id
            WHERE a.doctor_id = ?
            AND (
                p.first_name LIKE ?
                OR p.last_name LIKE ?
                OR p.phone LIKE ?
                OR p.email LIKE ?
            )
        ");

        $count->execute([$doctorId, $like, $like, $like, $like]);
        $total = (int)$count->fetchColumn();

        $pages = (int)ceil(max(1, $total) / $p['per']);
    }
} else {
    $data = patients_list($p['q'], $p['limit'], $p['offset']);
    $rows = $data['rows'];
    $pages = (int)ceil(max(1, (int)$data['total']) / $p['per']);
}
?>
<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Patients</title>
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
                    <?= $isDoctor ? 'My Patients' : (($editRow) ? 'Edit Patient' : 'Patient Management') ?>
                </h1>

                <p class="text-sm text-slate-500 mt-1">
                    <?= $isDoctor
                        ? 'View patients assigned to your appointments.'
                        : 'Manage patient information and medical records.' ?>
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a href="index.php"
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-slate-200 bg-white text-slate-700 text-sm font-medium hover:bg-slate-100 transition">
                    ← Dashboard
                </a>

                <?php if (!$isDoctor && !$editRow): ?>
                    <a href="#patient-form"
                       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 shadow-sm transition">
                        + New Patient
                    </a>
                <?php endif; ?>
            </div>

        </div>

        <?php render_flash(); ?>

        <!-- Search Section -->
        <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 mb-6">

            <div class="flex items-center gap-2 mb-4">
                <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600">
                    🔍
                </div>

                <div>
                    <h2 class="font-semibold text-slate-800">Search Patients</h2>
                    <p class="text-xs text-slate-500">Find patients by name, phone or email.</p>
                </div>
            </div>

            <form method="get" class="flex flex-col sm:flex-row gap-3">

                <input
                    type="text"
                    name="q"
                    placeholder="Search by patient name, phone, email..."
                    value="<?= htmlspecialchars($p['q']) ?>"
                    class="flex-1 rounded-lg border border-slate-300 px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >

                <div class="flex gap-2">
                    <button type="submit"
                        class="px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition">
                        Search
                    </button>

                    <a href="manage-patients.php"
                       class="px-5 py-2.5 rounded-lg border border-slate-300 text-slate-600 text-sm font-medium hover:bg-slate-50 transition">
                        Reset
                    </a>
                </div>

            </form>
        </section>


        <!-- Create / Edit Patient Form -->
        <?php if (!$isDoctor): ?>

            <section id="patient-form" class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 sm:p-6 mb-8">

                <div class="flex items-center gap-3 mb-6">

                    <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-xl">
                        👤
                    </div>

                    <div>
                        <h2 class="text-lg font-bold text-slate-800">
                            <?= $editRow ? 'Update Patient Information' : 'Add New Patient' ?>
                        </h2>

                        <p class="text-sm text-slate-500">
                            <?= $editRow
                                ? 'Modify the existing patient details.'
                                : 'Enter the patient information below.' ?>
                        </p>
                    </div>

                </div>

                <form method="post" class="space-y-5">

                    <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>">

                    <?php if ($editRow): ?>
                        <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
                    <?php endif; ?>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">
                                First Name <span class="text-red-500">*</span>
                            </label>

                            <input
                                type="text"
                                name="first_name"
                                value="<?= htmlspecialchars($editRow['first_name'] ?? '') ?>"
                                placeholder="Enter first name"
                                required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">
                                Last Name <span class="text-red-500">*</span>
                            </label>

                            <input
                                type="text"
                                name="last_name"
                                value="<?= htmlspecialchars($editRow['last_name'] ?? '') ?>"
                                placeholder="Enter last name"
                                required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">
                                Gender
                            </label>

                            <select name="gender"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm bg-white outline-none focus:ring-2 focus:ring-blue-500">

                                <option value="">Select Gender</option>

                                <?php foreach (['male', 'female', 'other'] as $g): ?>
                                    <option value="<?= $g ?>"
                                        <?= ($editRow['gender'] ?? '') === $g ? 'selected' : '' ?>>
                                        <?= ucfirst($g) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">
                                Birthdate
                            </label>

                            <input
                                type="date"
                                name="birthdate"
                                value="<?= htmlspecialchars($editRow['birthdate'] ?? '') ?>"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">
                                Phone Number
                            </label>

                            <input
                                type="text"
                                name="phone"
                                value="<?= htmlspecialchars($editRow['phone'] ?? '') ?>"
                                placeholder="Enter phone number"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">
                                Email Address
                            </label>

                            <input
                                type="email"
                                name="email"
                                value="<?= htmlspecialchars($editRow['email'] ?? '') ?>"
                                placeholder="Enter email address"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                            >
                        </div>

                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                            Address
                        </label>

                        <textarea
                            name="address"
                            rows="3"
                            placeholder="Enter patient address"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 resize-y"
                        ><?= htmlspecialchars($editRow['address'] ?? '') ?></textarea>
                    </div>

                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

                    <div class="flex flex-wrap items-center gap-3 pt-3 border-t border-slate-100">

                        <button type="submit"
                            class="px-6 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition shadow-sm">

                            <?= $editRow ? 'Update Patient' : 'Save Patient' ?>

                        </button>

                        <?php if ($editRow): ?>
                            <a href="manage-patients.php"
                               class="px-6 py-2.5 rounded-lg border border-slate-300 text-slate-600 text-sm font-medium hover:bg-slate-50 transition">
                                Cancel
                            </a>
                        <?php endif; ?>

                    </div>

                </form>

            </section>

        <?php endif; ?>


        <!-- Patient Records -->
        <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">

            <div class="p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

                <div>
                    <h2 class="text-lg font-bold text-slate-800">
                        Patient Records
                    </h2>

                    <p class="text-sm text-slate-500 mt-1">
                        Recently registered patients and their information.
                    </p>
                </div>

                <span class="text-xs font-semibold bg-blue-50 text-blue-700 px-3 py-2 rounded-full">
                    <?= $isDoctor ? 'Assigned Patients' : 'Patient Directory' ?>
                </span>

            </div>


            <div class="overflow-x-auto">

                <table class="min-w-full text-sm">

                    <thead class="bg-slate-50 text-slate-600">

                        <tr>
                            <th class="text-left px-5 py-4 font-semibold">Patient ID</th>
                            <th class="text-left px-5 py-4 font-semibold">Patient Name</th>
                            <th class="text-left px-5 py-4 font-semibold">Gender</th>
                            <th class="text-left px-5 py-4 font-semibold">Phone</th>
                            <th class="text-left px-5 py-4 font-semibold">Email</th>

                            <?php if (!$isDoctor): ?>
                                <th class="text-right px-5 py-4 font-semibold">Actions</th>
                            <?php endif; ?>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-slate-100">

                        <?php foreach ($rows as $r): ?>

                            <tr class="hover:bg-slate-50 transition">

                                <td class="px-5 py-4 font-medium text-slate-500">
                                    #<?= (int)$r['id'] ?>
                                </td>

                                <td class="px-5 py-4">

                                    <div class="flex items-center gap-3">

                                        <div class="w-9 h-9 rounded-full bg-blue-50 text-blue-700 flex items-center justify-center font-bold">
                                            <?= strtoupper(substr($r['first_name'], 0, 1)) ?>
                                        </div>

                                        <div class="font-semibold text-slate-800 whitespace-nowrap">
                                            <?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?>
                                        </div>

                                    </div>

                                </td>

                                <td class="px-5 py-4">

                                    <?php
                                    $g = strtolower($r['gender'] ?? '');

                                    $gbg = $g === 'male'
                                        ? 'bg-blue-50 text-blue-700 border-blue-200'
                                        : ($g === 'female'
                                            ? 'bg-pink-50 text-pink-700 border-pink-200'
                                            : 'bg-slate-50 text-slate-600 border-slate-200');
                                    ?>

                                    <span class="inline-flex items-center px-3 py-1 rounded-full border text-xs font-medium <?= $gbg ?>">
                                        <?= $g ? ucfirst($g) : 'Not specified' ?>
                                    </span>

                                </td>

                                <td class="px-5 py-4 text-slate-600 whitespace-nowrap">
                                    <?= htmlspecialchars($r['phone'] ?? '') ?: '-' ?>
                                </td>

                                <td class="px-5 py-4 text-slate-600">
                                    <?= htmlspecialchars($r['email'] ?? '') ?: '-' ?>
                                </td>


                                <?php if (!$isDoctor): ?>

                                    <td class="px-5 py-4">

                                        <div class="flex justify-end items-center gap-2">

                                            <a href="?edit=<?= (int)$r['id'] ?>"
                                               class="px-3 py-1.5 rounded-lg border border-blue-200 text-blue-700 text-xs font-semibold hover:bg-blue-50 transition">
                                                Edit
                                            </a>

                                            <form method="post"
                                                  class="inline"
                                                  onsubmit="return confirm('Are you sure you want to delete this patient?')">

                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

                                                <button type="submit"
                                                    class="px-3 py-1.5 rounded-lg border border-red-200 text-red-600 text-xs font-semibold hover:bg-red-50 transition">
                                                    Delete
                                                </button>

                                            </form>

                                            <a href="manage-rooms.php?patient_id=<?= (int)$r['id'] ?>"
                                               class="px-3 py-1.5 rounded-lg border border-emerald-200 text-emerald-700 text-xs font-semibold hover:bg-emerald-50 transition">
                                                Admit
                                            </a>

                                        </div>

                                    </td>

                                <?php endif; ?>

                            </tr>

                        <?php endforeach; ?>


                        <?php if (!$rows): ?>

                            <tr>
                                <td colspan="<?= $isDoctor ? 5 : 6 ?>" class="px-5 py-12 text-center">

                                    <div class="text-4xl mb-3">👥</div>

                                    <p class="font-semibold text-slate-700">
                                        No patients found
                                    </p>

                                    <p class="text-sm text-slate-500 mt-1">
                                        Try another search term or add a new patient.
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

    </main>

</body>

</html>