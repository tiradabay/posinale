<?php
require_once __DIR__ . '/auth_check.php';
require_api_auth(['admin', 'cashier']);
include '../connection.php';
header('Content-Type: application/json');

/* =========================
   GET JSON INPUT
========================= */
$data = json_decode(file_get_contents("php://input"), true);

$customerID = intval($data['customerID'] ?? 0);

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

/* =========================
   SINGLE CUSTOMER FETCH
========================= */
if ($customerID > 0) {

    $stmt = $conn->prepare("
        SELECT
            CustomerID,
            Name,
            Contact,
            LoyaltyPoints
        FROM Customer
        WHERE CustomerID = ?
    ");

    $stmt->bind_param("i", $customerID);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        echo json_encode([
            "success" => false,
            "error" => "Customer not found"
        ]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "data" => $row
    ]);

    exit;
}

/* =========================
   STATS
========================= */
$statsSQL = "SELECT
    COUNT(*) AS total,
    COALESCE(SUM(LoyaltyPoints), 0) AS totalPoints,
    COALESCE(ROUND(AVG(LoyaltyPoints), 0), 0) AS avgPoints,
    SUM(CASE WHEN LoyaltyPoints >= 500 THEN 1 ELSE 0 END) AS premium
FROM Customer";

$statsResult = mysqli_query($conn, $statsSQL);

if (!$statsResult) {
    echo json_encode([
        'success' => false,
        'error' => mysqli_error($conn)
    ]);
    exit;
}

$stats = mysqli_fetch_assoc($statsResult);

/* =========================
   SEARCH FILTER
========================= */
$searchFilter = "";

if ($search !== '') {

    $s = mysqli_real_escape_string($conn, $search);

    $searchFilter = "WHERE 
        c.Name LIKE '%$s%' 
        OR c.Contact LIKE '%$s%' 
        OR c.CustomerID LIKE '%$s%'
        OR CONCAT('2025-', LPAD(c.CustomerID, 3, '0')) LIKE '%$s%'";
}

/* =========================
   CUSTOMERS LIST
========================= */
$customersSQL = "
SELECT
    c.CustomerID,
    CONCAT('2025-', LPAD(c.CustomerID, 3, '0')) AS CustomerCode,
    c.Name,
    c.Contact,
    c.LoyaltyPoints
FROM Customer c
$searchFilter
ORDER BY c.CustomerID ASC
";

$customersResult = mysqli_query($conn, $customersSQL);

if (!$customersResult) {
    echo json_encode([
        'success' => false,
        'error' => mysqli_error($conn)
    ]);
    exit;
}

$customers = [];

while ($row = mysqli_fetch_assoc($customersResult)) {
    $customers[] = $row;
}

/* =========================
   OUTPUT
========================= */
echo json_encode([
    'success' => true,
    'stats' => $stats,
    'customers' => $customers
]);
?>