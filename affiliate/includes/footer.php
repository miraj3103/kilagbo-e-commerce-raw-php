        </div>
        
        <?php include_once __DIR__ . '/../../includes/floating-contact.php'; ?>
        
        <!-- Footer -->
        <footer class="affiliate-footer">
            <p class="mb-0">
                &copy; <?php echo date('Y'); ?> <?php echo getSettings('site_title') ?: 'কিলাগ্বো'; ?> অ্যাফিলিয়েট প্রোগ্রাম | 
                ডেভেলপড বাই <a href="https://webnify.com.bd" target="_blank">Webnify Bangladesh</a>
            </p>
        </footer>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('affiliateSidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }
        
        // Copy to clipboard function
        function copyToClipboard(text, button) {
            navigator.clipboard.writeText(text).then(function() {
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check-lg"></i> কপি হয়েছে';
                button.classList.remove('btn-outline-primary');
                button.classList.add('btn-success');
                setTimeout(function() {
                    button.innerHTML = originalText;
                    button.classList.remove('btn-success');
                    button.classList.add('btn-outline-primary');
                }, 2000);
            });
        }
    </script>
</body>
</html>
