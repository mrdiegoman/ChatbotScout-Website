<?php
/**
 * AJAX Endpoint - Toggle Favorite
 *
 * Pridá alebo odstráni chatbota z favorites tabuľky.
 * Vracia: { success, is_favourite, new_count }
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../../inc/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Musí byť prihlásený
if (!$isLoggedIn || empty($user['id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in', 'redirect' => '../public/login.php?redirect=chatbotShowcase.php']);
    exit;
}

validateCsrfToken();

$data      = json_decode(file_get_contents('php://input'), true);
$chatbotId = trim($data['chatbot_id'] ?? '');

if (empty($chatbotId)) {
    echo json_encode(['success' => false, 'message' => 'Missing chatbot_id']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Skontroluj či chatbot existuje
    $checkStmt = $db->prepare("
        SELECT id FROM `chatbots`
        WHERE id = :id AND status = 'active' AND deleted_at IS NULL
    ");
    $checkStmt->execute([':id' => $chatbotId]);
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Chatbot not found']);
        exit;
    }

    // Skontroluj či už je v obľúbených
    $existsStmt = $db->prepare("
        SELECT id FROM `favorites`
        WHERE chatbot_id = :chatbot_id AND user_id = :user_id
    ");
    $existsStmt->execute([
        ':chatbot_id' => $chatbotId,
        ':user_id'    => $user['id'],
    ]);
    $existingFav = $existsStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingFav) {
        // Odstráň z obľúbených
        $deleteStmt = $db->prepare("
            DELETE FROM `favorites`
            WHERE chatbot_id = :chatbot_id AND user_id = :user_id
        ");
        $deleteStmt->execute([
            ':chatbot_id' => $chatbotId,
            ':user_id'    => $user['id'],
        ]);
        $isFavourite = false;

    } else {
        // Pridaj do obľúbených
        $newId = generateUUID();

        $insertStmt = $db->prepare("
            INSERT INTO `favorites` (`id`, `chatbot_id`, `user_id`)
            VALUES (:id, :chatbot_id, :user_id)
        ");
        $insertStmt->execute([
            ':id'         => $newId,
            ':chatbot_id' => $chatbotId,
            ':user_id'    => $user['id'],
        ]);
        $isFavourite = true;
    }

    // Načítaj aktuálny favorite_count z chatbots (trigger ho aktualizuje automaticky)
    $countStmt = $db->prepare("SELECT favorite_count FROM `chatbots` WHERE id = :id");
    $countStmt->execute([':id' => $chatbotId]);
    $newCount = (int)($countStmt->fetchColumn() ?? 0);

    echo json_encode([
        'success'      => true,
        'is_favourite' => $isFavourite,
        'new_count'    => $newCount,
    ]);

} catch (Exception $e) {
    error_log('toggleFavorite error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}