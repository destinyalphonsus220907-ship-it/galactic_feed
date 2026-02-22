<?php
require_once 'session_handler.php';

// Redirect if not logged in
requireLogin('index.php');

$loggedIn = isUserLoggedIn();
$currentUser = getCurrentUser();
$currentUsername = $currentUser['username'] ?? '';

$friendsFile = 'friends.json';
$usersFile = 'users.json';

// Initialize friends file if it doesn't exist
if (!file_exists($friendsFile)) {
    file_put_contents($friendsFile, json_encode([], JSON_PRETTY_PRINT));
}

// Load friends data
$friendsData = json_decode(file_get_contents($friendsFile), true) ?? [];

// Handle friend request actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Send friend request
    if (isset($_POST['add_friend'])) {
        $targetUser = sanitizeInput($_POST['add_friend']);
        
        if ($targetUser !== $currentUsername && userExists($targetUser, $usersFile)) {
            $requestKey = "$currentUsername:$targetUser";
            
            // Check if request already exists
            if (!isset($friendsData[$requestKey]) && !isset($friendsData["$targetUser:$currentUsername"])) {
                $friendsData[$requestKey] = [
                    'status' => 'pending',
                    'from' => $currentUsername,
                    'to' => $targetUser,
                    'date' => date('Y-m-d H:i:s')
                ];
                file_put_contents($friendsFile, json_encode($friendsData, JSON_PRETTY_PRINT));
                $success = "Friend request sent to $targetUser";
            } else {
                $error = "Friend request already exists or already friends.";
            }
        } else {
            $error = "User not found.";
        }
    }
    
    // Accept friend request
    if (isset($_POST['accept_request'])) {
        $requesterUsername = sanitizeInput($_POST['accept_request']);
        $requestKey = "$requesterUsername:$currentUsername";
        
        if (isset($friendsData[$requestKey]) && $friendsData[$requestKey]['status'] == 'pending') {
            $friendsData[$requestKey]['status'] = 'accepted';
            file_put_contents($friendsFile, json_encode($friendsData, JSON_PRETTY_PRINT));
            $success = "You are now friends with $requesterUsername";
        }
    }
    
    // Reject friend request
    if (isset($_POST['reject_request'])) {
        $requesterUsername = sanitizeInput($_POST['reject_request']);
        $requestKey = "$requesterUsername:$currentUsername";
        
        if (isset($friendsData[$requestKey])) {
            unset($friendsData[$requestKey]);
            file_put_contents($friendsFile, json_encode($friendsData, JSON_PRETTY_PRINT));
            $success = "Friend request rejected.";
        }
    }
    
    // Unfriend
    if (isset($_POST['unfriend'])) {
        $friendUsername = sanitizeInput($_POST['unfriend']);
        $key1 = "$currentUsername:$friendUsername";
        $key2 = "$friendUsername:$currentUsername";
        
        if (isset($friendsData[$key1])) unset($friendsData[$key1]);
        if (isset($friendsData[$key2])) unset($friendsData[$key2]);
        
        file_put_contents($friendsFile, json_encode($friendsData, JSON_PRETTY_PRINT));
        $success = "Unfriended $friendUsername.";
    }
}

// Get current user's friends and pending requests
function getUserFriends($username, $friendsData) {
    $friends = [];
    foreach ($friendsData as $key => $relationship) {
        if ($relationship['status'] == 'accepted') {
            if ($relationship['from'] === $username) {
                $friends[] = $relationship['to'];
            } elseif ($relationship['to'] === $username) {
                $friends[] = $relationship['from'];
            }
        }
    }
    return $friends;
}

function getPendingRequests($username, $friendsData) {
    $requests = [];
    foreach ($friendsData as $key => $relationship) {
        if ($relationship['status'] == 'pending' && $relationship['to'] === $username) {
            $requests[] = $relationship['from'];
        }
    }
    return $requests;
}

function getPendingSent($username, $friendsData) {
    $requests = [];
    foreach ($friendsData as $key => $relationship) {
        if ($relationship['status'] == 'pending' && $relationship['from'] === $username) {
            $requests[] = $relationship['to'];
        }
    }
    return $requests;
}

function userExists($username, $usersFile) {
    if (!file_exists($usersFile)) return false;
    $users = json_decode(file_get_contents($usersFile), true) ?? [];
    foreach ($users as $user) {
        if ($user['username'] === $username) return true;
    }
    return false;
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input));
}

