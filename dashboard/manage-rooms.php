<?php
require_once __DIR__ . '/../database.php';

require_any_role([ROLE_ADMIN]);
$isAdmin = has_role(ROLE_ADMIN);

/*
|--------------------------------------------------------------------------
| ROOM ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_room') {
    verify_csrf_or_die();

    rooms_create($_POST);

    flash_add('success', 'Room added.');
    redirect('./manage-rooms.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_room') {
    verify_csrf_or_die();

    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        rooms_update($id, $_POST);
        flash_add('success', 'Room updated.');
    }

    redirect('./manage-rooms.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_room') {
    verify_csrf_or_die();

    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        rooms_delete($id);
        flash_add('success', 'Room deleted.');
    }

    redirect('./manage-rooms.php');
}

/*
|--------------------------------------------------------------------------
| ADMISSION ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'admit') {
    verify_csrf_or_die();

    admissions_create($_POST);

    flash_add('success', 'Patient admitted.');
    redirect('./manage-rooms.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_admission') {
    verify_csrf_or_die();

    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        admissions_update($id, $_POST);
        flash_add('success', 'Admission updated.');
    }

    redirect('./manage-rooms.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'discharge') {
    verify_csrf_or_die();

    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        admissions_discharge($id);
        flash_add('success', 'Patient discharged.');
    }

    redirect('./manage-rooms.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_admission') {
    verify_csrf_or_die();

    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        admissions_delete($id);
        flash_add('success', 'Admission deleted.');
    }

    redirect('./manage-rooms.php');
}

/*
|--------------------------------------------------------------------------
| DATA
|--------------------------------------------------------------------------
*/

$buildPagination = static function (string $prefix, int $default = 10): array {
    $q = trim((string)($_GET[$prefix . '_q'] ?? ''));

    $allowedPer = [5, 10, 25, 50];
    $per = (int)($_GET[$prefix . '_per'] ?? $default);

    if (!in_array($per, $allowedPer, true)) {
        $per = $default;
    }

    $page = max(1, (int)($_GET[$prefix . '_page'] ?? 1));
    $offset = ($page - 1) * $per;

    return [
        'q' => $q,
        'page' => $page,
        'per' => $per,
        'limit' => $per,
        'offset' => $offset
    ];
};

$rp = $buildPagination('room');

$roomsData = rooms_list($rp['q'], $rp['limit'], $rp['offset']);
$rooms = $roomsData['rows'];

$roomsPages = (int)ceil(
    max(1, (int)$roomsData['total']) / $rp['per']
);

$patients = patients_options();

$prefillPatientId = isset($_GET['patient_id'])
    ? (int)$_GET['patient_id']
    : 0;

$availableRooms = rooms_options_available();

$roomEditId = isset($_GET['edit_room'])
    ? (int)$_GET['edit_room']
    : 0;

$roomEditRow = $roomEditId
    ? rooms_get($roomEditId)
    : null;

$ap = $buildPagination('admission');

$admData = admissions_list($ap['q'], $ap['limit'], $ap['offset']);
$admissions = $admData['rows'];

$admPages = (int)ceil(
    max(1, (int)$admData['total']) / $ap['per']
);

$admEditId = isset($_GET['edit_admission'])
    ? (int)$_GET['edit_admission']
    : 0;

$admEditRow = $admEditId
    ? admissions_get($admEditId)
    : null;

/*
|--------------------------------------------------------------------------
| HELPER DATA
|--------------------------------------------------------------------------
*/

$roomTypes = [
    'General',
    'Private',
    'ICU',
    'Emergency',
    'Maternity'
];

$roomStatuses = [
    'available',
    'occupied',
    'maintenance'
];

$admDtVal = '';

