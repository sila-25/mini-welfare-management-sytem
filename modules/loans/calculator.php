<?php
require_once dirname(__DIR__) . '/../includes/config.php';
require_once dirname(__DIR__) . '/../includes/db.php';
require_once dirname(__DIR__) . '/../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/pages/login.php');
    exit();
}

$page_title = 'Loan Calculator';
$current_page = 'loans';

$groupId = $_SESSION['group_id'];

// Get group loan settings
$interest_rate_monthly = getGroupSetting($groupId, 'interest_rate_monthly', 1);
$min_loan_amount = getGroupSetting($groupId, 'min_loan_amount', 1000);
$max_loan_amount = getGroupSetting($groupId, 'max_loan_amount', 100000);
$processing_fee = getGroupSetting($groupId, 'processing_fee', 0);
$min_duration = getGroupSetting($groupId, 'min_loan_duration', 1);
$max_duration = getGroupSetting($groupId, 'max_loan_duration', 12);
$max_ratio = getGroupSetting($groupId, 'max_loan_ratio', 3);

// Get member's total contributions for max loan calculation
$member_total_contributions = 0;
$max_eligible = 0;

try {
    $stmt = db()->prepare("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM contributions 
        WHERE member_id = (SELECT id FROM members WHERE user_id = ? AND group_id = ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $groupId]);
    $member_total_contributions = $stmt->fetch()['total'];
    $max_eligible = $member_total_contributions * $max_ratio;
} catch (Exception $e) {
    $member_total_contributions = 0;
    $max_eligible = $max_loan_amount;
}

$absolute_max = min($max_loan_amount, $max_eligible);
$absolute_min = $min_loan_amount;

