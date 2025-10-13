<?php
/**
 * Universal Export Handler
 * Handle all export requests from various modules
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();

$db = Database::getInstance();

// Get export parameters
$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'excel';
$startDate = $_GET['start'] ?? date('Y-m-01');
$endDate = $_GET['end'] ?? date('Y-m-t');

// Export data based on type
$data = [];
$headers = [];
$filename = 'export_' . date('Y-m-d_H-i-s');

switch ($type) {
    case 'all_members':
        $filename = 'members_list_' . date('Y-m-d');
        $headers = ['Member Number', 'Name', 'Phone', 'Email', 'Join Date', 'Status', 'Department'];
        
        $stmt = $db->executeQuery("
            SELECT 
                m.member_number,
                CONCAT(m.first_name, ' ', m.last_name) as name,
                m.phone,
                m.email,
                m.join_date,
                m.membership_status,
                GROUP_CONCAT(d.name SEPARATOR ', ') as departments
            FROM members m
            LEFT JOIN member_departments md ON m.id = md.member_id AND md.is_active = 1
            LEFT JOIN departments d ON md.department_id = d.id
            GROUP BY m.id
            ORDER BY m.first_name, m.last_name
        ");
        
        $results = $stmt->fetchAll();
        foreach ($results as $row) {
            $data[] = [
                $row['member_number'],
                $row['name'],
                $row['phone'] ?? '',
                $row['email'] ?? '',
                date('M d, Y', strtotime($row['join_date'])),
                ucfirst($row['membership_status']),
                $row['departments'] ?? 'None'
            ];
        }
        break;
        
    case 'attendance_summary':
        $filename = 'attendance_summary_' . date('Y-m-d');
        $headers = ['Date', 'Event', 'Type', 'Attendance Count'];
        
        $stmt = $db->executeQuery("
            SELECT 
                e.event_date,
                e.name,
                e.event_type,
                COUNT(ar.id) as attendance
            FROM events e
            LEFT JOIN attendance_records ar ON e.id = ar.event_id
            WHERE e.event_date BETWEEN ? AND ?
            GROUP BY e.id
            ORDER BY e.event_date DESC
        ", [$startDate, $endDate]);
        
        $results = $stmt->fetchAll();
        foreach ($results as $row) {
            $data[] = [
                date('M d, Y', strtotime($row['event_date'])),
                $row['name'],
                ucwords(str_replace('_', ' ', $row['event_type'])),
                $row['attendance']
            ];
        }
        break;
        
    case 'financial_summary':
        $filename = 'financial_summary_' . date('Y-m-d');
        $headers = ['Date', 'Type', 'Category', 'Description', 'Amount'];
        
        // Income
        $stmt = $db->executeQuery("
            SELECT 
                transaction_date as date,
                'Income' as type,
                ic.name as category,
                description,
                amount
            FROM income i
            JOIN income_categories ic ON i.category_id = ic.id
            WHERE transaction_date BETWEEN ? AND ?
            AND status = 'verified'
            ORDER BY transaction_date DESC
        ", [$startDate, $endDate]);
        
        $incomeResults = $stmt->fetchAll();
        
        // Expenses
        $stmt = $db->executeQuery("
            SELECT 
                expense_date as date,
                'Expense' as type,
                ec.name as category,
                description,
                amount
            FROM expenses e
            JOIN expense_categories ec ON e.category_id = ec.id
            WHERE expense_date BETWEEN ? AND ?
            AND status = 'paid'
            ORDER BY expense_date DESC
        ", [$startDate, $endDate]);
        
        $expenseResults = $stmt->fetchAll();
        
        // Combine and sort
        $combined = array_merge($incomeResults, $expenseResults);
        usort($combined, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
        
        foreach ($combined as $row) {
            $data[] = [
                date('M d, Y', strtotime($row['date'])),
                $row['type'],
                $row['category'],
                $row['description'],
                'Ksh ' . number_format($row['amount'], 2)
            ];
        }
        break;
        
    case 'visitor_list':
        $filename = 'visitors_list_' . date('Y-m-d');
        $headers = ['Visitor Number', 'Name', 'Phone', 'Visit Date', 'Status', 'Follow-up'];
        
        $stmt = $db->executeQuery("
            SELECT 
                v.visitor_number,
                CONCAT(v.first_name, ' ', v.last_name) as name,
                v.phone,
                v.visit_date,
                v.status,
                IFNULL(
                    (SELECT COUNT(*) FROM visitor_followups vf WHERE vf.visitor_id = v.id),
                    0
                ) as followup_count
            FROM visitors v
            WHERE visit_date BETWEEN ? AND ?
            ORDER BY visit_date DESC
        ", [$startDate, $endDate]);
        
        $results = $stmt->fetchAll();
        foreach ($results as $row) {
            $data[] = [
                $row['visitor_number'],
                $row['name'],
                $row['phone'] ?? '',
                date('M d, Y', strtotime($row['visit_date'])),
                ucwords(str_replace('_', ' ', $row['status'])),
                $row['followup_count'] . ' times'
            ];
        }
        break;
        
    case 'equipment_inventory':
        $filename = 'equipment_inventory_' . date('Y-m-d');
        $headers = ['Code', 'Name', 'Category', 'Status', 'Purchase Date', 'Value', 'Location'];
        
        $stmt = $db->executeQuery("
            SELECT 
                e.equipment_code,
                e.name,
                ec.name as category,
                e.status,
                e.purchase_date,
                e.purchase_price,
                e.location
            FROM equipment e
            JOIN equipment_categories ec ON e.category_id = ec.id
            ORDER BY e.name
        ");
        
        $results = $stmt->fetchAll();
        foreach ($results as $row) {
            $data[] = [
                $row['equipment_code'],
                $row['name'],
                $row['category'],
                ucfirst(str_replace('_', ' ', $row['status'])),
                $row['purchase_date'] ? date('M d, Y', strtotime($row['purchase_date'])) : '-',
                'Ksh ' . number_format($row['purchase_price'] ?? 0, 2),
                $row['location'] ?? '-'
            ];
        }
        break;
        
    default:
        die('Invalid export type');
}

// Export based on format
if ($format === 'excel' || $format === 'csv') {
    // Set headers
    if ($format === 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    } else {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    }
    
    // Output headers
    echo implode("\t", $headers) . "\n";
    
    // Output data
    foreach ($data as $row) {
        echo implode("\t", $row) . "\n";
    }
    
} elseif ($format === 'pdf') {
    // For PDF, we'll create a simple HTML that can be printed as PDF
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $filename; ?></title>
        <style>
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #03045e;
                padding-bottom: 10px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            th {
                background-color: #03045e;
                color: white;
                padding: 10px;
                text-align: left;
            }
            td {
                border: 1px solid #ddd;
                padding: 8px;
            }
            tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .footer {
                margin-top: 30px;
                text-align: center;
                font-size: 10px;
                color: #666;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><?php echo $churchInfo['church_name'] ?? 'Deliverance Church'; ?></h1>
            <h3><?php echo ucwords(str_replace('_', ' ', $type)); ?></h3>
            <p>Generated on: <?php echo date('F d, Y h:i A'); ?></p>
            <?php if ($startDate && $endDate): ?>
            <p>Period: <?php echo date('M d, Y', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?></p>
            <?php endif; ?>
        </div>
        
        <table>
            <thead>
                <tr>
                    <?php foreach ($headers as $header): ?>
                    <th><?php echo $header; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                <tr>
                    <?php foreach ($row as $cell): ?>
                    <td><?php echo htmlspecialchars($cell); ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p>This is a computer-generated report from <?php echo $churchInfo['church_name'] ?? 'Deliverance Church'; ?> Management System</p>
            <p>&copy; <?php echo date('Y'); ?> - All Rights Reserved</p>
        </div>
        
        <script>
        // Auto-print for PDF
        window.onload = function() {
            window.print();
        };
        </script>
    </body>
    </html>
    <?php
}

exit;