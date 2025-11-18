<nav class="navbar">
    <div class="logo">
        <img src="images/logo.jpg" alt="LBC Logo" class="navbar-logo">
        <h1>LOST BOYS CLUB</h1>
    </div>
    
    <div class="nav-links">
        <a href="dashboard.php">Home</a>
        <a href="ticket.php">Ticket</a>
        <a href="account.php">Account</a>
        
        <?php if (isset($_SESSION['user_name'])): ?>
            <div class="user-info">
                <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></p>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        <?php endif; ?>
    </div>
</nav>