include_once dirname(__DIR__) . '/../templates/header.php';
include_once dirname(__DIR__) . '/../templates/sidebar.php';
include_once dirname(__DIR__) . '/../templates/topbar.php';
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="index.php">Loans</a></li>
                        <li class="breadcrumb-item active">Calculator</li>
                    </ol>
                </nav>
                <h1 class="h3 mb-0">
                    <i class="fas fa-calculator me-2 text-primary"></i>Loan Calculator
                </h1>
                <p class="text-muted mt-2">Calculate your loan repayments</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Loans
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-3 pb-0">
                        <h5 class="mb-0"><i class="fas fa-sliders-h me-2 text-primary"></i>Loan Parameters</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Group Loan Policies:</strong><br>
                            Interest Rate: <?php echo $interest_rate_monthly; ?>% per month<br>
                            Processing Fee: <?php echo formatCurrency($processing_fee); ?><br>
                            Loan Range: <?php echo formatCurrency($min_loan_amount); ?> - <?php echo formatCurrency($max_loan_amount); ?><br>
                            Loan Period: <?php echo $min_duration; ?> - <?php echo $max_duration; ?> months<br>
                            Maximum: Up to <?php echo $max_ratio; ?>x your contributions (<?php echo formatCurrency($max_eligible); ?>)
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Loan Amount (KES)</label>
                            <div class="input-group">
                                <span class="input-group-text">KES</span>
                                <input type="number" class="form-control" id="amount" 
                                       value="<?php echo $min_loan_amount; ?>" 
                                       step="100" 
                                       min="<?php echo $min_loan_amount; ?>" 
                                       max="<?php echo $absolute_max; ?>" 
                                       oninput="calculateLoan()">
                            </div>
                            <div class="form-text">
                                Min: <?php echo formatCurrency($min_loan_amount); ?> | 
                                Max: <?php echo formatCurrency($absolute_max); ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Repayment Period (Months)</label>
                            <select class="form-select" id="months" onchange="calculateLoan()">
                                <?php for ($i = $min_duration; $i <= $max_duration; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i == 12 ? 'selected' : ''; ?>>
                                        <?php echo $i; ?> month<?php echo $i > 1 ? 's' : ''; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-3 pb-0">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2 text-success"></i>Loan Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="result-card p-3 bg-light rounded mb-3">
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Principal Amount</small>
                                    <h4 id="principalDisplay" class="mb-0">KES 0</h4>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Processing Fee</small>
                                    <h4 id="feeDisplay" class="mb-0 text-warning">KES 0</h4>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Total Interest (<?php echo $interest_rate_monthly; ?>% monthly)</small>
                                    <h4 id="interestDisplay" class="mb-0 text-danger">KES 0</h4>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Total Repayable</small>
                                    <h4 id="totalDisplay" class="mb-0 text-primary fw-bold">KES 0</h4>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <small class="text-muted">Monthly Installment</small>
                                    <h3 id="monthlyDisplay" class="mb-0 text-success">KES 0</h3>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <h6 class="mb-3"><i class="fas fa-table me-2"></i>Repayment Schedule</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered" id="scheduleTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Month</th>
                                            <th>Principal Due</th>
                                            <th>Interest Due</th>
                                            <th>Total Due</th>
                                            <th>Balance After</th>
                                        </tr>
                                    </thead>
                                    <tbody id="scheduleBody">
                                        <tr>
                                            <td colspan="5" class="text-center">Enter loan details to see schedule</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="mt-4">
                            <a href="apply.php" class="btn btn-primary w-100" id="applyBtn">
                                <i class="fas fa-paper-plane me-2"></i>Apply for This Loan
                            </a>
                        </div>
                        
                        <div class="mt-3 text-center">
                            <a href="index.php" class="btn btn-link">
                                <i class="fas fa-arrow-left me-1"></i> Back to Loans Page
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const monthlyInterestRate = <?php echo $interest_rate_monthly; ?>;
const processingFee = <?php echo $processing_fee; ?>;
const minAmount = <?php echo $min_loan_amount; ?>;
const maxAmount = <?php echo $absolute_max; ?>;

function calculateLoan() {
    let amount = parseFloat(document.getElementById('amount').value) || 0;
    let months = parseInt(document.getElementById('months').value) || 12;
    
    if (amount < minAmount) {
        amount = minAmount;
        document.getElementById('amount').value = minAmount;
    }
    if (amount > maxAmount) {
        amount = maxAmount;
        document.getElementById('amount').value = maxAmount;
    }
    
    const totalInterest = amount * (monthlyInterestRate / 100) * months;
    const totalRepayable = amount + totalInterest + processingFee;
    const monthlyInstallment = months > 0 ? totalRepayable / months : 0;
    
    document.getElementById('principalDisplay').innerHTML = 'KES ' + amount.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('feeDisplay').innerHTML = 'KES ' + processingFee.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('interestDisplay').innerHTML = 'KES ' + totalInterest.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('totalDisplay').innerHTML = 'KES ' + totalRepayable.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('monthlyDisplay').innerHTML = 'KES ' + monthlyInstallment.toLocaleString(undefined, {minimumFractionDigits: 2});
    
    const applyBtn = document.getElementById('applyBtn');
    applyBtn.href = 'apply.php?amount=' + amount + '&months=' + months;
    
    generateSchedule(amount, months, totalInterest, monthlyInstallment);
}

function generateSchedule(principal, months, totalInterest, monthlyInstallment) {
    const tbody = document.getElementById('scheduleBody');
    tbody.innerHTML = '';
    
    if (principal <= 0 || months <= 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Enter valid loan details</td></tr>';
        return;
    }
    
    let remainingPrincipal = principal;
    let remainingInterest = totalInterest;
    let balance = principal + totalInterest + processingFee;
    
    for (let i = 1; i <= months; i++) {
        let interestDue = remainingPrincipal * (monthlyInterestRate / 100);
        let principalDue = monthlyInstallment - interestDue;
        
        if (i === months) {
            principalDue = remainingPrincipal;
            interestDue = remainingInterest;
        }
        
        principalDue = Math.max(0, principalDue);
        interestDue = Math.max(0, interestDue);
        
        const totalDue = principalDue + interestDue;
        balance -= totalDue;
        if (balance < 0) balance = 0;
        
        const row = `
            <tr>
                <td class="text-center">${i}</td>
                <td class="text-end">KES ${principalDue.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                <td class="text-end">KES ${interestDue.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                <td class="text-end">KES ${totalDue.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                <td class="text-end">KES ${balance.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
            </tr>
        `;
        tbody.innerHTML += row;
        
        remainingPrincipal -= principalDue;
        remainingInterest -= interestDue;
        if (remainingPrincipal < 0) remainingPrincipal = 0;
        if (remainingInterest < 0) remainingInterest = 0;
    }
}

calculateLoan();
</script>

<style>
.result-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 12px;
}
.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102,126,234,0.25);
}
.table-sm td, .table-sm th {
    padding: 0.5rem;
    font-size: 0.85rem;
}
.btn-link {
    text-decoration: none;
}
.btn-link:hover {
    text-decoration: underline;
}
</style>

<?php include_once dirname(__DIR__) . '/../templates/footer.php'; ?>