if ($admEditRow) {
    $dt = $admEditRow['admitted_on'];

    if ($dt && strpos($dt, ' ') !== false) {
        $admDtVal = str_replace(' ', 'T', substr($dt, 0, 16));
    } else {
        $admDtVal = (string)$dt;
    }
}

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Rooms & Admissions | MediFlow</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-50 text-slate-800">

    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="md:ml-64 min-h-screen p-4 sm:p-6 lg:p-8">

        <div class="max-w-7xl mx-auto">

            <!-- PAGE HEADER -->

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">

                <div>
                    <p class="text-sm font-semibold text-blue-600 uppercase tracking-wider">
                        Hospital Management
                    </p>

                    <h1 class="text-2xl sm:text-3xl font-bold text-slate-800 mt-1">
                        Rooms & Admissions
                    </h1>

                    <p class="text-sm text-slate-500 mt-2">
                        Manage hospital rooms, patient admissions and discharges.
                    </p>
                </div>

                <a
                    href="./index.php"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-100 font-medium text-sm"
                >
                    <span>←</span>
                    Back to Dashboard
                </a>

            </div>

            <?php render_flash(); ?>


            <!-- ROOM MANAGEMENT SECTION -->

            <section class="mb-10">

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">

                    <div>
                        <h2 class="text-xl font-bold text-slate-800">
                            Room Management
                        </h2>

                        <p class="text-sm text-slate-500 mt-1">
                            Add, update and manage hospital rooms.
                        </p>
                    </div>

                    <a
                        href="./manage-rooms.php"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold"
                    >
                        + New Room
                    </a>

                </div>


                <!-- ROOM FORM -->

                <div class="bg-white border border-slate-200 rounded-xl shadow-sm mb-6">

                    <div class="px-5 py-4 border-b border-slate-100">

                        <h3 class="font-semibold text-slate-800">
                            <?= $roomEditRow ? 'Update Room Information' : 'Add New Room' ?>
                        </h3>

                        <p class="text-xs text-slate-500 mt-1">
                            <?= $roomEditRow
                                ? 'Modify the selected room information.'
                                : 'Enter the details below to register a new room.' ?>
                        </p>

                    </div>

                    <form method="post" class="p-5 space-y-5">

                        <input
                            type="hidden"
                            name="action"
                            value="<?= $roomEditRow ? 'update_room' : 'create_room' ?>"
                        >

                        <?php if ($roomEditRow): ?>
                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int)$roomEditRow['id'] ?>"
                            >
                        <?php endif; ?>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

                            <!-- ROOM NUMBER -->

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2">
                                    Room Number
                                </label>

                                <input
                                    type="text"
                                    name="room_number"
                                    value="<?= htmlspecialchars($roomEditRow['room_number'] ?? '') ?>"
                                    placeholder="e.g. 101"
                                    required
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                >
                            </div>


                            <!-- ROOM TYPE -->

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2">
                                    Room Type
                                </label>

                                <select
                                    name="type"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                >
                                    <option value="">Select room type</option>

                                    <?php foreach ($roomTypes as $rt): ?>

                                        <option
                                            value="<?= htmlspecialchars($rt) ?>"
                                            <?= ($roomEditRow['type'] ?? '') === $rt ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars($rt) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>
                            </div>


                            <!-- ROOM STATUS -->

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2">
                                    Room Status
                                </label>

                                <select
                                    name="status"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                >

                                    <?php foreach ($roomStatuses as $rs): ?>

                                        <option
                                            value="<?= htmlspecialchars($rs) ?>"
                                            <?= ($roomEditRow['status'] ?? 'available') === $rs ? 'selected' : '' ?>
                                        >
                                            <?= ucfirst($rs) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>
                            </div>

                        </div>


                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(csrf_token()) ?>"
                        >


                        <div class="flex flex-wrap items-center gap-3 pt-2">

                            <button
                                type="submit"
                                class="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold"
                            >
                                <?= $roomEditRow ? 'Update Room' : 'Add Room' ?>
                            </button>

                            <?php if ($roomEditRow): ?>

                                <a
                                    href="./manage-rooms.php"
                                    class="px-5 py-2.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 text-sm font-medium"
                                >
                                    Cancel
                                </a>

                            <?php endif; ?>

                        </div>

                    </form>

                </div>


                <!-- ROOM SEARCH -->

                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm mb-5">

                    <form method="get" class="flex flex-col sm:flex-row gap-3">

                        <input
                            type="text"
                            name="room_q"
                            value="<?= htmlspecialchars($rp['q']) ?>"
                            placeholder="Search by room number..."
                            class="flex-1 rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                        >

                        <button
                            type="submit"
                            class="px-5 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold"
                        >
                            Search Rooms
                        </button>

                        <a
                            href="./manage-rooms.php"
                            class="px-5 py-2.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 text-sm font-medium text-center"
                        >
                            Reset
                        </a>

                    </form>

                </div>


                <!-- ROOM TABLE -->

                <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">

                    <div class="px-5 py-4 border-b border-slate-100">

                        <h3 class="font-semibold text-slate-800">
                            Hospital Room Directory
                        </h3>

                        <p class="text-xs text-slate-500 mt-1">
                            Registered rooms and their current availability.
                        </p>

                    </div>

                    <div class="overflow-x-auto">

                        <table class="w-full text-sm text-left">

                            <thead class="bg-slate-50 text-slate-600 uppercase text-xs">

                                <tr>
                                    <th class="px-5 py-4">Room Number</th>
                                    <th class="px-5 py-4">Room Type</th>
                                    <th class="px-5 py-4">Status</th>
                                    <th class="px-5 py-4 text-right">Actions</th>
                                </tr>

                            </thead>

                            <tbody class="divide-y divide-slate-100">

                                <?php foreach ($rooms as $r): ?>

                                    <?php
                                    $status = strtolower($r['status']);

                                    $statusClass = 'bg-slate-100 text-slate-700';

                                    if ($status === 'available') {
                                        $statusClass = 'bg-emerald-100 text-emerald-700';
                                    } elseif ($status === 'occupied') {
                                        $statusClass = 'bg-blue-100 text-blue-700';
                                    } elseif ($status === 'maintenance') {
                                        $statusClass = 'bg-amber-100 text-amber-700';
                                    }
                                    ?>

                                    <tr class="hover:bg-slate-50">

                                        <td class="px-5 py-4 font-semibold text-slate-800">
                                            <?= htmlspecialchars($r['room_number']) ?>
                                        </td>

                                        <td class="px-5 py-4 text-slate-600">
                                            <?= htmlspecialchars($r['type'] ?? 'Not specified') ?>
                                        </td>

                                        <td class="px-5 py-4">

                                            <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold <?= $statusClass ?>">
                                                <?= htmlspecialchars(ucfirst($status)) ?>
                                            </span>

                                        </td>

                                        <td class="px-5 py-4 text-right whitespace-nowrap">

                                            <a
                                                href="?edit_room=<?= (int)$r['id'] ?>"
                                                class="inline-flex px-3 py-1.5 rounded-lg border border-blue-200 text-blue-700 hover:bg-blue-50 text-xs font-semibold"
                                            >
                                                Edit
                                            </a>

                                            <form
                                                method="post"
                                                class="inline-block"
                                                onsubmit="return confirm('Are you sure you want to delete this room?')"
                                            >

                                                <input type="hidden" name="action" value="delete_room">

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

                                                <button
                                                    type="submit"
                                                    class="inline-flex px-3 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 text-xs font-semibold"
                                                >
                                                    Delete
                                                </button>

                                            </form>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>


                                <?php if (!$rooms): ?>

                                    <tr>
                                        <td colspan="4" class="px-5 py-10 text-center text-slate-500">
                                            No rooms found.
                                        </td>
                                    </tr>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>


                    <!-- ROOM PAGINATION -->

                    <?php if ($roomsPages > 1): ?>

                        <div class="px-5 py-4 border-t border-slate-100">

                            <nav aria-label="Room pagination">

                                <div class="flex flex-wrap gap-2">

                                    <?php for ($i = 1; $i <= $roomsPages; $i++): ?>

                                        <a
                                            href="?room_page=<?= $i ?>&room_per=<?= $rp['per'] ?>&room_q=<?= urlencode($rp['q']) ?>"
                                            class="px-3 py-2 rounded-lg border text-sm font-medium <?= $i === $rp['page']
                                                ? 'bg-blue-600 text-white border-blue-600'
                                                : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>"
                                        >
                                            <?= $i ?>
                                        </a>

                                    <?php endfor; ?>

                                </div>

                            </nav>

                        </div>

                    <?php endif; ?>

                </div>

            </section>



            <!-- ADMISSION MANAGEMENT SECTION -->

            <section class="mt-12">

                <div class="mb-5">

                    <h2 class="text-xl font-bold text-slate-800">
                        Patient Admissions
                    </h2>

                    <p class="text-sm text-slate-500 mt-1">
                        Manage patient admission, room assignment and discharge records.
                    </p>

                </div>


                <!-- ADMISSION FORM -->

                <div class="bg-white border border-slate-200 rounded-xl shadow-sm mb-6">

                    <div class="px-5 py-4 border-b border-slate-100">

                        <h3 class="font-semibold text-slate-800">
                            <?= $admEditRow ? 'Update Admission' : 'Admit New Patient' ?>
                        </h3>

                        <p class="text-xs text-slate-500 mt-1">
                            Enter patient and room information for admission.
                        </p>

                    </div>


                    <form method="post" class="p-5 space-y-5">

                        <input
                            type="hidden"
                            name="action"
                            value="<?= $admEditRow ? 'update_admission' : 'admit' ?>"
                        >

                        <?php if ($admEditRow): ?>

                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int)$admEditRow['id'] ?>"
                            >

                        <?php endif; ?>


                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

                            <!-- PATIENT -->

                            <div>

                                <label class="block text-sm font-medium text-slate-700 mb-2">
                                    Select Patient
                                </label>

                                <select
                                    name="patient_id"
                                    required
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-blue-500 outline-none"
                                >

                                    <option value="">Choose patient</option>

                                    <?php foreach ($patients as $patientOption): ?>

                                        <?php
                                        $sel = $admEditRow
                                            ? ((int)$admEditRow['patient_id'] === (int)$patientOption['id'])
                                            : ($prefillPatientId && (int)$patientOption['id'] === $prefillPatientId);
                                        ?>

                                        <option
                                            value="<?= (int)$patientOption['id'] ?>"
                                            <?= $sel ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars($patientOption['first_name'] . ' ' . $patientOption['last_name']) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- ROOM -->

                            <div>

                                <label class="block text-sm font-medium text-slate-700 mb-2">
                                    Assign Room
                                </label>

                                <select
                                    name="room_id"
                                    required
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-blue-500 outline-none"
                                >

                                    <option value="">Choose room</option>

                                    <?php if ($admEditRow): ?>

                                        <?php
                                        $currentRoomId = (int)$admEditRow['room_id'];
                                        $currentRoom = rooms_get($currentRoomId);
                                        ?>

                                        <?php if ($currentRoom): ?>

                                            <option
                                                value="<?= (int)$currentRoom['id'] ?>"
                                                selected
                                            >
                                                <?= htmlspecialchars($currentRoom['room_number']) ?> (Current Room)
                                            </option>

                                        <?php endif; ?>

                                    <?php endif; ?>


                                    <?php foreach ($availableRooms as $roomOption): ?>

                                        <?php
                                        if (
                                            $admEditRow &&
                                            (int)$roomOption['id'] === (int)$admEditRow['room_id']
                                        ) {
                                            continue;
                                        }
                                        ?>

                                        <option value="<?= (int)$roomOption['id'] ?>">
                                            <?= htmlspecialchars($roomOption['room_number']) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- ADMISSION DATE -->

                            <div>

                                <label class="block text-sm font-medium text-slate-700 mb-2">
                                    Admitted On
                                </label>

                                <input
                                    type="datetime-local"
                                    name="admitted_on"
                                    value="<?= htmlspecialchars($admDtVal) ?>"
                                    required
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                                >

                            </div>

                        </div>


                        <!-- NOTES -->

                        <div>

                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Admission Notes
                            </label>

                            <textarea
                                name="notes"
                                rows="3"
                                placeholder="Enter additional admission notes..."
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                            ><?= htmlspecialchars($admEditRow['notes'] ?? '') ?></textarea>

                        </div>


                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(csrf_token()) ?>"
                        >


                        <div class="flex flex-wrap items-center gap-3">

                            <button
                                type="submit"
                                class="px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold"
                            >
                                <?= $admEditRow ? 'Update Admission' : 'Admit Patient' ?>
                            </button>

                            <?php if ($admEditRow): ?>

                                <a
                                    href="./manage-rooms.php"
                                    class="px-5 py-2.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 text-sm font-medium"
                                >
                                    Cancel
                                </a>

                            <?php endif; ?>

                        </div>

                    </form>

                </div>



                <!-- ADMISSION SEARCH -->

                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm mb-5">

                    <form method="get" class="flex flex-col sm:flex-row gap-3">

                        <input
                            type="text"
                            name="admission_q"
                            value="<?= htmlspecialchars($ap['q']) ?>"
                            placeholder="Search admissions..."
                            class="flex-1 rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                        >

                        <button
                            type="submit"
                            class="px-5 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold"
                        >
                            Search Admissions
                        </button>

                        <a
                            href="./manage-rooms.php"
                            class="px-5 py-2.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 text-sm font-medium text-center"
                        >
                            Reset
                        </a>

                    </form>

                </div>



                <!-- ADMISSION TABLE -->

                <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">

                    <div class="px-5 py-4 border-b border-slate-100">

                        <h3 class="font-semibold text-slate-800">
                            Admission Records
                        </h3>

                        <p class="text-xs text-slate-500 mt-1">
                            View and manage patient admission history.
                        </p>

                    </div>


                    <div class="overflow-x-auto">

                        <table class="w-full text-sm text-left">

                            <thead class="bg-slate-50 text-slate-600 uppercase text-xs">

                                <tr>
                                    <th class="px-5 py-4">Admission Date</th>
                                    <th class="px-5 py-4">Patient</th>
                                    <th class="px-5 py-4">Room</th>
                                    <th class="px-5 py-4">Discharge Date</th>
                                    <th class="px-5 py-4">Status</th>
                                    <th class="px-5 py-4 text-right">Actions</th>
                                </tr>

                            </thead>


                            <tbody class="divide-y divide-slate-100">

                                <?php foreach ($admissions as $a): ?>

                                    <?php
                                    $isDischarged = $a['discharged_on'] !== null;
                                    ?>

                                    <tr class="hover:bg-slate-50">

                                        <td class="px-5 py-4 text-slate-600 whitespace-nowrap">
                                            <?= htmlspecialchars($a['admitted_on']) ?>
                                        </td>

                                        <td class="px-5 py-4">

                                            <div class="font-semibold text-slate-800">
                                                <?= htmlspecialchars($a['pf'] . ' ' . $a['pl']) ?>
                                            </div>

                                        </td>

                                        <td class="px-5 py-4">

                                            <span class="inline-flex px-3 py-1 rounded-lg bg-blue-50 text-blue-700 text-xs font-semibold">
                                                <?= htmlspecialchars($a['room_number']) ?>
                                            </span>

                                        </td>

                                        <td class="px-5 py-4 text-slate-600 whitespace-nowrap">
                                            <?= htmlspecialchars($a['discharged_on'] ?? 'Not discharged') ?>
                                        </td>

                                        <td class="px-5 py-4">

                                            <?php if ($isDischarged): ?>

                                                <span class="inline-flex px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-xs font-semibold">
                                                    Discharged
                                                </span>

                                            <?php else: ?>

                                                <span class="inline-flex px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">
                                                    Active
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <td class="px-5 py-4 text-right whitespace-nowrap">

                                            <a
                                                href="?edit_admission=<?= (int)$a['id'] ?>"
                                                class="inline-flex px-3 py-1.5 rounded-lg border border-blue-200 text-blue-700 hover:bg-blue-50 text-xs font-semibold"
                                            >
                                                Edit
                                            </a>


                                            <?php if (!$isDischarged): ?>

                                                <form
                                                    method="post"
                                                    class="inline-block"
                                                    onsubmit="return confirm('Are you sure you want to discharge this patient?')"
                                                >

                                                    <input type="hidden" name="action" value="discharge">

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int)$a['id'] ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= htmlspecialchars(csrf_token()) ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="inline-flex px-3 py-1.5 rounded-lg border border-emerald-200 text-emerald-700 hover:bg-emerald-50 text-xs font-semibold"
                                                    >
                                                        Discharge
                                                    </button>

                                                </form>

                                            <?php endif; ?>


                                            <form
                                                method="post"
                                                class="inline-block"
                                                onsubmit="return confirm('Are you sure you want to delete this admission record?')"
                                            >

                                                <input type="hidden" name="action" value="delete_admission">

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int)$a['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= htmlspecialchars(csrf_token()) ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="inline-flex px-3 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 text-xs font-semibold"
                                                >
                                                    Delete
                                                </button>

                                            </form>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>


                                <?php if (!$admissions): ?>

                                    <tr>
                                        <td colspan="6" class="px-5 py-10 text-center text-slate-500">
                                            No admission records found.
                                        </td>
                                    </tr>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>


                    <!-- ADMISSION PAGINATION -->

                    <?php if ($admPages > 1): ?>

                        <div class="px-5 py-4 border-t border-slate-100">

                            <nav aria-label="Admission pagination">

                                <div class="flex flex-wrap gap-2">

                                    <?php for ($i = 1; $i <= $admPages; $i++): ?>

                                        <a
                                            href="?admission_page=<?= $i ?>&admission_per=<?= $ap['per'] ?>&admission_q=<?= urlencode($ap['q']) ?>"
                                            class="px-3 py-2 rounded-lg border text-sm font-medium <?= $i === $ap['page']
                                                ? 'bg-blue-600 text-white border-blue-600'
                                                : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>"
                                        >
                                            <?= $i ?>
                                        </a>

                                    <?php endfor; ?>

                                </div>

                            </nav>

                        </div>

                    <?php endif; ?>

                </div>

            </section>


            <!-- FOOTER -->

            <div class="mt-10 pt-5 border-t border-slate-200 text-center text-xs text-slate-400">

                MediFlow Hospital Management System

            </div>

        </div>

    </main>

</body>
</html>