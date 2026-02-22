<?php
require_once 'session_handler.php';
requireLogin();

$currentUser = getCurrentUser();
$username = $currentUser['username'];
$error = '';
$success = '';

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $recipient = isset($_POST['recipient']) ? trim($_POST['recipient']) : '';
    $messageBody = isset($_POST['message']) ? trim($_POST['message']) : '';

    if (empty($recipient)) {
        $error = 'Recipient username is required.';
    } elseif (empty($messageBody)) {
        $error = 'Message cannot be empty.';
    } elseif ($recipient === $username) {
        $error = 'You cannot send a message to yourself.';
    } else {
        // Check if recipient exists
        if (!getUserByUsername($recipient)) {
            $error = 'Recipient username not found.';
        } else {
            // Save message
            $messagesFile = 'messages.json';
            $messages = [];
            
            if (file_exists($messagesFile)) {
                $messages = json_decode(file_get_contents($messagesFile), true) ?? [];
            }

            $newMessage = [
                'id' => uniqid('msg_'),
                'sender' => $username,
                'recipient' => $recipient,
                'message' => htmlspecialchars($messageBody),
                'timestamp' => date("Y-m-d H:i:s"),
                'read' => false
            ];

            $messages[] = $newMessage;
            
            if (file_put_contents($messagesFile, json_encode($messages, JSON_PRETTY_PRINT))) {
                // Redirect to refresh the chat view
                header("Location: messages.php?to=" . urlencode($recipient));
                exit;
            } else {
                $error = 'Failed to send message.';
            }
        }
    }
}

// Load messages
$messagesFile = 'messages.json';
$allMessages = [];
if (file_exists($messagesFile)) {
    $allMessages = json_decode(file_get_contents($messagesFile), true) ?? [];
}

// Process messages for conversation view
$conversations = [];
$activeChatUser = isset($_GET['to']) ? $_GET['to'] : null;
$chatHistory = [];

foreach ($allMessages as $msg) {
    $sender = $msg['sender'];
    $recipient = $msg['recipient'];
    
    // Filter messages involving current user
    if ($sender !== $username && $recipient !== $username) {
        continue;
    }
    
    // Determine the partner
    $partner = ($sender === $username) ? $recipient : $sender;
    
    // Add/Update conversation info
    if (!isset($conversations[$partner])) {
        $conversations[$partner] = [
            'username' => $partner,
            'last_msg_time' => $msg['timestamp'],
            'preview' => $msg['message']
        ];
    } else {
        // Update if this message is newer
        if (strtotime($msg['timestamp']) > strtotime($conversations[$partner]['last_msg_time'])) {
            $conversations[$partner]['last_msg_time'] = $msg['timestamp'];
            $conversations[$partner]['preview'] = $msg['message'];
        }
    }
    
    // If this is the active chat, add to history
    if ($activeChatUser && $partner === $activeChatUser) {
        $chatHistory[] = $msg;
    }
}

// Sort conversations by time (newest first)
usort($conversations, function($a, $b) {
    return strtotime($b['last_msg_time']) - strtotime($a['last_msg_time']);
});

