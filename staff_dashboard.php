<?php
/**
 * staff_dashboard.php
 * Takines Labada Hub — Staff Portal
 * Staff can ONLY record loads — no financial reports / inventory / expenses access
 */
require_once __DIR__ . '/config.php';
require_role('staff');

$user = current_user();

// ------------------------------------------------------------
// HANDLE NEW TRANSACTION
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $allowedServices = ['Wash + Dry + Fold', 'Wash + Dry', 'Wash Only'];
    $transactionDate = $_POST['transaction_date'] ?? date('Y-m-d');
    $transactionTime = $_POST['transaction_time'] ?? date('H:i');
    $serviceType     = $_POST['service_type'] ?? '';
    $numLoads        = (int)($_POST['num_loads'] ?? 1);
    $fabricSpray     = isset($_POST['fabric_spray']) && $_POST['fabric_spray'] == '1' ? 1 : 0;
    $amountPaid      = (float)($_POST['amount_paid'] ?? 0);
    $cashOnHand      = (float)($_POST['cash_on_hand'] ?? 0);

    $errors = [];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $transactionDate)) {
        $errors[] = 'A valid transaction date is required.';
    }
    if (!in_array($serviceType, $allowedServices, true)) {
        $errors[] = 'Please select a valid service type.';
    }
    if ($numLoads < 1 || $numLoads > 99) {
        $errors[] = 'Number of loads must be between 1 and 99.';
    }
    if ($amountPaid < 0) {
        $errors[] = 'Amount paid cannot be negative.';
    }

    if ($errors) {
        flash('error', implode(' ', $errors));
    } else {
        $stmt = $pdo->prepare('INSERT INTO sales (transaction_date, transaction_time, service_type, num_loads, fabric_spray, amount_paid, cash_on_hand, recorded_by) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$transactionDate, $transactionTime, $serviceType, $numLoads, $fabricSpray, $amountPaid, $cashOnHand, $user->id]);
        flash('success', 'Transaction recorded successfully.');
    }

    redirect('staff_dashboard.php');
}

