<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get all reports with user information
try {
    $stmt = $pdo->query("
        SELECT r.*, 
               reporter.name as reporter_name,
               reported.name as reported_name,
               t.title as thread_title
        FROM user_reports r
        LEFT JOIN users reporter ON r.reporter_id = reporter.id
        LEFT JOIN users reported ON r.reported_user_id = reported.id
        LEFT JOIN chat_threads t ON r.thread_id = t.id
        ORDER BY r.created_at DESC
    ");
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching reports: " . $e->getMessage());
    $reports = [];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            color: #212529;
        }
        .report-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            background-color: #2c2c2c;
            color: #fff;
        }
        .status-pending { background-color: #2c2c2c; color: #ffd700; }
        .status-reviewed { background-color: #2c2c2c; color: #00bfff; }
        .status-resolved { background-color: #2c2c2c; color: #90ee90; }
        .status-dismissed { background-color: #2c2c2c; color: #ff6b6b; }
        .status-closed { background-color: #2c2c2c; color: #a9a9a9; }
        
        .screenshot-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .admin-response {
            background-color: #2c2c2c;
            color: #fff;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
        }
        
        .user-appeal {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
        }
        
        .appeal-content {
            margin: 10px 0;
            color: #2c2c2c;
        }
        
        .appeal-date {
            font-size: 0.9em;
            color: #6c757d;
        }

        .btn-primary {
            background-color: #2c2c2c;
            border-color: #2c2c2c;
        }

        .btn-primary:hover {
            background-color: #404040;
            border-color: #404040;
        }

        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }

        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }

        .form-control:focus {
            border-color: #2c2c2c;
            box-shadow: 0 0 0 0.2rem rgba(44, 44, 44, 0.25);
        }

        .form-select:focus {
            border-color: #2c2c2c;
            box-shadow: 0 0 0 0.2rem rgba(44, 44, 44, 0.25);
        }

        h2, h5 {
            color: #2c2c2c;
        }

        .text-muted {
            color: #6c757d !important;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>User Reports</h2>
            <a href="admin_dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Admin Panel
            </a>
        </div>

        <?php if (empty($reports)): ?>
            <div class="alert alert-info">
                No reports found.
            </div>
        <?php else: ?>
            <?php foreach ($reports as $report): ?>
                <div class="report-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5>
                                Report from <?php echo htmlspecialchars($report['reporter_name']); ?> 
                                against <?php echo htmlspecialchars($report['reported_name']); ?>
                            </h5>
                            <p class="text-muted">
                                Reported on: <?php echo date('F j, Y g:i a', strtotime($report['created_at'])); ?>
                            </p>
                        </div>
                        <span class="status-badge status-<?php echo $report['status']; ?>">
                            <?php echo ucfirst($report['status']); ?>
                        </span>
                    </div>

                    <div class="mt-3">
                        <strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $report['report_type'])); ?><br>
                        <strong>Thread:</strong> <?php echo htmlspecialchars($report['thread_title'] ?? 'N/A'); ?><br>
                        <strong>Details:</strong><br>
                        <p><?php echo nl2br(htmlspecialchars($report['details'])); ?></p>

                        <?php if ($report['screenshot_path']): ?>
                            <div>
                                <strong>Screenshot:</strong><br>
                                <img src="<?php echo htmlspecialchars($report['screenshot_path']); ?>" 
                                     class="screenshot-preview" 
                                     alt="Report Screenshot">
                            </div>
                        <?php endif; ?>

                        <?php if ($report['admin_response']): ?>
                            <div class="admin-response">
                                <strong>Admin Response:</strong><br>
                                <?php echo nl2br(htmlspecialchars($report['admin_response'])); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($report['user_appeal']): ?>
                            <div class="user-appeal mt-3">
                                <strong>User Appeal:</strong><br>
                                <div class="appeal-content">
                                    <?php echo nl2br(htmlspecialchars($report['user_appeal'])); ?>
                                </div>
                                <div class="appeal-date text-muted">
                                    Appealed on: <?php echo date('F j, Y g:i a', strtotime($report['appeal_date'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="mt-3">
                            <form onsubmit="return updateReport(event, <?php echo $report['id']; ?>)" class="row g-3">
                                <div class="col-md-4">
                                    <select name="status" class="form-select" required>
                                        <option value="">Update Status</option>
                                        <option value="pending" <?php echo $report['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="reviewed" <?php echo $report['status'] === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                        <option value="resolved" <?php echo $report['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        <option value="dismissed" <?php echo $report['status'] === 'dismissed' ? 'selected' : ''; ?>>Dismissed</option>
                                        <option value="closed" <?php echo $report['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <div class="input-group">
                                        <input type="text" name="admin_response" class="form-control" 
                                               placeholder="<?php echo $report['user_appeal'] ? 'Respond to appeal...' : 'Add admin response...'; ?>" 
                                               value="<?php echo htmlspecialchars($report['admin_response'] ?? ''); ?>">
                                        <button type="submit" class="btn btn-primary">Update</button>
                                        <?php if ($report['status'] !== 'closed'): ?>
                                            <button type="button" class="btn btn-danger" onclick="closeReport(<?php echo $report['id']; ?>)">Close Report</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateReport(event, reportId) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.append('report_id', reportId);

            fetch('api/update_report.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error updating report: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating report. Please try again.');
            });

            return false;
        }

        async function closeReport(reportId) {
            if (!confirm('Are you sure you want to close this report? This action cannot be undone.')) {
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
                    location.reload();
                } else {
                    alert(data.error || 'Failed to close report. Please try again.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while closing the report.');
            }
        }

        // Add auto-close functionality for reports after 7 days
        async function checkAutoCloseReports() {
            try {
                const response = await fetch('api/auto_close_reports.php');
                const data = await response.json();
                
                if (data.success && data.closed_count > 0) {
                    console.log(`Auto-closed ${data.closed_count} reports`);
                    loadReports();
                }
            } catch (error) {
                console.error('Error checking auto-close reports:', error);
            }
        }

        // Check for reports to auto-close every hour
        setInterval(checkAutoCloseReports, 3600000);
    </script>
</body>
</html> 