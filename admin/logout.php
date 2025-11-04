<?php
// Start the session to access session variables
session_start();

// Unset all of the session variables.
// Alternatively, you can use unset($_SESSION['variable_name']) to clear specific variables.
$_SESSION = array();

// Destroy the session. This will delete the session file on the server.
session_destroy();

// Redirect to the login page or homepage
header("Location: ../index.php"); // Or "index.php" or any other desired page
exit(); // Terminate the script to prevent further execution after the redirect
?>