$currentFriends = getUserFriends($currentUsername, $friendsData);
$pendingRequests = getPendingRequests($currentUsername, $friendsData);
$sentRequests = getPendingSent($currentUsername, $friendsData);

// Get all users for adding friends
$allUsers = [];
if (file_exists($usersFile)) {
    $users = json_decode(file_get_contents($usersFile), true) ?? [];
    foreach ($users as $user) {
        if ($user['username'] !== $currentUsername) {
            $allUsers[] = $user['username'];
        }
    }
}

$error = $error ?? '';
$success = $success ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Friends</title>
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

        .friends-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .section {
            background: rgba(26, 11, 46, 0.85);
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(124, 77, 255, 0.3);
            border: 1px solid #4a2c60;
            backdrop-filter: blur(10px);
        }
        .section h2 {
            margin-top: 0;
            color: #fff;
        }
        .user-list {
            display: grid;
            gap: 10px;
        }
        .user-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 4px;
            border-left: 4px solid #7c4dff;
        }
        .user-name {
            font-weight: bold;
            color: #e0e0e0;
        }
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            white-space: nowrap;
        }
        .add-btn {
            background-color: #28a745;
            color: white;
        }
        .add-btn:hover {
            background-color: #218838;
        }
        .accept-btn {
            background-color: #28a745;
            color: white;
        }
        .accept-btn:hover {
            background-color: #218838;
        }
        .reject-btn {
            background-color: #dc3545;
            color: white;
        }
        .reject-btn:hover {
            background-color: #c82333;
        }
        .unfriend-btn {
            background-color: #ffc107;
            color: #333;
        }
        .unfriend-btn:hover {
            background-color: #ffb300;
        }
        .pending-badge {
            background-color: #ffc107;
            color: #333;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
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
            padding: 20px;
            font-style: italic;
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
            <a href="logout.php">Log Out (<?php echo htmlspecialchars($currentUsername); ?>)</a>
        </div>
    </div>

    <div class="friends-container">
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Pending Requests Section -->
        <div class="section">
            <h2>Friend Requests (<?php echo count($pendingRequests); ?>)</h2>
            <?php if (empty($pendingRequests)): ?>
                <div class="empty-state">No pending friend requests</div>
            <?php else: ?>
                <div class="user-list">
                    <?php foreach ($pendingRequests as $requester): ?>
                        <div class="user-item">
                            <span class="user-name"><?php echo htmlspecialchars($requester); ?></span>
                            <div style="display: flex; gap: 10px;">
                                <form method="POST" style="display:inline;">
                                    <button type="submit" name="accept_request" value="<?php echo htmlspecialchars($requester); ?>" class="action-btn accept-btn">Accept</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <button type="submit" name="reject_request" value="<?php echo htmlspecialchars($requester); ?>" class="action-btn reject-btn">Reject</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Friends List Section -->
        <div class="section">
            <h2>Friends (<?php echo count($currentFriends); ?>)</h2>
            <?php if (empty($currentFriends)): ?>
                <div class="empty-state">You don't have any friends yet</div>
            <?php else: ?>
                <div class="user-list">
                    <?php foreach ($currentFriends as $friend): ?>
                        <div class="user-item">
                            <span class="user-name"><?php echo htmlspecialchars($friend); ?></span>
                            <form method="POST" style="display:inline;">
                                <button type="submit" name="unfriend" value="<?php echo htmlspecialchars($friend); ?>" class="action-btn unfriend-btn">Unfriend</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Add Friends Section -->
        <div class="section">
            <h2>Find Users</h2>
            <?php if (empty($allUsers)): ?>
                <div class="empty-state">No other users available</div>
            <?php else: ?>
                <div class="user-list">
                    <?php foreach ($allUsers as $user): ?>
                        <?php 
                            $isFriend = in_array($user, $currentFriends);
                            $isRequested = in_array($user, $sentRequests);
                        ?>
                        <div class="user-item">
                            <span class="user-name">
                                <?php echo htmlspecialchars($user); ?>
                                <?php if ($isFriend): ?>
                                    <span class="pending-badge">Friends</span>
                                <?php elseif ($isRequested): ?>
                                    <span class="pending-badge">Request Sent</span>
                                <?php endif; ?>
                            </span>
                            <?php if (!$isFriend && !$isRequested): ?>
                                <form method="POST" style="display:inline;">
                                    <button type="submit" name="add_friend" value="<?php echo htmlspecialchars($user); ?>" class="action-btn add-btn">Add Friend</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