// ------------------------------------------------------------
// TODAY'S TRANSACTIONS (recorded by anyone, view-only for staff)
// ------------------------------------------------------------
$stmt = $pdo->prepare("SELECT s.*, u.name AS recorded_by_name
                        FROM sales s
                        LEFT JOIN users u ON u.id = s.recorded_by
                        WHERE s.transaction_date = CURDATE()
                        ORDER BY s.transaction_time DESC");
$stmt->execute();
$todaysTransactions = $stmt->fetchAll();

$todayLoadsTotal = array_sum(array_map(fn($t) => (int)$t->num_loads, $todaysTransactions));

$pageTitle = 'Staff Portal';
require __DIR__ . '/includes/header_staff.php';
?>

<div class="page-content">

    <div class="page-header">
        <div>
            <div class="breadcrumb">
                <span class="current">Staff Portal</span>
            </div>
            <h1 class="page-title">Welcome, <?= h(explode(' ', $user->name)[0]) ?>! 👋</h1>
            <p class="page-subtitle"><?= date('l, F j, Y') ?> — Record laundry loads as they come in</p>
        </div>
        <button class="btn btn-primary" id="btn-add-transaction" aria-haspopup="dialog">
            <i class="ph-bold ph-plus" aria-hidden="true"></i>
            + Record New Load
        </button>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success" role="alert" aria-live="polite">
            <i class="ph-bold ph-check-circle" aria-hidden="true"></i> <?= h($msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger" role="alert" aria-live="polite">
            <i class="ph-bold ph-warning" aria-hidden="true"></i> <?= h($msg) ?>
        </div>
    <?php endif; ?>

    <!-- Simple stat: today's loads recorded -->
    <div class="grid-2 mb-lg">
        <div class="stat-card green">
            <div class="stat-card-label">Today's Transactions</div>
            <div class="stat-card-value"><?= count($todaysTransactions) ?></div>
            <div class="stat-card-sub"><?= date('F j, Y') ?></div>
        </div>
        <div class="stat-card blue">
            <div class="stat-card-label">Today's Loads</div>
            <div class="stat-card-value"><?= (int)$todayLoadsTotal ?></div>
            <div class="stat-card-sub"><?= (int)$todayLoadsTotal * 7 ?> kg total</div>
        </div>
    </div>

    <!-- Today's transactions table (no financial totals shown) -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Today's Recorded Loads</span>
        </div>
        <div class="table-container" style="border:none; border-radius:0;">
            <table class="data-table" aria-label="Today's transactions">
                <thead>
                    <tr>
                        <th scope="col">Time</th>
                        <th scope="col">Service</th>
                        <th scope="col">Loads (7kg)</th>
                        <th scope="col">Fabric Spray</th>
                        <th scope="col">Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($todaysTransactions)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding: var(--spacing-lg); color:var(--color-text-muted);">
                                No loads recorded yet today.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $svcColors = [
                            'Wash + Dry + Fold' => 'green',
                            'Wash + Dry'        => 'blue',
                            'Wash Only'         => 'orange',
                        ];
                        ?>
                        <?php foreach ($todaysTransactions as $tx): ?>
                            <?php $color = $svcColors[$tx->service_type] ?? 'gray'; ?>
                            <tr>
                                <td><?= date('h:i A', strtotime($tx->transaction_time)) ?></td>
                                <td><span class="badge badge-<?= $color ?>"><?= h($tx->service_type) ?></span></td>
                                <td>
                                    <?= (int)$tx->num_loads ?> load<?= $tx->num_loads > 1 ? 's' : '' ?>
                                    <span class="text-muted text-sm">(<?= (int)$tx->num_loads * 7 ?> kg)</span>
                                </td>
                                <td>
                                    <span class="badge <?= $tx->fabric_spray ? 'badge-teal' : 'badge-gray' ?>">
                                        <?= $tx->fabric_spray ? 'Yes' : 'No' ?>
                                    </span>
                                </td>
                                <td><span class="badge badge-gray"><?= h($tx->recorded_by_name ?? 'Unknown') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>


<!-- ADD TRANSACTION MODAL -->
<div class="modal-overlay" id="modal-add-transaction" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal-dialog">

        <div class="modal-header">
            <div class="modal-title" id="modal-title">
                <span aria-hidden="true">🧺</span>
                Record New Load
            </div>
            <button class="modal-close" id="btn-close-modal" aria-label="Close modal">
                <i class="ph-bold ph-x" aria-hidden="true"></i>
            </button>
        </div>

        <form method="POST" action="staff_dashboard.php" id="form-add-transaction" novalidate>
            <?= csrf_field() ?>

            <div class="modal-body">

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="tx-date">Transaction Date</label>
                        <input type="date" id="tx-date" name="transaction_date" class="form-control"
                               value="<?= date('Y-m-d') ?>" required aria-required="true">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="tx-time">Time</label>
                        <input type="time" id="tx-time" name="transaction_time" class="form-control"
                               value="<?= date('H:i') ?>" required aria-required="true">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Service Type</label>
                    <div class="toggle-group" role="group" aria-label="Select service type">
                        <button type="button" class="toggle-btn active" data-value="Wash + Dry + Fold" onclick="selectService(this)">
                            Wash + Dry + Fold
                        </button>
                        <button type="button" class="toggle-btn" data-value="Wash + Dry" onclick="selectService(this)">
                            Wash + Dry
                        </button>
                        <button type="button" class="toggle-btn" data-value="Wash Only" onclick="selectService(this)">
                            Wash Only
                        </button>
                    </div>
                    <input type="hidden" name="service_type" id="service-type-input" value="Wash + Dry + Fold">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:var(--spacing-md);">
                    <div class="form-group">
                        <label class="form-label" for="tx-loads">No. of Loads</label>
                        <input type="number" id="tx-loads" name="num_loads" class="form-control"
                               value="1" min="1" max="99" required aria-required="true" oninput="recalculate()">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="tx-amount">Amount Paid (₱)</label>
                        <input type="number" id="tx-amount" name="amount_paid" class="form-control"
                               value="0" min="0" step="0.01" required aria-required="true" oninput="recalculate()">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="tx-cash">Cash on Hand (₱)</label>
                        <input type="number" id="tx-cash" name="cash_on_hand" class="form-control"
                               value="0" min="0" step="0.01" required aria-required="true">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="tx-spray">Fabric Spray Used</label>
                    <select id="tx-spray" name="fabric_spray" class="form-control">
                        <option value="0">No — without spray</option>
                        <option value="1">Yes — with spray</option>
                    </select>
                </div>

                <div style="background:var(--color-info-bg); border-radius:var(--radius-md);
                            padding: 12px var(--spacing-md);
                            display:flex; justify-content:space-between; align-items:center;
                            font-size:13px; font-weight:600;">
                    <span>
                        Total Weight:
                        <strong id="summary-weight">7 kg</strong>
                        <span id="summary-loads-text" style="color:var(--color-text-muted);">(1 load &times; 7 kg)</span>
                    </span>
                    <span style="color:var(--color-primary);">
                        Amount: <strong id="summary-amount">₱0.00</strong>
                    </span>
                </div>

            </div><!-- /.modal-body -->

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="btn-cancel-modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="ph-bold ph-check" aria-hidden="true"></i>
                    Save Transaction
                </button>
            </div>

        </form>
    </div>
</div><!-- /.modal-overlay -->

<?php
$pageScripts = <<<'HTML'
<script>
    const modal     = document.getElementById('modal-add-transaction');
    const btnOpen   = document.getElementById('btn-add-transaction');
    const btnClose  = document.getElementById('btn-close-modal');
    const btnCancel = document.getElementById('btn-cancel-modal');

    function openModal()  { modal.style.display = 'flex'; document.getElementById('tx-date').focus(); }
    function closeModal() { modal.style.display = 'none'; }

    btnOpen.addEventListener('click', openModal);
    btnClose.addEventListener('click', closeModal);
    btnCancel.addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

    function selectService(btn) {
        document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('service-type-input').value = btn.dataset.value;
    }

    function recalculate() {
        const loads  = parseInt(document.getElementById('tx-loads').value) || 0;
        const amount = parseFloat(document.getElementById('tx-amount').value) || 0;
        const weight = loads * 7;

        document.getElementById('summary-weight').textContent = weight + ' kg';
        document.getElementById('summary-loads-text').textContent =
            `(${loads} load${loads !== 1 ? 's' : ''} \u00d7 7 kg)`;
        document.getElementById('summary-amount').textContent =
            '\u20b1' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
</script>
HTML;
require __DIR__ . '/includes/footer_staff.php';
