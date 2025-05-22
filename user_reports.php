<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Nubenta – My Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard_style.css">
    <style>
        .report-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .report-header {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            background: #f8f9fa;
            border-radius: 8px 8px 0 0;
        }
        
        .report-body {
            padding: 15px;
        }
        
        .report-footer {
            padding: 15px;
            border-top: 1px solid #dee2e6;
            background: #f8f9fa;
            border-radius: 0 0 8px 8px;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-reviewed {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-resolved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-dismissed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-closed {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .admin-response {
            background: #3b3f44;
            border: 1px solid #5a6268;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            color: #e9ecef;
        }
        
        .user-appeal {
            background: #4a4e53;
            border: 1px solid #6c757d;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            color: #e9ecef;
        }
        
        .screenshot-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        .report-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .report-actions button {
            padding: 8px 15px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-appeal {
            background: #2c2c2c;
            color: white;
        }
        
        .btn-appeal:hover {
            background: #404040;
        }
        
        .btn-close-report {
            background: #6c757d;
            color: white;
        }
        
        .btn-close-report:hover {
            background: #5a6268;
        }
        
        .report-timestamp {
            font-size: 0.85em;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .report-type {
            font-weight: 500;
            color: #495057;
        }
        
        .report-details {
            margin-top: 10px;
            white-space: pre-wrap;
        }
        
        .report-closed-info {
            color: #6c757d;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .report-closed-info i {
            color: #6c757d;
        }
    </style>
</head>
<body>
    <button class="hamburger" onclick="toggleSidebar()" id="hamburgerBtn">☰</button>

    <div class="dashboard-grid">
        <!-- Left Sidebar - Navigation -->
        <aside class="left-sidebar">
            <h1>Nubenta</h1>
            <?php
            $currentUser = $user;
            $currentPage = 'messages';
            include 'assets/navigation.php';
            ?>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>My Reports</h3>
                <div>
                    <a href="messages.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Messages
                    </a>
                </div>
            </div>

            <div id="reports-container">
                <!-- Reports will be loaded here -->
            </div>
        </main>

        <!-- Right Sidebar -->
        <aside class="right-sidebar">
            <div class="sidebar-section">
                <h4>📢 Ads</h4>
                <p>(Coming Soon)</p>
            </div>
        </aside>
    </div>

    <!-- Appeal Modal -->
    <div class="modal fade" id="appealModal" tabindex="-1" aria-labelledby="appealModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="appealModalLabel">Appeal Admin's Decision</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="appealForm">
                        <input type="hidden" id="reportId" name="report_id">
                        <div class="mb-3">
                            <label for="appealReason" class="form-label">Reason for Appeal</label>
                            <textarea class="form-control" id="appealReason" name="appeal_reason" rows="4" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-dark" onclick="submitAppeal()">Submit Appeal</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Load reports
        async function loadReports() {
            try {
                const response = await fetch('api/get_user_reports.php');
                const data = await response.json();
                
                if (!data.success) {
                    console.error('Error loading reports:', data.error);
                    return;
                }
                
                const container = document.getElementById('reports-container');
                container.innerHTML = '';
                
                if (data.reports.length === 0) {
                    container.innerHTML = '<div class="alert alert-info">You have not submitted any reports yet.</div>';
                    return;
                }
                
                data.reports.forEach(report => {
                    const card = createReportCard(report);
                    container.appendChild(card);
                });
            } catch (error) {
                console.error('Error:', error);
            }
        }
        
        function createReportCard(report) {
            const div = document.createElement('div');
            div.className = 'report-card';
            
            const statusClass = {
                'pending': 'status-pending',
                'reviewed': 'status-reviewed',
                'resolved': 'status-resolved',
                'dismissed': 'status-dismissed',
                'closed': 'status-closed'
            }[report.status] || 'status-pending';
            
            div.innerHTML = `
                <div class="report-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="report-type">${report.report_type}</span>
                            <span class="status-badge ${statusClass}">${report.status}</span>
                        </div>
                        <div class="report-timestamp">
                            Reported on ${new Date(report.created_at).toLocaleString()}
                        </div>
                    </div>
                </div>
                <div class="report-body">
                    <div class="report-details">${report.details}</div>
                    ${report.screenshot_path ? `
                        <div class="mt-3">
                            <img src="${report.screenshot_path}" alt="Report Screenshot" class="screenshot-preview">
                        </div>
                    ` : ''}
                    
                    ${report.admin_response ? `
                        <div class="admin-response">
                            <strong>Admin Response:</strong>
                            <div class="mt-2">${report.admin_response}</div>
                            <div class="report-timestamp">
                                Responded on ${new Date(report.updated_at).toLocaleString()}
                            </div>
                        </div>
                    ` : ''}
                    
                    ${report.user_appeal ? `
                        <div class="user-appeal">
                            <strong>Your Appeal:</strong>
                            <div class="mt-2">${report.user_appeal}</div>
                            <div class="report-timestamp">
                                Appealed on ${new Date(report.appeal_date).toLocaleString()}
                            </div>
                        </div>
                    ` : ''}
                </div>
                <div class="report-footer">
                    <div class="report-actions">
                        ${report.status.toLowerCase() !== 'closed' && report.admin_response && !report.user_appeal ? `
                            <button class="btn-appeal" onclick="openAppealModal(${report.id})">
                                <i class="fas fa-gavel"></i> Appeal Decision
                            </button>
                        ` : ''}
                        
                        ${report.status.toLowerCase() === 'closed' ? `
                            <div class="report-closed-info">
                                <i class="fas fa-check-circle"></i> Report closed on ${new Date(report.closed_at).toLocaleString()}
                            </div>
                        ` : `
                            <button class="btn-close-report" onclick="closeReport(${report.id})">
                                <i class="fas fa-times"></i> Close Report
                            </button>
                        `}
                    </div>
                </div>
            `;
            
            return div;
        }
        
        function openAppealModal(reportId) {
            document.getElementById('reportId').value = reportId;
            const modal = new bootstrap.Modal(document.getElementById('appealModal'));
            modal.show();
        }
        
        async function submitAppeal() {
            const reportId = document.getElementById('reportId').value;
            const appealReason = document.getElementById('appealReason').value;
            
            if (!appealReason.trim()) {
                alert('Please provide a reason for your appeal');
                return;
            }
            
            try {
                const response = await fetch('api/submit_appeal.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        report_id: reportId,
                        appeal_reason: appealReason
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('appealModal'));
                    modal.hide();
                    
                    // Reset form
                    document.getElementById('appealForm').reset();
                    
                    // Reload reports
                    loadReports();
                    
                    alert('Your appeal has been submitted successfully.');
                } else {
                    alert(data.error || 'Failed to submit appeal. Please try again.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while submitting your appeal.');
            }
        }
        
        async function closeReport(reportId) {
            if (!confirm('Are you sure you want to close this report?')) {
                return;
            }
            
            try {
                const response = await fetch('api/close_report.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        report_id: reportId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    loadReports();
                    alert('Report closed successfully.');
                } else {
                    alert(data.error || 'Failed to close report. Please try again.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while closing the report.');
            }
        }
        
        // Load reports when page loads
        document.addEventListener('DOMContentLoaded', loadReports);
        
        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.querySelector('.left-sidebar');
            sidebar.classList.toggle('show');
        }
        
        // Click outside to close sidebar
        document.addEventListener('click', function(e) {
            const sidebar = document.querySelector('.left-sidebar');
            const hamburger = document.getElementById('hamburgerBtn');
            if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        });
    </script>
</body>
</html> 