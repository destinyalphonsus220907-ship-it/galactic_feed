<?php
require_once 'session_handler.php';
requireLogin();

$currentUser = getCurrentUser();
$currentUsername = $currentUser['username'];
$username = isset($_GET['user']) ? $_GET['user'] : $currentUsername;
$usersFile = 'users.json';
$commentsFile = 'comments.json';
$friendsFile = 'friends.json';
$uploadsDir = __DIR__ . '/uploads';

// Ensure uploads directory exists
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

$error = '';
$success = '';

// Get full user data
$userData = getUserByUsername($username);
if (!$userData) {
    die("User data not found.");
}

// Load friends data
$friendsData = [];
if (file_exists($friendsFile)) {
    $friendsData = json_decode(file_get_contents($friendsFile), true) ?? [];
}

// Handle Add Friend Request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_friend'])) {
    $targetUser = $_POST['add_friend'];
    if ($targetUser === $username && $targetUser !== $currentUsername) {
        $key1 = "$currentUsername:$targetUser";
        $key2 = "$targetUser:$currentUsername";
        
        if (!isset($friendsData[$key1]) && !isset($friendsData[$key2])) {
             $friendsData[$key1] = [
                'status' => 'pending',
                'from' => $currentUsername,
                'to' => $targetUser,
                'date' => date('Y-m-d H:i:s')
            ];
            file_put_contents($friendsFile, json_encode($friendsData, JSON_PRETTY_PRINT));
            $success = "Friend request sent!";
        }
    }
}

// Handle Avatar Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['avatar']) && $username === $currentUsername) {
    $file = $_FILES['avatar'];
    if ($file['error'] == UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $mimeType = mime_content_type($file['tmp_name']);
        
        if (in_array($mimeType, $allowedTypes)) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . uniqid() . '.' . $ext;
            $targetPath = $uploadsDir . '/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                // Update user in JSON
                $users = json_decode(file_get_contents($usersFile), true);
                foreach ($users as &$user) {
                    if ($user['username'] === $username) {
                        // Delete old avatar if exists and is not a default
                        if (isset($user['avatar']) && file_exists($uploadsDir . '/' . $user['avatar'])) {
                            unlink($uploadsDir . '/' . $user['avatar']);
                        }
                        $user['avatar'] = $filename;
                        $userData['avatar'] = $filename; // Update local var
                        break;
                    }
                }
                file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
                $success = "Avatar updated successfully!";
            } else {
                $error = "Failed to move uploaded file.";
            }
        } else {
            $error = "Invalid file type. Only JPG, PNG, GIF, WEBP allowed.";
        }
    } else {
        $error = "Error uploading file.";
    }
}

// Calculate Stats
$stats = [
    'comments' => 0,
    'likes_received' => 0,
    'friends' => 0,
    'account_age_days' => 0
];

// Comments & Likes
$userPosts = [];
if (file_exists($commentsFile)) {
    $comments = json_decode(file_get_contents($commentsFile), true) ?? [];
    foreach ($comments as $c) {
        $owner = $c['created_by'] ?? $c['name'];
        if ($owner === $username) {
            $stats['comments']++;
            $stats['likes_received'] += ($c['likes'] ?? 0);
            $userPosts[] = $c;
        }
    }
}

// Friends
if (!empty($friendsData)) {
    foreach ($friendsData as $rel) {
        if ($rel['status'] === 'accepted' && ($rel['from'] === $username || $rel['to'] === $username)) {
            $stats['friends']++;
        }
    }
}

// Account Age
if (isset($userData['created_at'])) {
    $created = strtotime($userData['created_at']);
    $stats['account_age_days'] = floor((time() - $created) / (60 * 60 * 24));
}

