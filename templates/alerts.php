<?php
// Display flash messages
$flash = getFlash();
if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show shadow-sm" role="alert" style="border-radius: 12px;">
        <div class="d-flex align-items-center">
            <div class="me-3">
                <?php if ($flash['type'] === 'success'): ?>
                    <i class="fas fa-check-circle fa-2x"></i>
                <?php elseif ($flash['type'] === 'danger'): ?>
                    <i class="fas fa-exclamation-circle fa-2x"></i>
                <?php elseif ($flash['type'] === 'warning'): ?>
                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                <?php else: ?>
                    <i class="fas fa-info-circle fa-2x"></i>
                <?php endif; ?>
            </div>
            <div class="flex-grow-1">
                <?php echo $flash['message']; ?>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Auto-hide alerts after 5 seconds -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('fade');
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 300);
        }, 5000);
    });
});
</script>

<style>
.alert {
    border: none;
    animation: slideInRight 0.3s ease-out;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.alert.fade {
    opacity: 0;
    transition: opacity 0.3s ease-out;
}
</style>