// QR Code Management JavaScript
class QRManager {
    constructor() {
        this.currentQRCodeId = null;
        this.init();
    }

    init() {
        this.loadQRCodes();
        this.bindEvents();
        this.loadSidebar();
    }

    bindEvents() {
        // Refresh button
        document.getElementById('refreshBtn').addEventListener('click', () => {
            this.loadQRCodes();
        });

        // Bulk generate button
        document.getElementById('bulkGenerateBtn').addEventListener('click', () => {
            this.toggleBulkActionsPanel();
        });

        // Cancel bulk generate
        document.getElementById('cancelBulkGenerate').addEventListener('click', () => {
            this.toggleBulkActionsPanel();
        });

        // Execute bulk generate
        document.getElementById('executeBulkGenerate').addEventListener('click', () => {
            this.executeBulkGenerate();
        });

        // Download QR button in modal
        document.getElementById('downloadQRBtn').addEventListener('click', () => {
            this.downloadQRCode();
        });
    }

    async loadSidebar() {
        try {
            const response = await fetch('/crmfms/public/modules/_layout/sidebar.html');
            const sidebarHTML = await response.text();
            document.getElementById('sidebar-content').innerHTML = sidebarHTML;
            
            // Initialize sidebar functionality
            if (typeof SidebarManager !== 'undefined') {
                new SidebarManager();
            }
        } catch (error) {
            console.error('Error loading sidebar:', error);
        }
    }

    async loadQRCodes() {
        try {
            showLoading('qrCodesTableBody');
            
            const response = await apiCall('/api/qr/qr_manager.php?action=list');
            
            if (response.items) {
                this.displayQRCodes(response.items);
                this.updateStatistics(response.items);
            } else {
                throw new Error('Failed to load QR codes');
            }
        } catch (error) {
            console.error('Error loading QR codes:', error);
            showError('qrCodesTableBody', 'Failed to load QR codes: ' + error.message);
        }
    }

    displayQRCodes(qrCodes) {
        const tbody = document.getElementById('qrCodesTableBody');
        
        if (qrCodes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center">No QR codes found</td></tr>';
            return;
        }

        tbody.innerHTML = qrCodes.map(qr => `
            <tr>
                <td>${qr.code_id}</td>
                <td>
                    <span class="badge ${qr.code_type === 'faculty' ? 'bg-success' : 'bg-info'}">
                        ${qr.code_type.toUpperCase()}
                    </span>
                </td>
                <td>${qr.display_name || 'N/A'}</td>
                <td>${qr.reference_code || 'N/A'}</td>
                <td>
                    <img src="${qr.qr_code_url}" 
                         alt="QR Code" 
                         class="qr-code-thumbnail"
                         style="width: 50px; height: 50px; cursor: pointer;"
                         onclick="qrManager.previewQRCode(${qr.code_id})"
                         title="Click to preview">
                </td>
                <td>${new Date(qr.created_at).toLocaleDateString()}</td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" 
                                class="btn btn-outline-primary btn-sm" 
                                onclick="qrManager.previewQRCode(${qr.code_id})"
                                title="Preview QR Code">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button type="button" 
                                class="btn btn-outline-success btn-sm" 
                                onclick="qrManager.downloadQRCodeDirect(${qr.code_id})"
                                title="Download QR Code">
                            <i class="bi bi-download"></i>
                        </button>
                        <button type="button" 
                                class="btn btn-outline-warning btn-sm" 
                                onclick="qrManager.regenerateQRCode(${qr.code_id})"
                                title="Regenerate QR Code">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                        <button type="button" 
                                class="btn btn-outline-danger btn-sm" 
                                onclick="qrManager.deleteQRCode(${qr.code_id})"
                                title="Delete QR Code">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    updateStatistics(qrCodes) {
        const total = qrCodes.length;
        const faculty = qrCodes.filter(qr => qr.code_type === 'faculty').length;
        const room = qrCodes.filter(qr => qr.code_type === 'room').length;

        document.getElementById('totalQRCodes').textContent = total;
        document.getElementById('facultyQRCodes').textContent = faculty;
        document.getElementById('roomQRCodes').textContent = room;
    }

    toggleBulkActionsPanel() {
        const panel = document.getElementById('bulkActionsPanel');
        panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
    }

    async executeBulkGenerate() {
        const type = document.getElementById('generateType').value;
        
        try {
            showLoading();
            
            const response = await apiCall('/api/qr/qr_manager.php', 'POST', {
                action: 'bulk_generate',
                type: type
            });
            
            if (response.success) {
                showSuccess(response.message);
                this.loadQRCodes();
                this.toggleBulkActionsPanel();
            } else {
                throw new Error(response.message);
            }
        } catch (error) {
            console.error('Error generating QR codes:', error);
            showError(null, 'Failed to generate QR codes: ' + error.message);
        }
    }

    async previewQRCode(codeId) {
        this.currentQRCodeId = codeId;
        
        try {
            const previewUrl = `/crmfms/api/qr/qr_manager.php?action=preview&code_id=${codeId}`;
            
            document.getElementById('qrPreviewContent').innerHTML = `
                <img src="${previewUrl}" 
                     alt="QR Code Preview" 
                     class="img-fluid"
                     style="max-width: 300px;">
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('qrPreviewModal'));
            modal.show();
        } catch (error) {
            console.error('Error previewing QR code:', error);
            showError(null, 'Failed to preview QR code');
        }
    }

    async downloadQRCode() {
        if (!this.currentQRCodeId) {
            showError(null, 'No QR code selected for download');
            return;
        }
        
        try {
            const downloadUrl = `/crmfms/api/qr/qr_manager.php?action=download&code_id=${this.currentQRCodeId}`;
            
            // Create a temporary link and trigger download
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = `qr_code_${this.currentQRCodeId}.png`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('qrPreviewModal'));
            modal.hide();
            
        } catch (error) {
            console.error('Error downloading QR code:', error);
            showError(null, 'Failed to download QR code');
        }
    }

    async downloadQRCodeDirect(codeId) {
        try {
            const downloadUrl = `/crmfms/api/qr/qr_manager.php?action=download&code_id=${codeId}`;
            
            // Create a temporary link and trigger download
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = `qr_code_${codeId}.png`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
        } catch (error) {
            console.error('Error downloading QR code:', error);
            showError(null, 'Failed to download QR code');
        }
    }

    async regenerateQRCode(codeId) {
        if (!confirm('Are you sure you want to regenerate this QR code? The old QR code will no longer be valid.')) {
            return;
        }
        
        try {
            showLoading();
            
            const response = await apiCall('/api/qr/qr_manager.php', 'POST', {
                action: 'regenerate',
                code_id: codeId
            });
            
            if (response.success) {
                showSuccess(response.message);
                this.loadQRCodes();
            } else {
                throw new Error(response.message);
            }
        } catch (error) {
            console.error('Error regenerating QR code:', error);
            showError(null, 'Failed to regenerate QR code: ' + error.message);
        }
    }

    async deleteQRCode(codeId) {
        if (!confirm('Are you sure you want to delete this QR code? This action cannot be undone.')) {
            return;
        }
        
        try {
            showLoading();
            
            const response = await apiCall('/api/qr/qr_manager.php', 'POST', {
                action: 'delete',
                code_id: codeId
            });
            
            if (response.success) {
                showSuccess(response.message);
                this.loadQRCodes();
            } else {
                throw new Error(response.message);
            }
        } catch (error) {
            console.error('Error deleting QR code:', error);
            showError(null, 'Failed to delete QR code: ' + error.message);
        }
    }
}

// Initialize QR Manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.qrManager = new QRManager();
});
