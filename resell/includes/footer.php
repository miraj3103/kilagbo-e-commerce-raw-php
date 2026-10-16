    </div><!-- content-area -->
</main>

<?php include_once __DIR__ . '/../../includes/floating-contact.php'; ?>

<!-- Minimum Balance Modal -->
<div class="modal fade" id="minBalanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-body text-center p-5">
                <div class="mb-4">
                    <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 4rem;"></i>
                </div>
                <h4 class="fw-bold mb-3">ব্যালেন্স নূন্যতম <?php echo number_format($minBalance); ?> টাকা প্রয়োজন!</h4>
                <p class="text-muted mb-4">রিসেলার অর্ডার করতে হলে আপনার মেইন ব্যালেন্স নূন্যতম <?php echo number_format($minBalance); ?> টাকা থাকা বাধ্যতামূলক। বর্তমানে আপনার পর্যাপ্ত ব্যালেন্স নেই। অর্ডার করতে দয়া করে ক্যাশব্যালেন্স এড করুন।</p>
                <div class="d-grid gap-2">
                    <a href="<?php echo baseUrl('resell/add-balance.php'); ?>" class="btn btn-primary py-3 fw-bold" style="border-radius: 10px; background: linear-gradient(135deg, #667eea, #764ba2); border: none;">ব্যালেন্স এড করুন</a>
                    <button type="button" class="btn btn-light py-3 fw-bold mt-2" data-bs-dismiss="modal" style="border-radius: 10px;">বন্ধ করুন</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}

function showToast(title, message, type = 'success') {
    const toastHtml = `
        <div class="toast align-items-center text-white bg-${type === 'error' ? 'danger' : 'success'} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <strong>${title}</strong>: ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(container);
    }
    
    container.insertAdjacentHTML('beforeend', toastHtml);
    const toast = new bootstrap.Toast(container.lastElementChild);
    toast.show();
}
</script>
</body>
</html>
