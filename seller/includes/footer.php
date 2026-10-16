        </div>
        
        <?php include_once __DIR__ . '/../../includes/floating-contact.php'; ?>
        
        <!-- Footer -->
        <footer class="seller-footer">
            &copy; <?php echo date('Y'); ?> <?php echo getSettings('site_title') ?: 'কিলাগ্বো'; ?>। সর্বস্বত্ব সংরক্ষিত।
            <br>
            <small>Developed by <a href="https://webnify.com.bd" target="_blank">Webnify Bangladesh</a></small>
        </footer>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sellerSidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }
        
        // Image preview functionality
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" style="max-width: 200px; max-height: 200px; border-radius: 8px; margin-top: 10px;">`;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Gallery preview
        function previewGallery(input, previewId) {
            const preview = document.getElementById(previewId);
            preview.innerHTML = '';
            if (input.files) {
                Array.from(input.files).forEach(file => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.style.cssText = 'width: 80px; height: 80px; object-fit: cover; border-radius: 8px; margin: 5px;';
                        preview.appendChild(img);
                    }
                    reader.readAsDataURL(file);
                });
            }
        }
    </script>
</body>
</html>
