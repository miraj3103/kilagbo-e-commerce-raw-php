        </div>
        
        <!-- Footer -->
        <footer class="admin-footer">
            Developed by <a href="https://www.webnifybd.com" target="_blank">Webnify Bangladesh</a>
        </footer>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Universal Image Preview Styles -->
    <style>
        .image-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
            margin-bottom: 10px;
        }
        
        .image-preview-item {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #e0e0e0;
        }
        
        .image-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .image-preview-item .delete-btn {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 24px;
            height: 24px;
            background: rgba(220, 53, 69, 0.9);
            color: #fff;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: all 0.2s;
            z-index: 10;
        }
        
        .image-preview-item .delete-btn:hover {
            background: #dc3545;
            transform: scale(1.1);
        }
        
        .image-preview-item.main-image {
            border-color: #28a745;
        }
        
        .image-preview-item.main-image::after {
            content: 'প্রধান';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(40, 167, 69, 0.9);
            color: #fff;
            font-size: 10px;
            text-align: center;
            padding: 2px;
        }
    </style>
    
    <script>
        function toggleSidebar() {
            document.getElementById('adminSidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }
        
        // Auto dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(function(alert) {
                new bootstrap.Alert(alert).close();
            });
        }, 5000);
        
        // ========== Notification Management ==========
        
        // Mark notifications as read when clicking bell icon
        function markNotificationsRead() {
            const badge = document.getElementById('notifBadge');
            if (badge) {
                badge.style.display = 'none';
            }
            // AJAX call to mark as read
            fetch('<?php echo adminUrl("ajax/mark-notifications-read"); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'}
            });
        }
        
        // Clear all notifications
        function clearAllNotifications() {
            if (!confirm('সব নোটিফিকেশন মুছে ফেলতে চান?')) return;
            
            fetch('<?php echo adminUrl("ajax/clear-notifications"); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'}
            }).then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('notificationList').innerHTML = 
                        '<div class="p-3 text-center text-muted">কোন নোটিফিকেশন নেই</div>';
                    const badge = document.getElementById('notifBadge');
                    if (badge) badge.style.display = 'none';
                    // Hide clear button
                    document.querySelector('.notifications-dropdown .btn-outline-danger')?.remove();
                }
            });
        }
        
        // Dismiss order badges when clicking on order menu
        document.addEventListener('DOMContentLoaded', function() {
            // Retail orders
            const retailOrderLink = document.querySelector('a[href*="orders"]');
            if (retailOrderLink) {
                retailOrderLink.addEventListener('click', function() {
                    const badge = this.querySelector('.order-badge');
                    if (badge) badge.style.display = 'none';
                });
            }
            
            // Service orders
            const serviceOrderLink = document.querySelector('a[href*="service-orders"]');
            if (serviceOrderLink) {
                serviceOrderLink.addEventListener('click', function() {
                    const badge = this.querySelector('.order-badge');
                    if (badge) badge.style.display = 'none';
                });
            }
            
            // Digital orders
            const digitalOrderLink = document.querySelector('a[href*="digital-orders"]');
            if (digitalOrderLink) {
                digitalOrderLink.addEventListener('click', function() {
                    const badge = this.querySelector('.order-badge');
                    if (badge) badge.style.display = 'none';
                });
            }
            
            // Affiliate section - hide badges when clicking on menu items
            // Affiliators menu
            const affiliatorsLink = document.querySelector('a[href*="affiliators"]');
            if (affiliatorsLink) {
                affiliatorsLink.addEventListener('click', function() {
                    const badge = this.querySelector('.menu-badge');
                    if (badge) badge.style.display = 'none';
                    updateAffiliateParentBadge();
                });
            }
            
            // Affiliate orders menu
            const affiliateOrdersLink = document.querySelector('a[href*="affiliate-orders"]');
            if (affiliateOrdersLink) {
                affiliateOrdersLink.addEventListener('click', function() {
                    const badge = this.querySelector('.menu-badge');
                    if (badge) badge.style.display = 'none';
                    updateAffiliateParentBadge();
                });
            }
            
            // Affiliate payments menu
            const affiliatePaymentsLink = document.querySelector('a[href*="affiliate-payments"]');
            if (affiliatePaymentsLink) {
                affiliatePaymentsLink.addEventListener('click', function() {
                    const badge = this.querySelector('.menu-badge');
                    if (badge) badge.style.display = 'none';
                    updateAffiliateParentBadge();
                });
            }
            
            // Function to update parent affiliate menu badge
            function updateAffiliateParentBadge() {
                const parentLink = document.querySelector('a[href="#affiliateSubmenu"]');
                if (parentLink) {
                    const parentBadge = parentLink.querySelector('.menu-badge');
                    if (parentBadge) {
                        // Count remaining visible badges in submenu
                        const submenu = document.getElementById('affiliateSubmenu');
                        if (submenu) {
                            const visibleBadges = submenu.querySelectorAll('.menu-badge:not([style*="display: none"])');
                            let total = 0;
                            visibleBadges.forEach(b => {
                                total += parseInt(b.textContent) || 0;
                            });
                            if (total > 0) {
                                parentBadge.textContent = total;
                            } else {
                                parentBadge.style.display = 'none';
                            }
                        }
                    }
                }
            }
        });
        
        // ========== Universal Image Preview System ==========
        
        // Initialize image previews on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Find all file inputs for images
            document.querySelectorAll('input[type="file"][accept*="image"]').forEach(function(input) {
                setupImagePreview(input);
            });
        });
        
        // Setup preview for a file input
        function setupImagePreview(input) {
            // Create preview container if not exists
            let container = input.parentElement.querySelector('.image-preview-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'image-preview-container';
                container.dataset.inputId = input.id || 'input-' + Math.random().toString(36).substr(2, 9);
                input.insertAdjacentElement('afterend', container);
            }
            
            // Store selected files
            input.selectedFiles = [];
            
            input.addEventListener('change', function(e) {
                const files = Array.from(e.target.files);
                const isMultiple = input.hasAttribute('multiple');
                
                if (!isMultiple) {
                    // Single file - clear previous
                    container.innerHTML = '';
                    input.selectedFiles = [];
                }
                
                files.forEach(function(file) {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            addPreviewItem(container, e.target.result, file.name, input, isMultiple);
                        };
                        reader.readAsDataURL(file);
                        input.selectedFiles.push(file);
                    }
                });
            });
        }
        
        // Add a preview item to container
        function addPreviewItem(container, src, filename, input, isMultiple) {
            const item = document.createElement('div');
            item.className = 'image-preview-item' + (!isMultiple ? ' main-image' : '');
            item.dataset.filename = filename;
            
            item.innerHTML = `
                <img src="${src}" alt="${filename}">
                <button type="button" class="delete-btn" title="মুছুন">
                    <i class="bi bi-x"></i>
                </button>
            `;
            
            // Delete button handler
            item.querySelector('.delete-btn').addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Remove from selectedFiles array
                if (input.selectedFiles) {
                    input.selectedFiles = input.selectedFiles.filter(f => f.name !== filename);
                }
                
                // Remove preview item
                item.remove();
                
                // Clear the file input if no files left
                if (container.children.length === 0) {
                    input.value = '';
                }
                
                // For existing images (from server), add to hidden field for deletion
                if (item.dataset.existingUrl) {
                    addToDeleteList(input, item.dataset.existingUrl);
                }
            });
            
            container.appendChild(item);
        }
        
        // Add existing image to preview (for edit pages)
        function addExistingImagePreview(containerId, imageUrl, isMain = false) {
            const container = document.getElementById(containerId);
            if (!container) return;
            
            const item = document.createElement('div');
            item.className = 'image-preview-item' + (isMain ? ' main-image' : '');
            item.dataset.existingUrl = imageUrl;
            
            item.innerHTML = `
                <img src="${imageUrl}" alt="Existing image">
                <button type="button" class="delete-btn" title="মুছুন">
                    <i class="bi bi-x"></i>
                </button>
            `;
            
            item.querySelector('.delete-btn').addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                item.remove();
                addToDeleteList(container, imageUrl);
            });
            
            container.appendChild(item);
        }
        
        // Track images to delete
        function addToDeleteList(container, url) {
            let deleteInput = container.parentElement.querySelector('input[name="delete_images[]"]');
            if (!deleteInput) {
                deleteInput = document.createElement('input');
                deleteInput.type = 'hidden';
                deleteInput.name = 'delete_images[]';
                container.parentElement.appendChild(deleteInput);
            }
            // Add new hidden input for each deleted image
            const newInput = document.createElement('input');
            newInput.type = 'hidden';
            newInput.name = 'delete_images[]';
            newInput.value = url;
            container.parentElement.appendChild(newInput);
        }
    </script>
</body>
</html>
