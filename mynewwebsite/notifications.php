<?php
require_once 'session_handler.php';

// Redirect if not logged in
requireLogin('index.php');

$loggedIn = isUserLoggedIn();
$currentUser = getCurrentUser();
$currentUsername = $currentUser['username'] ?? '';

$notificationsFile = 'notifications.json';
$commentsFile = 'comments.json';

// Initialize notifications file if it doesn't exist
if (!file_exists($notificationsFile)) {
    file_put_contents($notificationsFile, json_encode([], JSON_PRETTY_PRINT));
}

// Load notifications
$notifications = json_decode(file_get_contents($notificationsFile), true) ?? [];

// Handle marking notification as read
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_read'])) {
    $notificationIndex = intval($_POST['mark_read']);
    
    if (isset($notifications[$notificationIndex])) {
        $notifications[$notificationIndex]['read'] = true;
        file_put_contents($notificationsFile, json_encode($notifications, JSON_PRETTY_PRINT));
    }
}

// Handle clearing all notifications
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['clear_all'])) {
    // Only keep unread notifications or delete all for this user
    $userNotifications = array_filter($notifications, function($n) use ($currentUsername) {
        return $n['tagged_user'] === $currentUsername;
    });
    
    // Remove all notifications for current user
    $notifications = array_filter($notifications, function($n) use ($currentUsername) {
        return $n['tagged_user'] !== $currentUsername;
    });
    
    // Reindex array
    $notifications = array_values($notifications);
    file_put_contents($notificationsFile, json_encode($notifications, JSON_PRETTY_PRINT));
    $success = "All notifications cleared.";
}

// Get notifications for current user
$userNotifications = array_filter($notifications, function($n) use ($currentUsername) {
    return $n['tagged_user'] === $currentUsername;
});

// Sort by date (newest first)
usort($userNotifications, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Count unread notifications
$unreadCount = count(array_filter($userNotifications, function($n) {
    return !isset($n['read']) || !$n['read'];
}));

$error = $error ?? '';
$success = $success ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background-color: #000;
            background-image: url('https://images.unsplash.com/photo-1534796636912-3b95b3ab5986?ixlib=rb-4.0.3&auto=format&fit=crop&w=2342&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: #e0e0e0;
        }
        .navbar {
            background-color: rgba(20, 10, 30, 0.9);
            padding: 10px 20px;
            border-bottom: 1px solid #4a2c60;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(5px);
            margin-bottom: 20px;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .nav-links { display: flex; gap: 10px; }
        .nav-links a {
            color: #d1c4e9;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 20px;
            transition: all 0.3s;
            font-size: 14px;
        }
        .nav-links a:hover { background-color: #4a2c60; color: #fff; }

        .notifications-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .section {
            background: rgba(26, 11, 46, 0.85);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(124, 77, 255, 0.3);
            border: 1px solid #4a2c60;
            backdrop-filter: blur(10px);
            margin-bottom: 20px;
        }
        .section h2 {
            margin-top: 0;
            color: #fff;
        }
        .notification {
            padding: 15px;
            margin: 10px 0;
            border-left: 4px solid #7c4dff;
            border-radius: 4px;
            background: rgba(0, 0, 0, 0.2);
        }
        .notification.unread {
            background: rgba(124, 77, 255, 0.15);
            border-left-color: #b388ff;
            font-weight: 500;
        }
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .notification-user {
            font-weight: bold;
            color: #b388ff;
        }
        .notification-date {
            font-size: 12px;
            color: #aaa;
        }
        .notification-text {
            color: #e0e0e0;
            margin: 10px 0;
            line-height: 1.5;
        }
        .notification-comment {
            background: rgba(0,0,0,0.3);
            padding: 10px;
            border-left: 3px solid #4a2c60;
            margin: 10px 0;
            font-style: italic;
            color: #ccc;
            border-radius: 2px;
        }
        .notification-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .view-btn {
            background-color: #7c4dff;
            color: white;
        }
        .view-btn:hover {
            background-color: #651fff;
        }
        .read-btn {
            background-color: #6c757d;
            color: white;
        }
        .read-btn:hover {
            background-color: #5a6268;
        }
        .clear-btn {
            background-color: #dc3545;
            color: white;
        }
        .clear-btn:hover {
            background-color: #c82333;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .empty-state {
            color: #aaa;
            text-align: center;
            padding: 30px;
            font-style: italic;
        }
        .badge {
            display: inline-block;
            background-color: #dc3545;
            color: white;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 5px;
        }
        .header-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div style="font-weight: bold; color: #b388ff; font-size: 1.2em;">🌌 GalaxyConnect</div>
        <div class="nav-links">
            <a href="comments.php">Comments</a>
            <a href="messages.php">Messages</a>
            <a href="friends.php">Friends</a>
            <a href="notifications.php">Notifications<?php if ($unreadCount > 0): ?><span class="badge"><?php echo $unreadCount; ?></span><?php endif; ?></a>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Log Out (<?php echo htmlspecialchars($currentUsername); ?>)</a>
        </div>
    </div>

    <div class="notifications-container">
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="section">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0;">Notifications (<?php echo count($userNotifications); ?>)</h2>
                <?php if (!empty($userNotifications)): ?>
                    <form method="POST" style="display:inline;">
                        <button type="submit" name="clear_all" value="1" class="action-btn clear-btn" onclick="return confirm('Clear all notifications?');">Clear All</button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (empty($userNotifications)): ?>
                <div class="empty-state">
                    🔔 No notifications yet<br>
                    <small>You'll be notified when someone tags you in a comment</small>
                </div>
            <?php else: ?>
                <?php foreach ($userNotifications as $index => $notification): ?>
                    <?php 
                        $isUnread = !isset($notification['read']) || !$notification['read'];
                        $actualIndex = array_search($notification, $notifications);
                    ?>
                    <div class="notification <?php echo $isUnread ? 'unread' : ''; ?>">
                        <div class="notification-header">
                            <span>
                                <span class="notification-user"><?php echo htmlspecialchars($notification['tagged_by']); ?></span>
                                tagged you in a comment
                            </span>
                            <span class="notification-date"><?php echo date('M d, H:i', strtotime($notification['date'])); ?></span>
                        </div>
                        
                        <div class="notification-text">
                            <?php echo htmlspecialchars($notification['message']); ?>
                        </div>
                        
                        <?php if (!empty($notification['comment_preview'])): ?>
                            <div class="notification-comment">
                                "<?php echo htmlspecialchars(substr($notification['comment_preview'], 0, 150)); ?><?php echo strlen($notification['comment_preview']) > 150 ? '...' : ''; ?>"
                            </div>
                        <?php endif; ?>
                        
                        <div class="notification-actions">
                            <a href="comments.php" class="action-btn view-btn">View Comments</a>
                            <?php if ($isUnread): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="mark_read" value="<?php echo $actualIndex; ?>">
                                    <button type="submit" class="action-btn read-btn">Mark as Read</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
