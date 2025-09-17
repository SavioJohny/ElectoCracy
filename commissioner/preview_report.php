<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Election Commissioner');

$type = $_GET['type'] ?? '';
$election_id = (int)($_GET['election_id'] ?? 0);

if (!$type || !$election_id) {
    die('Report type and election ID are required.');
}

try {
    if ($type === 'class') {
        // Get class election details
        $stmt = $pdo->prepare("
            SELECT e.*, et.election_type_name, c.class_name, d.department_name
            FROM elections e
            JOIN election_types et ON e.election_type_id = et.election_type_id
            LEFT JOIN classes c ON e.class_id = c.class_id
            LEFT JOIN departments d ON c.department_id = d.department_id
            WHERE e.election_id = ? AND et.election_type_name = 'class'
        ");
        $stmt->execute([$election_id]);
        $election = $stmt->fetch();
        
        if (!$election) {
            throw new Exception('Class election not found.');
        }
        
        $title = "Class Election Report - {$election['class_name']} ({$election['election_year']})";
        
    } elseif ($type === 'union') {
        // Get union election details
        $stmt = $pdo->prepare("
            SELECT e.*, et.election_type_name
            FROM elections e
            JOIN election_types et ON e.election_type_id = et.election_type_id
            WHERE e.election_id = ? AND et.election_type_name = 'union'
        ");
        $stmt->execute([$election_id]);
        $election = $stmt->fetch();
        
        if (!$election) {
            throw new Exception('Union election not found.');
        }
        
        $title = "Union Election Report - {$election['election_year']}";
        
    } else {
        throw new Exception('Invalid report type.');
    }
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3">
                <div>
                    <h2><i class="fas fa-eye me-2"></i><?php echo htmlspecialchars($title); ?> - Preview</h2>
                    <p class="text-muted mb-0">Preview the report before generating PDF</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-success" onclick="generatePDF()">
                        <i class="fas fa-download me-1"></i>Generate PDF
                    </button>
                    <button class="btn btn-info" onclick="printReport()">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                    <a href="reports.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Reports
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Preview -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body" id="reportContent">
                    <!-- Report content will be loaded here -->
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading report preview...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadReportPreview();
});

function loadReportPreview() {
    const type = '<?php echo $type; ?>';
    const electionId = <?php echo $election_id; ?>;
    
    fetch(`get_report_content.php?type=${type}&election_id=${electionId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('reportContent').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('reportContent').innerHTML = 
                '<div class="alert alert-danger">Error loading report preview: ' + error.message + '</div>';
        });
}

function generatePDF() {
    const type = '<?php echo $type; ?>';
    const electionId = <?php echo $election_id; ?>;
    
    showAlert('Generating PDF report...', 'info');
    window.open(`generate_pdf.php?type=${type}&election_id=${electionId}`, '_blank');
}

function printReport() {
    const printWindow = window.open('', '_blank');
    const reportContent = document.getElementById('reportContent').innerHTML;
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title><?php echo htmlspecialchars($title); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                .section { margin-bottom: 20px; }
                .section h3 { color: #333; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .winner { background-color: #d4edda; }
                .stats { background-color: #f8f9fa; padding: 15px; border-radius: 5px; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
                @media print {
                    body { margin: 0; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1><?php echo htmlspecialchars($title); ?></h1>
                <p>Generated on: ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}</p>
            </div>
            ${reportContent}
            <div class="footer">
                <p>This report was generated by the Election Management System</p>
                <p> ${new Date().getFullYear()} - Official Election Results</p>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
}

function showAlert(message, type) {
    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert at top of container
    const container = document.querySelector('.container-fluid');
    container.insertBefore(alertDiv, container.firstChild);
    
    // Auto dismiss after 3 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 3000);
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
