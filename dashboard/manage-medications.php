<?php
require_once __DIR__ . '/../database.php';
require_any_role([ROLE_ADMIN]);

// Create Medication
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    verify_csrf_or_die();

    try {
        medications_create($_POST);
        flash_add('success', 'Medication added.');
    } catch (Throwable $e) {
        flash_add('error', 'Could not add medication (maybe duplicate).');
    }

    redirect('./manage-medications.php');
}

// Update Medication
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    verify_csrf_or_die();

    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        medications_update($id, $_POST);
        flash_add('success', 'Medication updated.');
    }

    redirect('./manage-medications.php');
}

// Delete Medication
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verify_csrf_or_die();

    $delId = (int)($_POST['id'] ?? 0);

    if ($delId > 0) {
        medications_delete($delId);
        flash_add('success', 'Medication deleted.');
    }

    redirect('./manage-medications.php');
}

// Pagination and Search
$p = get_pagination_params(10);

$data = medications_list(
    $p['q'],
    $p['limit'],
    $p['offset']
);

$rows = $data['rows'];

$pages = (int) ceil(
    max(1, (int)$data['total']) / $p['per']
);

// Edit Medication
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = $editId ? medications_get($editId) : null;

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Medications | MediFlow</title>

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
                    <?= $editRow ? 'Edit Medication' : 'Medication Management' ?>
                </h1>

                <p class="text-sm text-slate-500 mt-1">
                    Manage hospital medications and their descriptions.
                </p>

            </div>

            <div class="flex items-center gap-2">

                <a href="index.php"
                   class="px-4 py-2.5 rounded-lg border border-slate-200 bg-white text-slate-700 text-sm font-medium hover:bg-slate-100 transition">

                    ← Dashboard

                </a>

                <?php if (!$editRow): ?>

                    <a href="#medication-form"
                       class="px-4 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 shadow-sm transition">

                        + New Medication

                    </a>

                <?php endif; ?>

            </div>

        </div>


        <!-- Flash Messages -->
        <?php render_flash(); ?>


        <!-- Search Section -->
        <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 mb-6">

            <div class="flex items-center gap-3 mb-4">

                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-lg">
                    🔍
                </div>

                <div>

                    <h2 class="font-semibold text-slate-800">
                        Search Medications
                    </h2>

                    <p class="text-xs text-slate-500">
                        Find medications by their name.
                    </p>

                </div>

            </div>


            <form method="get" class="flex flex-col sm:flex-row gap-3">

                <input
                    type="text"
                    name="q"
                    placeholder="Search medication name..."
                    value="<?= htmlspecialchars($p['q']) ?>"
                    class="flex-1 rounded-lg border border-slate-300 px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                >

                <div class="flex gap-2">

                    <button type="submit"
                        class="px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition">

                        Search

                    </button>

                    <a href="manage-medications.php"
                       class="px-5 py-2.5 rounded-lg border border-slate-300 text-slate-600 text-sm font-medium hover:bg-slate-50 transition">

                        Reset

                    </a>

                </div>

            </form>

        </section>


        <!-- Medication Form -->
        <section id="medication-form"
                 class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 sm:p-6 mb-6">

            <div class="flex items-center gap-3 mb-6">

                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-xl">
                    💊
                </div>

                <div>

                    <h2 class="text-lg font-bold text-slate-800">
                        <?= $editRow ? 'Update Medication' : 'Add New Medication' ?>
                    </h2>

                    <p class="text-sm text-slate-500">
                        Enter medication information below.
                    </p>

                </div>

            </div>


            <form method="post" class="space-y-5">

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


                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

                    <!-- Medication Name -->
                    <div>

                        <label class="block text-sm font-medium text-slate-700 mb-1.5">

                            Medication Name
                            <span class="text-red-500">*</span>

                        </label>

                        <input
                            type="text"
                            name="name"
                            placeholder="Enter medication name"
                            value="<?= htmlspecialchars($editRow['name'] ?? '') ?>"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                        >

                    </div>


                    <!-- Description -->
                    <div>

                        <label class="block text-sm font-medium text-slate-700 mb-1.5">

                            Description

                        </label>

                        <textarea
                            name="description"
                            rows="3"
                            placeholder="Enter medication description..."
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                        ><?= htmlspecialchars($editRow['description'] ?? '') ?></textarea>

                    </div>

                </div>


                <!-- CSRF Token -->
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(csrf_token()) ?>"
                >


                <!-- Form Buttons -->
                <div class="flex flex-wrap items-center gap-3 pt-4 border-t border-slate-100">

                    <button type="submit"
                        class="px-6 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition shadow-sm">

                        <?= $editRow ? 'Update Medication' : 'Save Medication' ?>

                    </button>

                    <?php if ($editRow): ?>

                        <a href="manage-medications.php"
                           class="px-5 py-2.5 rounded-lg border border-slate-300 text-slate-600 text-sm font-medium hover:bg-slate-50 transition">

                            Cancel

                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </section>


        <!-- Medication Records -->
        <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">

            <!-- Section Header -->
            <div class="p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

                <div>

                    <h2 class="text-lg font-bold text-slate-800">
                        Medication Records
                    </h2>

                    <p class="text-sm text-slate-500 mt-1">
                        View and manage available hospital medications.
                    </p>

                </div>

                <span class="text-xs font-semibold bg-blue-50 text-blue-700 px-3 py-2 rounded-full">

                    Total Records: <?= (int)$data['total'] ?>

                </span>

            </div>


            <!-- Table -->
            <div class="overflow-x-auto">

                <table class="min-w-full text-sm">

                    <thead class="bg-slate-50 text-slate-600">

                        <tr>

                            <th class="text-left px-5 py-4 font-semibold">
                                ID
                            </th>

                            <th class="text-left px-5 py-4 font-semibold">
                                Medication Name
                            </th>

                            <th class="text-left px-5 py-4 font-semibold">
                                Description
                            </th>

                            <th class="text-right px-5 py-4 font-semibold">
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-slate-100">

                        <?php foreach ($rows as $r): ?>

                            <tr class="hover:bg-slate-50 transition">

                                <!-- ID -->
                                <td class="px-5 py-4 text-slate-500 font-medium">

                                    #<?= (int)$r['id'] ?>

                                </td>


                                <!-- Medication Name -->
                                <td class="px-5 py-4">

                                    <span class="inline-flex items-center px-3 py-1.5 rounded-full bg-blue-50 text-blue-700 text-xs font-semibold">

                                        💊 <?= htmlspecialchars($r['name']) ?>

                                    </span>

                                </td>


                                <!-- Description -->
                                <td class="px-5 py-4 text-slate-600 max-w-md">

                                    <?php if (!empty($r['description'])): ?>

                                        <?= htmlspecialchars($r['description']) ?>

                                    <?php else: ?>

                                        <span class="text-slate-400">
                                            No description
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- Actions -->
                                <td class="px-5 py-4">

                                    <div class="flex flex-wrap justify-end items-center gap-2">

                                        <!-- Edit -->
                                        <a href="?edit=<?= (int)$r['id'] ?>"
                                           class="px-3 py-1.5 rounded-lg border border-blue-200 text-blue-700 text-xs font-semibold hover:bg-blue-50 transition">

                                            Edit

                                        </a>


                                        <!-- Delete -->
                                        <form method="post"
                                              class="inline"
                                              onsubmit="return confirm('Are you sure you want to delete this medication?')">

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$r['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars(csrf_token()) ?>"
                                            >

                                            <button type="submit"
                                                class="px-3 py-1.5 rounded-lg border border-red-200 text-red-600 text-xs font-semibold hover:bg-red-50 transition">

                                                Delete

                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>


                        <!-- Empty State -->
                        <?php if (!$rows): ?>

                            <tr>

                                <td colspan="4" class="px-5 py-12 text-center">

                                    <div class="text-4xl mb-3">
                                        💊
                                    </div>

                                    <p class="font-semibold text-slate-700">
                                        No medications found
                                    </p>

                                    <p class="text-sm text-slate-500 mt-1">
                                        Try another search term or add a new medication.
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