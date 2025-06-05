<!DOCTYPE html>
<html>
<head>
    <title>User Data Display</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>
    <h1>User Account Information </h1>
    
    <?php
    // Database connection
    $conn = mysqli_connect("localhost", "admin", "healthdata123", "healthdata");
    
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    
    // Fetch data from users table
    $sql = "SELECT id, fullname, username FROM users";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        echo '<table>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Username</th>
                </tr>';
        
        // Output data of each row
        while($row = mysqli_fetch_assoc($result)) {
            echo "<tr>
                    <td>".$row["id"]."</td>
                    <td>".$row["fullname"]."</td>
                    <td>".$row["username"]."</td>
                  </tr>";
        }
        echo "</table>";
    } else {
        echo "0 results found in the users table";
    }
    
    mysqli_close($conn);
    ?>
</body>
</html>