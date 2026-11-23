<?php
require_once __DIR__ . '/../database.php';
require_any_role([ROLE_ADMIN]);

$allowedRoles = [ROLE_ADMIN, ROLE_DOCTOR, ROLE_PATIENT];

function user_save_error(Throwable $e, string $fallback): string
{
    if ($e instanceof InvalidArgumentException) {
        return $e->getMessage();
    }

    if ($e instanceof PDOException && ($e->errorInfo[0] ?? '') === '23000') {
        return 'That username, email or profile link is already used by another account.';
    }

    return $fallback;
}

// Preserve non-sensitive create-form values after a validation/database error.
$oldForm = $_SESSION['manage_users_old'] ?? [];
unset($_SESSION['manage_users_old']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    verify_csrf_or_die();

    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $role = (string)($_POST['role'] ?? ROLE_PATIENT);

    try {
        if (!in_array($role, $allowedRoles, true)) {
            throw new InvalidArgumentException('Invalid user role.');
        }
        if ($username === '' || $email === '' || $password === '') {
            throw new InvalidArgumentException('Username, email and password are required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Please enter a valid email address.');
        }

        create_user_with_profile($_POST);
        flash_add('success', 'User created.');
    } catch (Throwable $e) {
        $oldForm = $_POST;
        unset($oldForm['password'], $oldForm['csrf_token']);
        $_SESSION['manage_users_old'] = $oldForm;
        flash_add('error', user_save_error($e, 'Could not create user.'));
    }

    redirect('./manage-users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    verify_csrf_or_die();

    $id = (int)($_POST['id'] ?? 0);
    $role = (string)($_POST['role'] ?? '');

    try {
        if (!in_array($role, $allowedRoles, true)) {
            throw new InvalidArgumentException('Invalid user role.');
        }
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid user.');
        }

        users_update($id, $_POST);
        flash_add('success', 'User updated.');
    } catch (Throwable $e) {
        flash_add('error', user_save_error($e, 'Could not update user.'));
        if ($id > 0) {
            redirect('./manage-users.php?edit=' . $id);
        }
    }

    redirect('./manage-users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verify_csrf_or_die();

    $delId = (int)($_POST['id'] ?? 0);

    if ($delId === (int)(current_user()['id'] ?? 0)) {
        flash_add('error', 'You cannot delete your own account.');
    } elseif ($delId > 0) {
        users_delete($delId);
        flash_add('success', 'User deleted.');
    }

    redirect('./manage-users.php');
}

$p = get_pagination_params(10);
$data = users_list($p['q'], $p['limit'], $p['offset']);
$rows = $data['rows'];
$pages = (int)ceil(max(1, (int)$data['total']) / $p['per']);
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = $editId ? users_get($editId) : null;
$doctors = doctors_options_with_users();
$patients = patients_options_with_users();
$formRole = $editRow['role'] ?? ($oldForm['role'] ?? ROLE_PATIENT);
$isEditing = (bool)$editRow;

$val = static function (string $key, $default = '') use ($editRow, $oldForm, $isEditing): string {
    $value = $isEditing ? ($editRow[$key] ?? $default) : ($oldForm[$key] ?? $default);
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | MediFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="md:ml-64 min-h-screen p-4 sm:p-6 lg:p-8">
        <div class="mx-auto max-w-7xl">
            <header class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">Administration</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-900 sm:text-3xl">User Management</h1>
                    <p class="mt-2 text-sm text-slate-500">Create accounts, assign roles and manage profile links.</p>
                </div>
                <a href="./index.php" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">← Back to Dashboard</a>
            </header>

            <?php render_flash(); ?>

            <section class="mb-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900"><?= $isEditing ? 'Edit User Account' : 'Create New User' ?></h2>
                        <p class="mt-1 text-sm text-slate-500">Enter account details and choose the appropriate role.</p>
                    </div>
                    <?php if ($isEditing): ?>
                        <a href="./manage-users.php" class="text-sm font-semibold text-blue-600 hover:text-blue-700">+ Create another user</a>
                    <?php else: ?>
                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">Admin access</span>
                    <?php endif; ?>
                </div>

                <form method="post" class="space-y-6 p-5 sm:p-6">
                    <input type="hidden" name="action" value="<?= $isEditing ? 'update' : 'create' ?>">
                    <?php if ($isEditing): ?>
                        <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
                    <?php endif; ?>

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <label for="username" class="mb-2 block text-sm font-medium text-slate-700">Username</label>
                            <input id="username" name="username" type="text" required value="<?= $val('username') ?>" autocomplete="username" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        </div>
                        <div>
                            <label for="email" class="mb-2 block text-sm font-medium text-slate-700">Email Address</label>
                            <input id="email" name="email" type="email" required value="<?= $val('email') ?>" autocomplete="email" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        </div>
                        <div>
                            <label for="password" class="mb-2 block text-sm font-medium text-slate-700">Password <?= $isEditing ? '<span class="font-normal text-slate-400">(leave blank to keep current)</span>' : '' ?></label>
                            <input id="password" name="password" type="password" <?= $isEditing ? '' : 'required' ?> autocomplete="new-password" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        </div>
                        <div>
                            <label for="role-select" class="mb-2 block text-sm font-medium text-slate-700">Account Role</label>
                            <select name="role" id="role-select" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                <?php foreach ([ROLE_PATIENT, ROLE_DOCTOR, ROLE_ADMIN] as $r): ?>
                                    <option value="<?= htmlspecialchars($r, ENT_QUOTES, 'UTF-8') ?>" <?= $formRole === $r ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($r)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <?php if ($isEditing): ?>
                        <div id="wrap-doctor" class="rounded-xl border border-slate-200 bg-slate-50 p-4 <?= $formRole === ROLE_DOCTOR ? '' : 'hidden' ?>">
                            <h3 class="mb-3 font-semibold text-slate-800">Linked Doctor Profile</h3>
                            <label for="linked-doctor" class="mb-2 block text-sm font-medium text-slate-700">Doctor (optional)</label>
                            <select id="linked-doctor" name="linked_doctor_id" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                <option value="">-- No linked doctor --</option>
                                <?php foreach ($doctors as $d): ?>
                                    <?php $takenByOther = $d['user_id'] !== null && (int)$d['user_id'] !== (int)$editRow['id']; ?>
                                    <option value="<?= (int)$d['id'] ?>" <?= (int)($editRow['linked_doctor_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?> <?= $takenByOther ? 'disabled' : '' ?>><?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="wrap-patient" class="rounded-xl border border-slate-200 bg-slate-50 p-4 <?= $formRole === ROLE_PATIENT ? '' : 'hidden' ?>">
                            <h3 class="mb-3 font-semibold text-slate-800">Linked Patient Profile</h3>
                            <label for="linked-patient" class="mb-2 block text-sm font-medium text-slate-700">Patient (optional)</label>
                            <select id="linked-patient" name="linked_patient_id" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                <option value="">-- No linked patient --</option>
                                <?php foreach ($patients as $pt): ?>
                                    <?php $takenByOther = $pt['user_id'] !== null && (int)$pt['user_id'] !== (int)$editRow['id']; ?>
                                    <option value="<?= (int)$pt['id'] ?>" <?= (int)($editRow['linked_patient_id'] ?? 0) === (int)$pt['id'] ? 'selected' : '' ?> <?= $takenByOther ? 'disabled' : '' ?>><?= htmlspecialchars($pt['first_name'] . ' ' . $pt['last_name'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="wrap-admin" class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 <?= $formRole === ROLE_ADMIN ? '' : 'hidden' ?>">Admin accounts do not require a linked doctor or patient profile.</div>
                    <?php else: ?>
                        <div id="wrap-doctor" class="space-y-4 rounded-xl border border-sky-100 bg-sky-50/60 p-4 <?= $formRole === ROLE_DOCTOR ? '' : 'hidden' ?>">
                            <div><h3 class="font-semibold text-slate-900">Doctor Profile Information</h3><p class="mt-1 text-xs text-slate-500">These details will be used to create the doctor profile.</p></div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <input name="first_name" value="<?= $val('first_name') ?>" placeholder="First Name" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                <input name="last_name" value="<?= $val('last_name') ?>" placeholder="Last Name" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                <input name="phone" value="<?= $val('phone') ?>" placeholder="Phone" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                <input name="specialization" value="<?= $val('specialization') ?>" placeholder="Specialization" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                <input name="department_id" value="<?= $val('department_id') ?>" type="number" min="1" placeholder="Department ID (optional)" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                            </div>
                        </div>
                        <div id="wrap-patient" class="space-y-4 rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 <?= $formRole === ROLE_PATIENT ? '' : 'hidden' ?>">
                            <div><h3 class="font-semibold text-slate-900">Patient Profile Information</h3><p class="mt-1 text-xs text-slate-500">These details will be used to create the patient profile.</p></div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <input name="first_name" value="<?= $val('first_name') ?>" placeholder="First Name" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                <input name="last_name" value="<?= $val('last_name') ?>" placeholder="Last Name" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                <select name="gender" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                    <option value="">Select Gender</option>
                                    <?php foreach (['Male', 'Female', 'Other'] as $gender): ?><option value="<?= $gender ?>" <?= ($oldForm['gender'] ?? '') === $gender ? 'selected' : '' ?>><?= $gender ?></option><?php endforeach; ?>
                                </select>
                                <input name="birthdate" value="<?= $val('birthdate') ?>" type="date" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                <input name="phone" value="<?= $val('phone') ?>" placeholder="Phone" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                <input name="address" value="<?= $val('address') ?>" placeholder="Address" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                            </div>
                        </div>
                        <div id="wrap-admin" class="rounded-xl border border-violet-100 bg-violet-50 p-4 text-sm text-slate-600 <?= $formRole === ROLE_ADMIN ? '' : 'hidden' ?>">Admin accounts do not need a linked doctor or patient profile.</div>
                    <?php endif; ?>

                    <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"><?= $isEditing ? 'Save Changes' : 'Create User' ?></button>
                        <?php if ($isEditing): ?><a href="./manage-users.php" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</a><?php endif; ?>
                    </div>
                </form>
            </section>

            <section>
                <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div><p class="text-sm font-semibold uppercase tracking-wider text-blue-600">Accounts Directory</p><h2 class="mt-1 text-xl font-bold text-slate-900">All Users</h2><p class="mt-1 text-sm text-slate-500">Search accounts and manage their access.</p></div>
                    <form method="get" class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                        <input type="text" name="q" value="<?= htmlspecialchars($p['q'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Search username or email..." class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 sm:w-72">
                        <button type="submit" class="rounded-lg bg-slate-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-900">Search</button>
                        <?php if ($p['q'] !== ''): ?><a href="./manage-users.php" class="rounded-lg border border-slate-300 px-4 py-2.5 text-center text-sm font-semibold text-slate-600 hover:bg-white">Reset</a><?php endif; ?>
                    </form>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[900px] text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-5 py-4">ID</th><th class="px-5 py-4">Account</th><th class="px-5 py-4">Email</th><th class="px-5 py-4">Role</th><th class="px-5 py-4">Linked Doctor</th><th class="px-5 py-4">Linked Patient</th><th class="px-5 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($rows as $r): ?>
                                    <?php
                                    $rowRole = (string)($r['role'] ?? ROLE_PATIENT);
                                    $roleClass = $rowRole === ROLE_ADMIN
                                        ? 'bg-violet-50 text-violet-700 ring-violet-200'
                                        : ($rowRole === ROLE_DOCTOR ? 'bg-sky-50 text-sky-700 ring-sky-200' : 'bg-emerald-50 text-emerald-700 ring-emerald-200');
                                    $doctorName = trim(($r['doctor_first'] ?? '') . ' ' . ($r['doctor_last'] ?? ''));
                                    $patientName = trim(($r['patient_first'] ?? '') . ' ' . ($r['patient_last'] ?? ''));
                                    ?>
                                    <tr class="hover:bg-slate-50/80">
                                        <td class="whitespace-nowrap px-5 py-4 font-medium text-slate-500">#<?= (int)$r['id'] ?></td>
                                        <td class="px-5 py-4"><div class="font-semibold text-slate-900"><?= htmlspecialchars($r['username'], ENT_QUOTES, 'UTF-8') ?></div></td>
                                        <td class="px-5 py-4 text-slate-600"><?= htmlspecialchars($r['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="px-5 py-4"><span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset <?= $roleClass ?>"><?= htmlspecialchars(ucfirst($rowRole), ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td class="px-5 py-4 text-slate-600"><?= $r['linked_doctor_id'] !== null ? htmlspecialchars(($doctorName !== '' ? $doctorName : 'Doctor') . ' (#' . (int)$r['linked_doctor_id'] . ')', ENT_QUOTES, 'UTF-8') : '<span class="text-slate-400">Not linked</span>' ?></td>
                                        <td class="px-5 py-4 text-slate-600"><?= $r['linked_patient_id'] !== null ? htmlspecialchars(($patientName !== '' ? $patientName : 'Patient') . ' (#' . (int)$r['linked_patient_id'] . ')', ENT_QUOTES, 'UTF-8') : '<span class="text-slate-400">Not linked</span>' ?></td>
                                        <td class="whitespace-nowrap px-5 py-4 text-right">
                                            <a href="?edit=<?= (int)$r['id'] ?>" class="inline-flex rounded-lg border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-50">Edit</a>
                                            <form method="post" class="ml-1 inline" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" class="inline-flex rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$rows): ?><tr><td colspan="7" class="px-5 py-12 text-center text-slate-500">No user accounts found.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($pages > 1): ?>
                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4">
                            <p class="text-xs text-slate-500">Page <?= (int)$p['page'] ?> of <?= $pages ?></p>
                            <nav aria-label="User pagination" class="flex flex-wrap gap-2">
                                <?php for ($i = 1; $i <= $pages; $i++): ?>
                                    <a href="?page=<?= $i ?>&per=<?= (int)$p['per'] ?>&q=<?= urlencode($p['q']) ?>" class="rounded-lg border px-3 py-2 text-sm font-medium <?= $i === (int)$p['page'] ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' ?>"><?= $i ?></a>
                                <?php endfor; ?>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <footer class="mt-10 border-t border-slate-200 pt-5 text-center text-xs text-slate-400">MediFlow Hospital Management System</footer>
        </div>
    </main>

    <script>
    (function () {
        const roleSelect = document.getElementById('role-select');
        if (!roleSelect) return;

        const sections = {
            doctor: document.getElementById('wrap-doctor'),
            patient: document.getElementById('wrap-patient'),
            admin: document.getElementById('wrap-admin')
        };

        function syncRoleFields() {
            Object.keys(sections).forEach(function (role) {
                const section = sections[role];
                if (!section) return;
                const active = roleSelect.value === role;
                section.classList.toggle('hidden', !active);
                section.querySelectorAll('input, select, textarea').forEach(function (field) {
                    field.disabled = !active;
                });
            });
        }

        roleSelect.addEventListener('change', syncRoleFields);
        syncRoleFields();
    })();
    </script>
</body>
</html>
