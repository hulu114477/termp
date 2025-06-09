<?php
//header.php
if (session_status() == PHP_SESSION_NONE) session_start();
?>
<header>
    <div class="header-content">
        <h1>מערכת ניהול טרמפים</h1>
        <?php if (isset($_SESSION['employee_id'])): ?>
            <nav>
                <a href="dashboard.php">דף הבית</a>
                <a href="profile.php">הפרופיל שלי</a>
                <a href="rides.php">רשימת טרמפים</a>
                <a href="search_ride.php">חיפוש טרמפ</a>
                <a href="add_ride.php">הוסף טרמפ</a>
                <a href="requests_from_me.php">מבקשים טרמפ ממני</a>
                <a href="my_requests.php">בקשות שלי לטרמפ</a>
                <a href="messages.php">הודעות</a>
                <?php if (!empty($_SESSION['is_manager'])): ?>
                    <a href="manage_users.php">ניהול משתמשים</a>
                <?php endif; ?>
                <a href="logout.php" class="logout-btn">התנתק</a>
            </nav>
        <?php endif; ?>
    </div>
</header>