// Sort posts by date (newest first)
usort($userPosts, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

$avatarUrl = isset($userData['avatar']) ? 'uploads/' . $userData['avatar'] : null;

// Determine relationship status
$friendStatus = 'none';
if ($username !== $currentUsername) {
    $key1 = "$currentUsername:$username";
    $key2 = "$username:$currentUsername";
    
    if (isset($friendsData[$key1])) {
        if ($friendsData[$key1]['status'] === 'accepted') $friendStatus = 'friends';
        elseif ($friendsData[$key1]['status'] === 'pending') $friendStatus = 'pending_sent';
    } elseif (isset($friendsData[$key2])) {
        if ($friendsData[$key2]['status'] === 'accepted') $friendStatus = 'friends';
        elseif ($friendsData[$key2]['status'] === 'pending') $friendStatus = 'pending_received';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo htmlspecialchars($username); ?></title>
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

        .profile-container {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(26, 11, 46, 0.85);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(124, 77, 255, 0.3);
            border: 1px solid #4a2c60;
            backdrop-filter: blur(10px);
            text-align: center;
        }
        .avatar-wrapper {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
        }
        .avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #7c4dff;
            background: linear-gradient(135deg, #7c4dff, #b388ff);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            color: white;
            font-weight: bold;
        }
        .upload-btn {
            margin-top: 10px;
            background: #4a2c60;
            color: white;
            border: 1px solid #7c4dff;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 40px;
        }
        .stat-card {
            background: rgba(0, 0, 0, 0.3);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #4a2c60;
        }
        .stat-value { font-size: 24px; font-weight: bold; color: #b388ff; }
        .stat-label { font-size: 14px; color: #aaa; margin-top: 5px; }
        h1 { color: white; margin-bottom: 5px; }
        .user-meta { color: #aaa; margin-bottom: 20px; }
        
        .action-btn {
            background: #7c4dff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            cursor: pointer;
            margin-top: 10px;
            font-size: 14px;
            transition: background 0.3s;
        }
        .action-btn:hover { background: #651fff; }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            background: rgba(124, 77, 255, 0.2);
            border: 1px solid #7c4dff;
            border-radius: 20px;
            color: #b388ff;
            margin-top: 10px;
            font-size: 14px;
        }
        
        /* Post Card Styles */
        .posts-section {
            max-width: 800px;
            margin: 30px auto;
        }
        .post-card {
            background: rgba(26, 11, 46, 0.85);
            border-radius: 15px;
            border: 1px solid #4a2c60;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            backdrop-filter: blur(10px);
            text-align: left;
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .post-header {
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .post-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #7c4dff;
            background: linear-gradient(135deg, #7c4dff, #b388ff);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .post-info { flex: 1; }
        .post-author { font-weight: bold; color: #fff; display: block; font-size: 15px; }
        .post-date { font-size: 12px; color: #aaa; }
        .post-content {
            padding: 0 15px 15px;
            color: #e0e0e0;
            line-height: 1.5;
            white-space: pre-wrap;
        }
        .post-image {
            width: 100%;
            max-height: 400px;
            object-fit: contain;
            background: rgba(0,0,0,0.5);
            border-top: 1px solid #4a2c60;
            border-bottom: 1px solid #4a2c60;
        }
        .post-footer {
            padding: 10px 15px;
            background: rgba(0,0,0,0.2);
            border-top: 1px solid rgba(74, 44, 96, 0.5);
            color: #aaa;
            font-size: 13px;
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
            <a href="notifications.php">Notifications</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Log Out (<?php echo htmlspecialchars($username); ?>)</a>
        </div>
    </div>

    <div class="profile-container">
        <div class="avatar-wrapper">
            <?php if ($avatarUrl): ?>
                <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar" class="avatar">
            <?php else: ?>
                <div class="avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
            <?php endif; ?>
        </div>
        
        <h1><?php echo htmlspecialchars($username); ?></h1>
        <div class="user-meta">
            <?php echo htmlspecialchars($userData['email']); ?> • Age: <?php echo htmlspecialchars($userData['age']); ?>
        </div>

        <?php if ($success): ?><div style="color: #4caf50; margin-bottom: 15px;"><?php echo $success; ?></div><?php endif; ?>
        <?php if ($error): ?><div style="color: #f44336; margin-bottom: 15px;"><?php echo $error; ?></div><?php endif; ?>

        <?php if ($username === $currentUsername): ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="avatar" id="avatarInput" style="display: none;" onchange="this.form.submit()" accept="image/*">
            <button type="button" class="upload-btn" onclick="document.getElementById('avatarInput').click()">Change Avatar</button>
        </form>
        <?php else: ?>
            <?php if ($friendStatus === 'none'): ?>
                <form method="POST">
                    <button type="submit" name="add_friend" value="<?php echo htmlspecialchars($username); ?>" class="action-btn">Add Friend</button>
                </form>
            <?php elseif ($friendStatus === 'pending_sent'): ?>
                <div class="status-badge">Request Sent</div>
            <?php elseif ($friendStatus === 'pending_received'): ?>
                <div class="status-badge">Request Pending</div>
            <?php elseif ($friendStatus === 'friends'): ?>
                <div class="status-badge">Friends</div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['account_age_days']; ?></div>
                <div class="stat-label">Days Active</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['comments']; ?></div>
                <div class="stat-label">Comments</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['likes_received']; ?></div>
                <div class="stat-label">Likes Received</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['friends']; ?></div>
                <div class="stat-label">Friends</div>
            </div>
        </div>
    </div>

    <div class="posts-section">
        <h2 style="color: white; border-bottom: 1px solid #4a2c60; padding-bottom: 10px; margin-bottom: 20px;">Recent Activity</h2>
        <?php if (empty($userPosts)): ?>
            <div style="text-align: center; color: #aaa; padding: 20px; background: rgba(26, 11, 46, 0.85); border-radius: 15px; border: 1px solid #4a2c60;">No posts yet.</div>
        <?php else: ?>
            <?php foreach ($userPosts as $post): ?>
                <div class="post-card">
                    <div class="post-header">
                        <?php if ($avatarUrl): ?>
                            <img src="<?php echo htmlspecialchars($avatarUrl); ?>" class="post-avatar" alt="Avatar">
                        <?php else: ?>
                            <div class="post-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                        <?php endif; ?>
                        <div class="post-info">
                            <span class="post-author"><?php echo htmlspecialchars($username); ?></span>
                            <span class="post-date"><?php echo htmlspecialchars($post['date']); ?></span>
                        </div>
                    </div>
                    <div class="post-content"><?php echo nl2br($post['comment']); ?></div>
                    <?php if (isset($post['image']) && !empty($post['image'])): ?>
                        <img src="uploads/<?php echo htmlspecialchars($post['image']); ?>" class="post-image" alt="Post Image">
                    <?php endif; ?>
                    <div class="post-footer">
                        ❤️ <?php echo isset($post['likes']) ? $post['likes'] : 0; ?> Likes
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>