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

echo "Connected successfully\n";

$sql = "
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(50) PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'client',
    name VARCHAR(255) NOT NULL,
    instagram VARCHAR(255),
    facebook VARCHAR(255),
    linkedin VARCHAR(255),
    bio TEXT,
    pricing_rules TEXT,
    equipment TEXT,
    availability VARCHAR(255),
    profile_image MEDIUMTEXT,
    cover_image MEDIUMTEXT,
    reset_token VARCHAR(255),
    reset_token_expires TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS portfolios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_name VARCHAR(255) NOT NULL,
    client_email VARCHAR(255),
    photographer_id VARCHAR(50) NOT NULL,
    budget_offer VARCHAR(255),
    message TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (photographer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Note: We are now using plain text passwords as requested
INSERT INTO users (id, email, password_hash, role, name, instagram, facebook, availability, equipment, profile_image) 
VALUES 
('james-mcguigan', 'jimmyu2foru18@gmail.com', 'password123', 'admin', 'James McGuigan Jr', 'jimmyu2foru18', 'https://www.facebook.com/jimmyu2foru18/', 'Available Weekends', 'Nikon D3400, D5600, Canon Rebel T3, Polaroid', '/jamesprofile/profile.jpg'),
('waleed-bhatti', 'waleedb219@gmail.com', 'password123', 'photographer', 'Waleed Bhatti', 'waleedb219', 'https://www.facebook.com/profile.php?id=100067090366463', 'Flexible Schedule', 'Sony DCR HC-26 MiniDV Tape Camcorder, Nikon D40X, Nikon D50 with audio/visual editing, FL Studio', '/waleedprofile/profile.jpg')
ON DUPLICATE KEY UPDATE 
    email = VALUES(email),
    password_hash = VALUES(password_hash),
    role = VALUES(role),
    name = VALUES(name),
    equipment = VALUES(equipment);
";

if ($conn->multi_query($sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
    echo "Database setup completed successfully. Plain text passwords applied.\n";
} else {
    echo "Error executing queries: " . $conn->error;
}

$conn->close();
?>
