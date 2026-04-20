<?php
require_once dirname(__DIR__) . '/../includes/config.php';
require_once dirname(__DIR__) . '/../includes/db.php';
require_once dirname(__DIR__) . '/../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/pages/login.php');
    exit();
}

$page_title = 'Apply for Loan';
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

$success = '';
$error = '';

// Get member information
$member = null;
try {
    $stmt = db()->prepare("
        SELECT * FROM members 
        WHERE user_id = ? AND group_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $groupId]);
    $member = $stmt->fetch();
} catch (Exception $e) {
    $member = null;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount'] ?? 0);
    $duration = intval($_POST['duration'] ?? 12);
    $purpose = trim($_POST['purpose'] ?? '');
    
    if ($amount <= 0) {
        $error = 'Please enter a valid loan amount';
    } elseif ($amount < $min_loan_amount) {
        $error = "Minimum loan amount is " . formatCurrency($min_loan_amount);
    } elseif ($amount > $absolute_max) {
        $error = "Maximum loan amount you can apply for is " . formatCurrency($absolute_max);
    } elseif ($duration < $min_duration || $duration > $max_duration) {
        $error = "Loan period must be between {$min_duration} and {$max_duration} months";
    } elseif (empty($purpose)) {
        $error = 'Please select a loan purpose';
    } else {
        try {
            // Calculate using monthly interest rate
            $totalInterest = $amount * ($interest_rate_monthly / 100) * $duration;
            $totalRepayable = $amount + $totalInterest + $processing_fee;
            $monthlyInstallment = $totalRepayable / $duration;
            $loanNumber = 'LN' . date('Y') . rand(10000, 99999);
            
            $stmt = db()->prepare("
                INSERT INTO loans (
                    group_id, member_id, loan_number, principal_amount, interest_rate,
                    total_repayable, duration_months, purpose, processing_fee,
                    status, application_date, recorded_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', CURDATE(), ?)
            ");
            
            $stmt->execute([
                $groupId,
                $member['id'] ?? 1,
                $loanNumber,
                $amount,
                $interest_rate_monthly,
                $totalRepayable,
                $duration,
                $purpose,
                $processing_fee,
                $_SESSION['user_id']
            ]);
            
            $success = "Loan application submitted successfully!<br>
                        Loan Number: <strong>$loanNumber</strong><br>
                        Amount: <strong>" . formatCurrency($amount) . "</strong><br>
                        Repayment Period: <strong>{$duration} months</strong><br>
                        Monthly Installment: <strong>" . formatCurrency($monthlyInstallment) . "</strong>";
            
        } catch (Exception $e) {
            $error = 'Error submitting application: ' . $e->getMessage();
        }
    }
}

include_once dirname(__DIR__) . '/../templates/header.php';
include_once dirname(__DIR__) . '/../templates/sidebar.php';
include_once dirname(__DIR__) . '/../templates/topbar.php';
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-1">
                                <li class="breadcrumb-item"><a href="index.php">Loans</a></li>
                                <li class="breadcrumb-item active">Apply for Loan</li>
                            </ol>
                        </nav>
                        <h1 class="h3 mb-0">Apply for Loan</h1>
                    </div>
                    <a href="index.php" class="btn btn-secondary">Back to Loans</a>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-hand-holding-usd me-2"></i>Loan Application Form</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $success; ?>
                            </div>
                            <div class="mt-3">
                                <a href="index.php" class="btn btn-primary">View My Loans</a>
                                <a href="apply.php" class="btn btn-success ms-2">Apply for Another Loan</a>
                            </div>
                        <?php elseif ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!$success): ?>
                            <!-- Group Loan Policies Info -->
                            <div class="alert alert-info mb-4">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Group Loan Policies:</strong><br>
                                Interest Rate: <?php echo $interest_rate_monthly; ?>% per month<br>
                                Processing Fee: <?php echo formatCurrency($processing_fee); ?><br>
                                Loan Range: <?php echo formatCurrency($min_loan_amount); ?> - <?php echo formatCurrency($max_loan_amount); ?><br>
                                Loan Period: <?php echo $min_duration; ?> - <?php echo $max_duration; ?> months<br>
                                Maximum: Up to <?php echo $max_ratio; ?>x your contributions (<?php echo formatCurrency($max_eligible); ?>)
                            </div>
                            
                            <?php if ($member): ?>
                            <div class="alert alert-secondary mb-4">
                                <strong>Member Information:</strong><br>
                                Name: <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?><br>
                                Member Number: <?php echo htmlspecialchars($member['member_number']); ?><br>
                                Total Contributions: <?php echo formatCurrency($member_total_contributions); ?>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="" id="loanForm">
                                <div class="mb-3">
                                    <label class="form-label">Loan Amount (KES) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="amount" id="amount" 
                                           step="100" min="<?php echo $min_loan_amount; ?>" max="<?php echo $absolute_max; ?>" 
                                           oninput="calculateSummary()" required>
                                    <small class="text-muted">
                                        Min: <?php echo formatCurrency($min_loan_amount); ?> | 
                                        Max: <?php echo formatCurrency($absolute_max); ?>
                                    </small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Repayment Period (Months) <span class="text-danger">*</span></label>
                                    <select class="form-select" name="duration" id="duration" onchange="calculateSummary()" required>
                                        <?php for ($i = $min_duration; $i <= $max_duration; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo $i == 12 ? 'selected' : ''; ?>>
                                                <?php echo $i; ?> month<?php echo $i > 1 ? 's' : ''; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Loan Purpose <span class="text-danger">*</span></label>
                                    <select class="form-select" name="purpose" required>
                                        <option value="">Select Purpose</option>
                                        <option value="Business Capital">Business Capital</option>
                                        <option value="School Fees">School Fees</option>
                                        <option value="Medical Emergency">Medical Emergency</option>
                                        <option value="Home Improvement">Home Improvement</option>
                                        <option value="Vehicle Purchase">Vehicle Purchase</option>
                                        <option value="Land Purchase">Land Purchase</option>
                                        <option value="Debt Consolidation">Debt Consolidation</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                
                                <!-- Loan Summary - Read Only -->
                                <div class="card bg-light mb-4">
                                    <div class="card-body">
                                        <h6 class="card-title">Loan Summary</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <small>Principal Amount:</small>
                                                <h6 id="summaryPrincipal" class="text-primary">KES 0</h6>
                                            </div>
                                            <div class="col-6">
                                                <small>Processing Fee:</small>
                                                <h6 id="summaryFee" class="text-warning">KES 0</h6>
                                            </div>
                                            <div class="col-6">
                                                <small>Total Interest (<?php echo $interest_rate_monthly; ?>% monthly):</small>
                                                <h6 id="summaryInterest" class="text-danger">KES 0</h6>
                                            </div>
                                            <div class="col-6">
                                                <small>Total Repayable:</small>
                                                <h6 id="summaryTotal" class="text-success">KES 0</h6>
                                            </div>
                                            <div class="col-12">
                                                <small>Monthly Installment:</small>
                                                <h4 id="summaryInstallment" class="text-primary">KES 0</h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input type="checkbox" class="form-check-input" id="terms" required>
                                    <label class="form-check-label" for="terms">
                                        I confirm that all information provided is correct and I agree to the loan terms and conditions.
                                    </label>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Application
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const monthlyInterestRate = <?php echo $interest_rate_monthly; ?>;
const processingFee = <?php echo $processing_fee; ?>;

function calculateSummary() {
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    const months = parseInt(document.getElementById('duration').value) || 12;
    
    const totalInterest = amount * (monthlyInterestRate / 100) * months;
    const totalRepayable = amount + totalInterest + processingFee;
    const monthlyInstallment = totalRepayable / months;
    
    document.getElementById('summaryPrincipal').innerHTML = 'KES ' + amount.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('summaryFee').innerHTML = 'KES ' + processingFee.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('summaryInterest').innerHTML = 'KES ' + totalInterest.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('summaryTotal').innerHTML = 'KES ' + totalRepayable.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('summaryInstallment').innerHTML = 'KES ' + monthlyInstallment.toLocaleString(undefined, {minimumFractionDigits: 2});
}

calculateSummary();
</script>

<?php include_once dirname(__DIR__) . '/../templates/footer.php'; ?>