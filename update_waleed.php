<?php
header("Content-Type: text/plain");

$host = 'sql206.infinityfree.com';
$user = 'if0_41764748';
$pass = 'qqO5VAfzeSXcD0';
$db   = 'if0_41764748_jwcreativestudio';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$equipment = 'Sony DCR HC-26 MiniDV Tape Camcorder, Nikon D40X, Nikon D50 with audio/visual editing, FL Studio';
$id = 'waleed-bhatti';

$stmt = $conn->prepare("UPDATE users SET equipment = ? WHERE id = ?");
$stmt->bind_param("ss", $equipment, $id);

if ($stmt->execute()) {
    echo "Waleed's equipment updated successfully in the database.\n";
} else {
    echo "Error updating record: " . $conn->error;
}

$conn->close();
?>
