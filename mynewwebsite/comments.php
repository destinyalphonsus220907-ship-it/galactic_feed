<?php
require_once 'session_handler.php';
$loggedIn = function_exists('isUserLoggedIn') ? isUserLoggedIn() : (isset($_SESSION['username']));
$currentUser = function_exists('getCurrentUser') ? getCurrentUser() : (isset($_SESSION['username']) ? ['username' => $_SESSION['username']] : []);

// Load users for avatar display
$usersFile = 'users.json';
$userMap = [];
if (file_exists($usersFile)) {
    $usersData = json_decode(file_get_contents($usersFile), true) ?? [];
    foreach ($usersData as $u) {
        $userMap[$u['username']] = $u;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galactic Feed</title>
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

        .feed-container {
            max-width: 600px;
            margin: 0 auto;
            padding-bottom: 40px;
        }
        
        /* Create Post Card */
        .create-post-card {
            background: rgba(26, 11, 46, 0.85);
            padding: 20px;
            border-radius: 15px;
            border: 1px solid #4a2c60;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            backdrop-filter: blur(10px);
        }
        
        .comment-form textarea {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid #4a2c60;
            border-radius: 10px;
            color: white;
            font-family: inherit;
            resize: vertical;
            min-height: 80px;
        }
        .comment-form textarea:focus { outline: none; border-color: #7c4dff; background: rgba(0,0,0,0.4); }
        
        .comment-form button {
            background-color: #7c4dff;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: background 0.3s;
        }
        .comment-form button:hover {
            background-color: #651fff;
        }
        
        /* Post Card */
        .post-card {
            background: rgba(26, 11, 46, 0.85);
            border-radius: 15px;
            border: 1px solid #4a2c60;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            backdrop-filter: blur(10px);
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .post-header {
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #7c4dff, #b388ff);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            object-fit: cover;
            border: 2px solid #4a2c60;
            font-size: 18px;
        }
        
        .post-info { flex: 1; }
        .post-author { font-weight: bold; color: #fff; display: block; font-size: 16px; }
        .post-meta { font-size: 12px; color: #aaa; margin-top: 2px; }
        
        .post-content {
            padding: 0 15px 15px;
            color: #e0e0e0;
            line-height: 1.5;
            font-size: 15px;
            white-space: pre-wrap;
        }
        
        .post-image-container {
            width: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            border-top: 1px solid #4a2c60;
            border-bottom: 1px solid #4a2c60;
        }
        .post-image {
            max-width: 100%;
            max-height: 500px;
            object-fit: contain;
            display: block;
        }
        
        .post-footer {
            padding: 10px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0,0,0,0.2);
            border-top: 1px solid rgba(74, 44, 96, 0.5);
        }
        
        .action-btn {
            background: transparent;
            border: none;
            color: #aaa;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            transition: all 0.2s;
            font-size: 14px;
        }
        .action-btn:hover { background: rgba(124, 77, 255, 0.1); color: #b388ff; }
        .action-btn.liked { color: #ff4081; }
        .action-btn.delete:hover { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .file-input-wrapper {
            position: relative;
            display: inline-block;
        }
        .file-input-label {
            display: inline-block;
            padding: 8px 15px;
            background-color: transparent;
            color: #b388ff;
            border: 1px solid #b388ff;
            border-radius: 20px;
            cursor: pointer;
            margin: 10px 0;
            text-align: center;
            font-size: 13px;
            transition: all 0.3s;
        }
        .file-input-label:hover { background-color: rgba(124, 77, 255, 0.1); }
        
        .file-input-wrapper input[type="file"] {
            display: none;
        }
        .file-name {
            display: inline-block;
            margin-left: 10px;
            font-size: 12px;
            color: #aaa;
        }
        
        h2 { color: #fff; margin-top: 0; }
        
        /* Search Bar Styles */
        .search-container {
            margin-bottom: 20px;
        }
        .search-form {
            display: flex;
            align-items: center;
            background: rgba(26, 11, 46, 0.85);
            border: 1px solid #4a2c60;
            border-radius: 25px;
            padding: 5px 15px;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .search-input {
            flex: 1;
            background: transparent;
            border: none;
            color: white;
            padding: 10px;
            font-size: 14px;
            outline: none;
        }
        .search-btn { background: transparent; border: none; color: #b388ff; cursor: pointer; font-size: 16px; padding: 5px; }
        .search-btn:hover { color: #fff; }
        .clear-search { color: #aaa; text-decoration: none; font-size: 18px; margin-left: 10px; padding: 0 5px; }
        .clear-search:hover { color: #fff; }

        /* Reply Styles */
        .replies-section {
            background: rgba(0, 0, 0, 0.15);
            padding: 10px 15px 15px;
            border-top: 1px solid rgba(74, 44, 96, 0.3);
        }
        .reply-item {
            display: flex;
            gap: 10px;
            margin-top: 12px;
            padding-left: 10px;
            border-left: 2px solid #4a2c60;
        }
        .reply-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #7c4dff, #b388ff);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
            object-fit: cover;
            flex-shrink: 0;
        }
        .reply-content-box { flex: 1; }
        .reply-author { font-weight: bold; color: #d1c4e9; font-size: 13px; margin-right: 5px; }
        .reply-date { font-size: 11px; color: #888; }
        .reply-text { color: #ccc; font-size: 14px; margin-top: 2px; line-height: 1.4; }
        
        .reply-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            margin-top: 5px;
            display: block;
            border: 1px solid #4a2c60;
        }
        .reply-actions { margin-top: 4px; }
        .action-btn.small { font-size: 11px; padding: 2px 8px; height: auto; }
        
        .reply-file-label {
            cursor: pointer;
            font-size: 18px;
            padding: 0 5px;
        }
        .reply-form-container {
            margin-top: 15px;
            display: none; /* Hidden by default */
        }
        .reply-form-container.active { display: block; }
        .reply-input-group { display: flex; gap: 10px; }
        .reply-input {
            flex: 1;
            background: rgba(0,0,0,0.3);
            border: 1px solid #4a2c60;
            border-radius: 20px;
            padding: 8px 15px;
            color: white;
            font-family: inherit;
            font-size: 13px;
            outline: none;
        }
        .reply-input:focus { border-color: #7c4dff; }
        .reply-submit-btn {
            background: #7c4dff;
            color: white;
            border: none;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 12px;
            cursor: pointer;
        }
        .reply-submit-btn:hover { background: #651fff; }
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
            <?php if ($loggedIn): ?>
                <a href="profile.php">Profile</a>
                <a href="logout.php">Log Out<?php echo !empty($currentUser['username']) ? ' (' . htmlspecialchars($currentUser['username']) . ')' : ''; ?></a>
            <?php else: ?>
                <a href="index.php">Log In</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="feed-container">
        <?php if ($loggedIn): ?>
        <div class="create-post-card">
            <h2 style="font-size: 1.2em; margin-bottom: 15px;">Create Post</h2>
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" class="comment-form">
                <textarea name="comment" placeholder="What's happening in the galaxy?" rows="3" required></textarea>
                
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div class="file-input-wrapper">
                        <label for="image" class="file-input-label">📷 Add Image</label>
                        <input type="file" id="image" name="image" accept="image/*">
                        <span class="file-name" id="file-name"></span>
                    </div>
                    
                    <button type="submit">Post</button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="error-message">
            <p>You must <a href="index.php" style="color: #721c24; text-decoration: underline;">log in</a> to post a comment.</p>
        </div>
        <?php endif; ?>

        <?php $searchQuery = isset($_GET['search']) ? trim($_GET['search']) : ''; ?>
        <div class="search-container">
            <form method="GET" action="comments.php" class="search-form">
                <input type="text" name="search" class="search-input" placeholder="Search posts or users..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                <?php if (!empty($searchQuery)): ?>
                    <a href="comments.php" class="clear-search" title="Clear search">×</a>
                <?php endif; ?>
                <button type="submit" class="search-btn">🔍</button>
            </form>
        </div>

        <div class="feed-list">
            <?php
                $error = '';
                $uploadsDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
                $commentsFile = 'comments.json';

                // Migration: Ensure all comments have unique IDs
                if (file_exists($commentsFile)) {
                    $comments = json_decode(file_get_contents($commentsFile), true) ?? [];
                    $updated = false;
                    foreach ($comments as &$c) {
                        if (!isset($c['id'])) {
                            $c['id'] = uniqid('cmt_');
                            $updated = true;
                        }
                        if (isset($c['replies']) && is_array($c['replies'])) {
                            foreach ($c['replies'] as &$r) {
                                if (!isset($r['id'])) {
                                    $r['id'] = uniqid('rpl_');
                                    $updated = true;
                                }
                            }
                        }
                    }
                    unset($c);
                    if ($updated) {
                        file_put_contents($commentsFile, json_encode($comments, JSON_PRETTY_PRINT));
                    }
                }
                
                // Create uploads directory if it doesn't exist
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0755, true);
                }
                
                // Handle like/unlike
                if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['like_id'])) {
                    $likeId = $_POST['like_id'];
                    $currentUsername = isset($_SESSION['username']) ? $_SESSION['username'] : null;
                    
                    if ($currentUsername && file_exists($commentsFile)) {
                        $comments = json_decode(file_get_contents($commentsFile), true);
                        
                        $likeIndex = -1;
                        foreach ($comments as $index => $cmt) {
                            if (isset($cmt['id']) && $cmt['id'] === $likeId) {
                                $likeIndex = $index;
                                break;
                            }
                        }

                        if ($likeIndex !== -1) {
                            // Initialize liked_by array if it doesn't exist
                            if (!isset($comments[$likeIndex]['liked_by'])) {
                                $comments[$likeIndex]['liked_by'] = [];
                            }
                            
                            // Check if user already liked this comment
                            $userIndex = array_search($currentUsername, $comments[$likeIndex]['liked_by']);
                            
                            if ($userIndex !== false) {
                                // User already liked - remove the like (unlike)
                                array_splice($comments[$likeIndex]['liked_by'], $userIndex, 1);
                            } else {
                                // User hasn't liked - add the like
                                $comments[$likeIndex]['liked_by'][] = $currentUsername;
                            }
                            
                            // Update likes count
                            $comments[$likeIndex]['likes'] = count($comments[$likeIndex]['liked_by']);
                            file_put_contents($commentsFile, json_encode($comments, JSON_PRETTY_PRINT));
                        }
                    }
                }
                
                // Handle reply submission
                if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reply_content']) && isset($_POST['parent_id']) && $loggedIn && !isset($_POST['comment'])) {
                    $parentId = $_POST['parent_id'];
                    $replyContent = htmlspecialchars(trim($_POST['reply_content']));
                    $replyUser = isset($_SESSION['username']) ? $_SESSION['username'] : 'Anonymous';
                    
                    if (!empty($replyContent) && file_exists($commentsFile)) {
                        $comments = json_decode(file_get_contents($commentsFile), true);
                        
                        $parentIndex = -1;
                        foreach ($comments as $index => $cmt) {
                            if (isset($cmt['id']) && $cmt['id'] === $parentId) {
                                $parentIndex = $index;
                                break;
                            }
                        }

                        if ($parentIndex !== -1) {
                            if (!isset($comments[$parentIndex]['replies'])) {
                                $comments[$parentIndex]['replies'] = [];
                            }
                            
                            // Handle reply image upload
                            $replyImageName = '';
                            if (isset($_FILES['reply_image']) && $_FILES['reply_image']['error'] == UPLOAD_ERR_OK) {
                                $tmpName = $_FILES['reply_image']['tmp_name'];
                                $fileSize = $_FILES['reply_image']['size'];
                                $mimeType = mime_content_type($tmpName);
                                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                                
                                if ($fileSize <= 5 * 1024 * 1024 && in_array($mimeType, $allowedTypes)) {
                                    $ext = pathinfo($_FILES['reply_image']['name'], PATHINFO_EXTENSION);
                                    $replyImageName = uniqid('rimg_') . '.' . $ext;
                                    move_uploaded_file($tmpName, $uploadsDir . DIRECTORY_SEPARATOR . $replyImageName);
                                }
                            }
                            
                            $newReply = [
                                'id' => uniqid('rpl_'),
                                'name' => $replyUser,
                                'comment' => $replyContent,
                                'date' => date("Y-m-d H:i"),
                                'created_by' => $replyUser
                            ];
                            
                            if (!empty($replyImageName)) {
                                $newReply['image'] = $replyImageName;
                            }
                            
                            $comments[$parentIndex]['replies'][] = $newReply;
                            file_put_contents($commentsFile, json_encode($comments, JSON_PRETTY_PRINT));
                            
                            // Notification logic for reply
                            $parentAuthor = $comments[$parentIndex]['created_by'] ?? $comments[$parentIndex]['name'];
                            if ($parentAuthor !== $replyUser) {
                                $notificationsFile = 'notifications.json';
                                $notifications = [];
                                if (file_exists($notificationsFile)) {
                                    $notifications = json_decode(file_get_contents($notificationsFile), true) ?? [];
                                }
                                $notifications[] = [
                                    'tagged_user' => $parentAuthor,
                                    'tagged_by' => $replyUser,
                                    'message' => "$replyUser replied to your post",
                                    'comment_preview' => substr($replyContent, 0, 100),
                                    'date' => date('Y-m-d H:i:s'),
                                    'read' => false
                                ];
                                file_put_contents($notificationsFile, json_encode($notifications, JSON_PRETTY_PRINT));
                            }
                        }
                    }
                }

                // Handle comment deletion
                if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_id'])) {
                    $deleteId = $_POST['delete_id'];
                    
                    if (file_exists($commentsFile)) {
                        $comments = json_decode(file_get_contents($commentsFile), true);
                        
                        $deleteIndex = -1;
                        foreach ($comments as $index => $cmt) {
                            if (isset($cmt['id']) && $cmt['id'] === $deleteId) {
                                $deleteIndex = $index;
                                break;
                            }
                        }

                        if ($deleteIndex !== -1) {
                        // Delete associated image if exists
                        if (isset($comments[$deleteIndex]['image']) && !empty($comments[$deleteIndex]['image'])) {
                            $imagePath = $uploadsDir . DIRECTORY_SEPARATOR . $comments[$deleteIndex]['image'];
                            if (file_exists($imagePath)) {
                                unlink($imagePath);
                            }
                        }
                        
                        // Remove comment at index
                            array_splice($comments, $deleteIndex, 1);
                            file_put_contents($commentsFile, json_encode($comments, JSON_PRETTY_PRINT));
                        }
                    }
                }

                // Handle reply deletion
                if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_reply_id']) && isset($_POST['parent_id'])) {
                    $deleteReplyId = $_POST['delete_reply_id'];
                    $parentId = $_POST['parent_id'];
                    
                    if (file_exists($commentsFile)) {
                        $comments = json_decode(file_get_contents($commentsFile), true);
                        
                        $parentIndex = -1;
                        foreach ($comments as $index => $cmt) {
                            if (isset($cmt['id']) && $cmt['id'] === $parentId) {
                                $parentIndex = $index;
                                break;
                            }
                        }

                        if ($parentIndex !== -1 && isset($comments[$parentIndex]['replies'])) {
                            $replyIndex = -1;
                            foreach ($comments[$parentIndex]['replies'] as $rIndex => $r) {
                                if (isset($r['id']) && $r['id'] === $deleteReplyId) {
                                    $replyIndex = $rIndex;
                                    break;
                                }
                            }

                            if ($replyIndex !== -1) {
                                $reply = $comments[$parentIndex]['replies'][$replyIndex];
                                $currentUsername = isset($_SESSION['username']) ? $_SESSION['username'] : null;
                                
                                // Check ownership
                                if ($currentUsername && isset($reply['created_by']) && $reply['created_by'] === $currentUsername) {
                                    // Delete image if exists
                                    if (isset($reply['image']) && !empty($reply['image'])) {
                                        $imagePath = $uploadsDir . DIRECTORY_SEPARATOR . $reply['image'];
                                        if (file_exists($imagePath)) {
                                            unlink($imagePath);
                                        }
                                    }
                                    
                                    // Remove reply
                                    array_splice($comments[$parentIndex]['replies'], $replyIndex, 1);
                                    file_put_contents($commentsFile, json_encode($comments, JSON_PRETTY_PRINT));
                                }
                            }
                        }
                    }
                }
                
                // Handle comment submission
                if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['comment']) && !isset($_POST['delete_id']) && !isset($_POST['like_id'])) {
                    // Use logged-in user's info from session
                    $name = isset($_SESSION['username']) ? $_SESSION['username'] : 'Anonymous';
                    $age = isset($_SESSION['age']) ? intval($_SESSION['age']) : 0;
                    $comment = htmlspecialchars($_POST['comment']);
                    $date = date("Y-m-d H:i");
                    $imageName = '';
                    $notificationsFile = 'notifications.json';
                    $usersFile = 'users.json';
                        
                        // Handle image upload
                        if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
                            $tmpName = $_FILES['image']['tmp_name'];
                            $origName = basename($_FILES['image']['name']);
                            $fileSize = $_FILES['image']['size'];
                            
                            // Validate image
                            $maxSize = 5 * 1024 * 1024; // 5MB
                            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                            $mimeType = mime_content_type($tmpName);
                            
                            if ($fileSize > $maxSize) {
                                $error = 'Image size must be less than 5MB.';
                            } elseif (!in_array($mimeType, $allowedTypes)) {
                                $error = 'Only JPEG, PNG, GIF, and WebP images are allowed.';
                            } else {
                                // Generate unique filename
                                $ext = pathinfo($origName, PATHINFO_EXTENSION);
                                $imageName = uniqid('img_') . '.' . $ext;
                                $uploadPath = $uploadsDir . DIRECTORY_SEPARATOR . $imageName;
                                
                                if (move_uploaded_file($tmpName, $uploadPath)) {
                                    // Success - image uploaded
                                } else {
                                    $error = 'Failed to upload image.';
                                    $imageName = '';
                                }
                            }
                        }
                        
                        if (empty($error)) {
                            $comments = [];
                            
                            // Load existing comments
                            if (file_exists($commentsFile)) {
                                $comments = json_decode(file_get_contents($commentsFile), true);
                            }
                            
                            // Add new comment
                            $newComment = [
                                'id' => uniqid('cmt_'),
                                'name' => $name,
                                'comment' => $comment,
                                'date' => $date,
                                'created_by' => isset($_SESSION['username']) ? $_SESSION['username'] : 'anonymous'
                            ];
                            
                            if (!empty($imageName)) {
                                $newComment['image'] = $imageName;
                            }
                            
                            $newComment['likes'] = 0;
                            $newComment['liked_by'] = [];
                            $comments[] = $newComment;
                            
                            // Save comments
                            file_put_contents($commentsFile, json_encode($comments, JSON_PRETTY_PRINT));
                            
                            // Parse mentions and create notifications
                            preg_match_all('/@(\w+)/', $_POST['comment'], $matches);
                            if (!empty($matches[1])) {
                                $notifications = [];
                                if (file_exists($notificationsFile)) {
                                    $notifications = json_decode(file_get_contents($notificationsFile), true) ?? [];
                                }
                                
                                // Get list of valid users
                                $validUsers = [];
                                if (file_exists($usersFile)) {
                                    $users = json_decode(file_get_contents($usersFile), true) ?? [];
                                    foreach ($users as $user) {
                                        $validUsers[] = $user['username'];
                                    }
                                }
                                
                                $taggedUsers = array_unique($matches[1]);
                                foreach ($taggedUsers as $taggedUser) {
                                    if (in_array($taggedUser, $validUsers) && $taggedUser !== $name) {
                                        $notifications[] = [
                                            'tagged_user' => $taggedUser,
                                            'tagged_by' => $name,
                                            'message' => "$name mentioned you in a comment",
                                            'comment_preview' => substr($_POST['comment'], 0, 200),
                                            'date' => date('Y-m-d H:i:s'),
                                            'read' => false
                                        ];
                                    }
                                }
                                
                                file_put_contents($notificationsFile, json_encode($notifications, JSON_PRETTY_PRINT));
                            }
                        }
                }
                // Display comments
                if (file_exists($commentsFile)) {
                    $comments = json_decode(file_get_contents($commentsFile), true);
                    
                    // Filter by search query
                    if (!empty($searchQuery)) {
                        $comments = array_filter($comments, function($c) use ($searchQuery) {
                            return (stripos($c['name'], $searchQuery) !== false) || 
                                   (stripos($c['comment'], $searchQuery) !== false);
                        });
                    }
                    
                    $totalComments = count($comments);
                    
                    // Display in reverse order (newest first)
                    foreach (array_reverse($comments, true) as $index => $c) {
                        $commentId = isset($c['id']) ? $c['id'] : $index;
                        
                        // Get avatar
                        $postUser = $c['name'];
                        $avatarUrl = isset($userMap[$postUser]['avatar']) ? 'uploads/' . $userMap[$postUser]['avatar'] : null;
                        
                        echo '<div class="post-card">';
                        
                        // Header
                        echo '<div class="post-header">';
                        echo '<a href="profile.php?user=' . urlencode($postUser) . '" style="text-decoration: none;">';
                        if ($avatarUrl) {
                            echo '<img src="' . htmlspecialchars($avatarUrl) . '" class="user-avatar" alt="' . htmlspecialchars($postUser) . '">';
                        } else {
                            echo '<div class="user-avatar">' . strtoupper(substr($postUser, 0, 1)) . '</div>';
                        }
                        echo '</a>';
                        
                        echo '<div class="post-info">';
                        echo '<a href="profile.php?user=' . urlencode($postUser) . '" class="post-author" style="text-decoration: none; color: #fff;">' . htmlspecialchars($postUser) . '</a>';
                        echo '<div class="post-meta">' . $c['date'] . '</div>';
                        echo '</div>'; // end post-info
                        echo '</div>'; // end post-header
                        
                        // Content
                        echo '<div class="post-content">' . nl2br($c['comment']) . '</div>';
                        
                        // Display image if exists
                        if (isset($c['image']) && !empty($c['image'])) {
                            $imagePath = 'uploads/' . htmlspecialchars($c['image']);
                            echo '<div class="post-image-container">';
                            echo '<img src="' . $imagePath . '" alt="Post image" class="post-image">';
                            echo '</div>';
                        }
                        
                        // Footer / Actions
                        echo '<div class="post-footer">';
                        
                        // Display actions (like and delete)
                        $likeCount = isset($c['likes']) ? $c['likes'] : 0;
                        $userHasLiked = false;
                        $currentUsername = isset($_SESSION['username']) ? $_SESSION['username'] : null;
                        
                        if ($currentUsername && isset($c['liked_by']) && is_array($c['liked_by'])) {
                            $userHasLiked = in_array($currentUsername, $c['liked_by']);
                        }
                        
                        // Like Button
                        if ($loggedIn) {
                            echo '<form method="POST" style="display:inline;">';
                            echo '<input type="hidden" name="like_id" value="' . htmlspecialchars($commentId) . '">';
                            $buttonClass = $userHasLiked ? 'action-btn liked' : 'action-btn';
                            $heart = $userHasLiked ? '❤️' : '🤍';
                            echo '<button type="submit" class="' . $buttonClass . '">' . $heart . ' ' . $likeCount . '</button>';
                            echo '</form>';
                        } else {
                            echo '<span class="action-btn">❤️ ' . $likeCount . '</span>';
                        }
                        
                        // Reply Button
                        if ($loggedIn) {
                            echo '<button class="action-btn" onclick="toggleReplyForm(\'' . htmlspecialchars($commentId) . '\')">💬 Reply</button>';
                        }

                        // Only show delete button if current user created the comment
                        $commentCreator = isset($c['created_by']) ? $c['created_by'] : 'anonymous';
                        $isCreator = $loggedIn && $currentUser && isset($currentUser['username']) && $currentUser['username'] === $commentCreator;
                        
                        if ($isCreator) {
                            echo '<form method="POST" style="display:inline;" onsubmit="return confirm(\'Delete this comment?\');">';
                            echo '<input type="hidden" name="delete_id" value="' . htmlspecialchars($commentId) . '">';
                            echo '<button type="submit" class="action-btn delete">🗑️ Delete</button>';
                            echo '</form>';
                        }
                        
                        echo '</div>'; // end post-footer

                        // Replies Section
                        echo '<div class="replies-section">';
                        
                        // Display existing replies
                        if (!empty($c['replies'])) {
                            foreach ($c['replies'] as $reply) {
                                $replyUser = $reply['name'];
                                $replyAvatar = isset($userMap[$replyUser]['avatar']) ? 'uploads/' . $userMap[$replyUser]['avatar'] : null;
                                
                                echo '<div class="reply-item">';
                                if ($replyAvatar) {
                                    echo '<img src="' . htmlspecialchars($replyAvatar) . '" class="reply-avatar">';
                                } else {
                                    echo '<div class="reply-avatar">' . strtoupper(substr($replyUser, 0, 1)) . '</div>';
                                }
                                echo '<div class="reply-content-box">';
                                echo '<div><span class="reply-author">' . htmlspecialchars($replyUser) . '</span> <span class="reply-date">' . $reply['date'] . '</span></div>';
                                echo '<div class="reply-text">' . nl2br(htmlspecialchars($reply['comment'])) . '</div>';
                                
                                if (isset($reply['image']) && !empty($reply['image'])) {
                                    echo '<img src="uploads/' . htmlspecialchars($reply['image']) . '" class="reply-image">';
                                }
                                
                                if ($loggedIn) {
                                    echo '<div class="reply-actions">';
                                    echo '<button class="action-btn small" onclick="replyToUser(\'' . htmlspecialchars($commentId) . '\', \'' . htmlspecialchars($replyUser) . '\')">Reply</button>';
                                    
                                    $currentUsername = isset($_SESSION['username']) ? $_SESSION['username'] : null;
                                    if ($currentUsername && isset($reply['created_by']) && $reply['created_by'] === $currentUsername) {
                                        echo '<form method="POST" style="display:inline;" onsubmit="return confirm(\'Delete this reply?\');">';
                                        echo '<input type="hidden" name="delete_reply_id" value="' . htmlspecialchars($reply['id']) . '">';
                                        echo '<input type="hidden" name="parent_id" value="' . htmlspecialchars($commentId) . '">';
                                        echo '<button type="submit" class="action-btn small delete" style="margin-left: 5px;">Delete</button>';
                                        echo '</form>';
                                    }
                                    echo '</div>';
                                }
                                
                                echo '</div>';
                                echo '</div>';
                            }
                        }

                        // Reply Form
                        if ($loggedIn) {
                            echo '<div id="reply-form-' . htmlspecialchars($commentId) . '" class="reply-form-container">';
                            echo '<form method="POST" class="reply-input-group" enctype="multipart/form-data">';
                            echo '<input type="hidden" name="parent_id" value="' . htmlspecialchars($commentId) . '">';
                            echo '<input type="text" name="reply_content" class="reply-input" placeholder="Write a reply..." required autocomplete="off">';
                            echo '<label class="reply-file-label" title="Attach Image">';
                            echo '📷 <input type="file" name="reply_image" accept="image/*" style="display: none;">';
                            echo '</label>';
                            echo '<button type="submit" class="reply-submit-btn">Send</button>';
                            echo '</form>';
                            echo '</div>';
                        }
                        
                        echo '</div>'; // end replies-section

                        echo '</div>'; // end post-card
                    }
                }
            ?>
        </div>
        
        <script>
            // Update file name display when image is selected
            document.getElementById('image').addEventListener('change', function(e) {
                const fileName = document.getElementById('file-name');
                if (this.files && this.files[0]) {
                    fileName.textContent = 'Selected: ' + this.files[0].name;
                } else {
                    fileName.textContent = '';
                }
            });

            function toggleReplyForm(id) {
                const form = document.getElementById('reply-form-' + id);
                if (form) {
                    form.classList.toggle('active');
                    if (form.classList.contains('active')) {
                        const input = form.querySelector('input[type="text"]');
                        if (input) input.focus();
                    }
                }
            }
            
            function replyToUser(parentId, username) {
                toggleReplyForm(parentId);
                const form = document.getElementById('reply-form-' + parentId);
                const input = form.querySelector('input[name="reply_content"]');
                input.value = '@' + username + ' ';
                input.focus();
            }
        </script>
    </div>
</body>
</html>