// Sort chat history by time (oldest first) for the chat window
usort($chatHistory, function($a, $b) {
    return strtotime($a['timestamp']) - strtotime($b['timestamp']);
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galactic Chat</title>
    <style>
        /* Reset and Base */
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            background-color: #000;
            background-image: url('https://images.unsplash.com/photo-1534796636912-3b95b3ab5986?ixlib=rb-4.0.3&auto=format&fit=crop&w=2342&q=80'); /* Starry night / Nebula */
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: #e0e0e0;
            display: flex;
            flex-direction: column;
        }

        /* Navbar */
        .navbar {
            background-color: rgba(20, 10, 30, 0.9);
            padding: 10px 20px;
            border-bottom: 1px solid #4a2c60;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(5px);
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

        /* Main Chat Layout */
        .chat-container {
            display: flex;
            flex: 1;
            max-width: 1200px;
            margin: 20px auto;
            width: 95%;
            background: rgba(26, 11, 46, 0.85); /* Semi-transparent dark purple */
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(124, 77, 255, 0.3);
            border: 1px solid #4a2c60;
            backdrop-filter: blur(10px);
            height: calc(100vh - 100px);
        }

        /* Sidebar */
        .sidebar {
            width: 300px;
            background-color: rgba(15, 5, 25, 0.6);
            border-right: 1px solid #4a2c60;
            display: flex;
            flex-direction: column;
        }
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #4a2c60;
            background: rgba(20, 10, 30, 0.5);
        }
        .sidebar-header h3 { margin: 0; color: #b388ff; }
        
        .new-chat-form {
            padding: 15px;
            border-bottom: 1px solid #4a2c60;
        }
        .new-chat-form input {
            width: 100%;
            padding: 10px;
            background: rgba(0,0,0,0.3);
            border: 1px solid #4a2c60;
            border-radius: 20px;
            color: white;
            outline: none;
        }
        .new-chat-form input::placeholder { color: #888; }
        .new-chat-form input:focus { border-color: #7c4dff; }

        .conversation-list {
            flex: 1;
            overflow-y: auto;
        }
        .conversation-item {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(74, 44, 96, 0.3);
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .conversation-item:hover { background-color: rgba(124, 77, 255, 0.1); }
        .conversation-item.active {
            background-color: rgba(124, 77, 255, 0.2);
            border-left: 4px solid #7c4dff;
        }
        .avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #7c4dff, #b388ff);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        .conv-info { flex: 1; overflow: hidden; }
        .conv-name { font-weight: bold; color: #fff; margin-bottom: 4px; display: block; }
        .conv-preview { font-size: 12px; color: #aaa; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .conv-time { font-size: 11px; color: #777; }

        /* Chat Area */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: rgba(0, 0, 0, 0.2);
            position: relative;
        }
        
        .chat-header {
            padding: 15px 20px;
            background: rgba(20, 10, 30, 0.8);
            border-bottom: 1px solid #4a2c60;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .chat-header h2 { margin: 0; font-size: 18px; color: #fff; }
        
        .messages-list {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .message-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            position: relative;
            line-height: 1.4;
            word-wrap: break-word;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .message-bubble.sent {
            align-self: flex-end;
            background: linear-gradient(135deg, #6200ea, #7c4dff);
            color: white;
            border-bottom-right-radius: 4px;
            box-shadow: 0 4px 15px rgba(98, 0, 234, 0.3);
        }
        
        .message-bubble.received {
            align-self: flex-start;
            background: #2d1b4e;
            color: #e0e0e0;
            border-bottom-left-radius: 4px;
            border: 1px solid #4a2c60;
        }
        
        .message-time {
            font-size: 10px;
            margin-top: 5px;
            opacity: 0.7;
            text-align: right;
        }
        
        .chat-input-area {
            padding: 20px;
            background: rgba(20, 10, 30, 0.8);
            border-top: 1px solid #4a2c60;
        }
        .chat-form {
            display: flex;
            gap: 10px;
        }
        .chat-form textarea {
            flex: 1;
            background: rgba(0,0,0,0.3);
            border: 1px solid #4a2c60;
            border-radius: 20px;
            padding: 12px 20px;
            color: white;
            resize: none;
            outline: none;
            font-family: inherit;
        }
        .chat-form textarea:focus { border-color: #7c4dff; background: rgba(0,0,0,0.5); }
        .send-btn {
            background: #7c4dff;
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            cursor: pointer;
            transition: transform 0.2s, background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .send-btn:hover { background: #651fff; transform: scale(1.05); box-shadow: 0 0 15px rgba(124, 77, 255, 0.5); }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); }
        ::-webkit-scrollbar-thumb { background: #4a2c60; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #7c4dff; }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #888;
            text-align: center;
        }
        .empty-state h3 { color: #b388ff; margin-bottom: 10px; }
        
        .error-banner {
            background: rgba(220, 53, 69, 0.8);
            color: white;
            padding: 10px;
            text-align: center;
            border-radius: 4px;
            margin: 10px;
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

    <div class="chat-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>Messages</h3>
            </div>
            
            <div class="new-chat-form">
                <form action="messages.php" method="GET">
                    <input type="text" name="to" placeholder="Search user or start new..." value="<?php echo isset($_GET['to']) ? htmlspecialchars($_GET['to']) : ''; ?>">
                </form>
            </div>

            <div class="conversation-list">
                <?php if (empty($conversations)): ?>
                    <div style="padding: 20px; text-align: center; color: #666; font-style: italic;">No conversations yet.</div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                        <?php $isActive = ($activeChatUser === $conv['username']); ?>
                        <div class="conversation-item <?php echo $isActive ? 'active' : ''; ?>" onclick="window.location.href='messages.php?to=<?php echo urlencode($conv['username']); ?>'">
                            <div class="avatar"><?php echo strtoupper(substr($conv['username'], 0, 1)); ?></div>
                            <div class="conv-info">
                                <span class="conv-name"><?php echo htmlspecialchars($conv['username']); ?></span>
                                <div class="conv-preview"><?php echo htmlspecialchars($conv['preview']); ?></div>
                            </div>
                            <div class="conv-time">
                                <?php 
                                    $time = strtotime($conv['last_msg_time']);
                                    echo (date('Y-m-d') == date('Y-m-d', $time)) ? date('H:i', $time) : date('M d', $time);
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="chat-area">
            <?php if (!empty($error)): ?>
                <div class="error-banner"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($activeChatUser): ?>
                <div class="chat-header">
                    <div class="avatar"><?php echo strtoupper(substr($activeChatUser, 0, 1)); ?></div>
                    <div>
                        <h2><?php echo htmlspecialchars($activeChatUser); ?></h2>
                        <span style="font-size: 12px; color: #aaa;">Intergalactic connection established</span>
                    </div>
                </div>

                <div class="messages-list" id="messagesList">
                    <?php if (empty($chatHistory)): ?>
                        <div class="empty-state">
                            <p>No messages yet.</p>
                            <p>Send a signal to start the conversation.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($chatHistory as $msg): ?>
                            <?php $isMe = ($msg['sender'] === $username); ?>
                            <div class="message-bubble <?php echo $isMe ? 'sent' : 'received'; ?>">
                                <?php echo nl2br($msg['message']); ?>
                                <div class="message-time"><?php echo date('H:i', strtotime($msg['timestamp'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="chat-input-area">
                    <form method="POST" class="chat-form">
                        <input type="hidden" name="send_message" value="1">
                        <input type="hidden" name="recipient" value="<?php echo htmlspecialchars($activeChatUser); ?>">
                        <textarea name="message" rows="1" placeholder="Type a message..." required onkeydown="if(event.keyCode == 13 && !event.shiftKey) { this.form.submit(); return false; }"></textarea>
                        <button type="submit" class="send-btn">➤</button>
                    </form>
                </div>
                
                <script>
                    // Auto-scroll to bottom
                    var msgList = document.getElementById('messagesList');
                    msgList.scrollTop = msgList.scrollHeight;
                </script>
            <?php else: ?>
                <div class="empty-state">
                    <div style="font-size: 50px; margin-bottom: 20px;">🪐</div>
                    <h3>Welcome to GalaxyConnect</h3>
                    <p>Select a conversation from the left or start a new one.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
