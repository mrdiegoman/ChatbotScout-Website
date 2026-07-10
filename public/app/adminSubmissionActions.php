<?php
/**
 * Admin Submission Actions
 * Actions: create_admin_chatbot | search_user_by_email | assign_chatbot_owner
 *          validate_chatbot | accept | review | reject | remove | save_notes
 */

require '../../inc/bootstrap.php';
require 'guard.php';

requireRole('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

validateCsrfToken();

$input  = json_decode(file_get_contents('php://input'), true);
$input  = is_array($input) ? $input : [];
$action = $input['action'] ?? '';
$id     = trim($input['id'] ?? '');

$db        = Database::getInstance()->getConnection();
$appConfig = require __DIR__ . '/../../config/app.php';

// ── Helper: get pending count ──────────────────────────────────────────────────
function getPendingCount(PDO $db): int {
    return (int)$db->query("
        SELECT COUNT(*) FROM chatbots
        WHERE status = 'pending'
          AND (submitted_at IS NOT NULL OR created_source = 'admin')
    ")->fetchColumn();
}

function getAdminSubmissionRow(PDO $db, string $id): ?array {
    $stmt = $db->prepare("
        SELECT
            c.id, c.name, c.website_url, c.logo_url,
            c.owner_user_id, c.created_by_admin_id, c.created_source, c.created_at,
            c.status, c.is_verified,
            c.submitted_at, c.approved_at, c.rejected_at, c.deleted_at, c.verified_at,
            c.access_note, c.admin_notes,
            u.first_name, u.last_name, u.email AS user_email,
            u.is_submission_blocked
        FROM chatbots c
        LEFT JOIN users u ON c.owner_user_id = u.id
        WHERE c.id = :id
          AND (c.submitted_at IS NOT NULL OR c.created_source = 'admin')
        LIMIT 1
    ");
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    $row['submitted_at_formatted'] = $row['submitted_at']
        ? date('d M Y, H:i', strtotime($row['submitted_at'])) : null;
    $row['created_at_formatted'] = $row['created_at']
        ? date('d M Y, H:i', strtotime($row['created_at'])) : null;
    $row['display_date_formatted'] = $row['submitted_at_formatted'] ?: $row['created_at_formatted'];
    $row['approved_at_formatted'] = $row['approved_at']
        ? date('d M Y, H:i', strtotime($row['approved_at'])) : null;
    $row['rejected_at_formatted'] = $row['rejected_at']
        ? date('d M Y, H:i', strtotime($row['rejected_at'])) : null;
    $row['deleted_at_formatted'] = $row['deleted_at']
        ? date('d M Y, H:i', strtotime($row['deleted_at'])) : null;
    $row['verified_at_formatted'] = $row['verified_at']
        ? date('d M Y, H:i', strtotime($row['verified_at'])) : null;
    $row['is_verified'] = (bool)$row['is_verified'];
    $row['is_submission_blocked'] = (bool)$row['is_submission_blocked'];
    $row['is_admin_added'] = ($row['created_source'] ?? '') === 'admin' && empty($row['owner_user_id']);

    return $row;
}

function getAdminSubmissionCounts(PDO $db): array {
    $countStmt = $db->query("
        SELECT status, COUNT(*) as cnt
        FROM chatbots
        WHERE status IN ('pending','reviewed','active','rejected','removed')
          AND (submitted_at IS NOT NULL OR created_source = 'admin')
        GROUP BY status
    ");

    $counts = ['all' => 0, 'pending' => 0, 'reviewed' => 0, 'active' => 0, 'rejected' => 0, 'removed' => 0];
    foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isset($counts[$row['status']])) {
            $counts[$row['status']] = (int)$row['cnt'];
        }
        $counts['all'] += (int)$row['cnt'];
    }

    return $counts;
}

// ── Helper: validate chatbot readiness ────────────────────────────────────────
function validateChatbot(PDO $db, string $id): array {
    // Fetch chatbot data
    $stmt = $db->prepare("
        SELECT c.description, c.logo_url, c.affiliate_link,
               c.starting_price, c.pricing_model,
               c.owner_user_id, c.created_source
        FROM chatbots c
        WHERE c.id = :id
    ");
    $stmt->execute(['id' => $id]);
    $chatbot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$chatbot) {
        return ['valid' => false, 'rules' => [], 'error' => 'Chatbot not found.'];
    }

    // Features count
    $featStmt = $db->prepare("
        SELECT COUNT(*) FROM chatbot_features
        WHERE chatbot_id = :id
          AND (boolean_value = 1 OR text_value IS NOT NULL)
    ");
    $featStmt->execute(['id' => $id]);
    $featureCount = (int)$featStmt->fetchColumn();

    // Integrations count
    $intStmt = $db->prepare("
        SELECT COUNT(*) FROM chatbot_integrations
        WHERE chatbot_id = :id AND is_supported = 1
    ");
    $intStmt->execute(['id' => $id]);
    $integrationCount = (int)$intStmt->fetchColumn();

    // A configured price of 0.00 is valid (for example, for free or freemium plans).
    $priceOk = $chatbot['starting_price'] !== null && $chatbot['starting_price'] !== '';

    $rules = [];

    if (($chatbot['created_source'] ?? '') === 'admin' || empty($chatbot['owner_user_id'])) {
        $rules[] = [
            'code'  => 'account_assigned',
            'label' => 'Account is assigned',
            'ok'    => !empty($chatbot['owner_user_id']),
        ];
    }

    $rules = array_merge($rules, [
        [
            'label' => 'Description is set',
            'ok'    => !empty($chatbot['description']),
        ],
        [
            'label' => 'Logo is uploaded',
            'ok'    => !empty($chatbot['logo_url']),
        ],
        [
            'label' => 'Outbound link is set',
            'ok'    => !empty($chatbot['affiliate_link']),
        ],
        [
            'label' => 'Starting price is set',
            'ok'    => $priceOk,
        ],
        [
            'label' => 'At least 1 feature',
            'ok'    => $featureCount >= 1,
        ],
        [
            'label' => 'At least 1 integration',
            'ok'    => $integrationCount >= 1,
        ],
    ]);

    $allPassed = array_reduce($rules, function (bool $carry, array $rule): bool {
        return $carry && ($rule['ok'] || ($rule['skip'] ?? false));
    }, true);

    return [
        'valid' => $allPassed,
        'rules' => $rules,
    ];
}

function cleanNullableString($value, int $maxLength): ?string {
    $value = trim((string)($value ?? ''));
    if ($value === '') {
        return null;
    }

    return mb_substr($value, 0, $maxLength);
}

function uniqueUuidList($values): array {
    $ids = [];
    foreach ((array)$values as $value) {
        $value = trim((string)$value);
        if ($value !== '' && preg_match('/^[0-9a-f-]{36}$/i', $value)) {
            $ids[strtolower($value)] = $value;
        }
    }

    return array_values($ids);
}

function getExistingIds(PDO $db, string $table, array $ids): array {
    if (empty($ids)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT id FROM `$table` WHERE id IN ($placeholders)");
    $stmt->execute($ids);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function findOrCreateNamedItem(PDO $db, string $table, string $name, array $extraValues = []): string {
    $name = trim($name);
    if ($name === '' || mb_strlen($name) > 100) {
        throw new InvalidArgumentException('New feature/integration names must be 1-100 characters.');
    }

    $stmt = $db->prepare("SELECT id FROM `$table` WHERE name = :name LIMIT 1");
    $stmt->execute(['name' => $name]);
    $existingId = $stmt->fetchColumn();
    if ($existingId) {
        return $existingId;
    }

    $id = generateUUID();
    if ($table === 'features') {
        $insert = $db->prepare("
            INSERT INTO features (id, name, advanced)
            VALUES (:id, :name, :advanced)
        ");
        $insert->execute([
            'id'       => $id,
            'name'     => $name,
            'advanced' => !empty($extraValues['advanced']) ? 1 : 0,
        ]);
    } else {
        $insert = $db->prepare("
            INSERT INTO integrations (id, name)
            VALUES (:id, :name)
        ");
        $insert->execute([
            'id'   => $id,
            'name' => $name,
        ]);
    }

    return $id;
}

function getSubmissionEditData(PDO $db, string $id): array {
    $stmt = $db->prepare("
        SELECT
            id, name, website_url, description, logo_url,
            has_affiliate_program, affiliate_link, affiliate_commission_rate,
            affiliate_commission_type, affiliate_cookie_duration,
            has_free_trial, free_trial_days,
            starting_price, pricing_model, pricing_currency, pricing_url,
            status
        FROM chatbots
        WHERE id = :id
          AND (submitted_at IS NOT NULL OR created_source = 'admin')
        LIMIT 1
    ");
    $stmt->execute(['id' => $id]);
    $chatbot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$chatbot) {
        throw new RuntimeException('Submission not found.');
    }

    $features = $db->query("
        SELECT
            f.id,
            f.name,
            f.advanced,
            COUNT(DISTINCT cf.chatbot_id) AS usage_count
        FROM features f
        LEFT JOIN chatbot_features cf ON cf.feature_id = f.id
        GROUP BY f.id, f.name, f.advanced
        ORDER BY f.advanced ASC, f.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $integrations = $db->query("
        SELECT id, name
        FROM integrations
        ORDER BY name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $featureStmt = $db->prepare("
        SELECT feature_id
        FROM chatbot_features
        WHERE chatbot_id = :id
          AND (boolean_value = 1 OR text_value IS NOT NULL OR number_value IS NOT NULL)
    ");
    $featureStmt->execute(['id' => $id]);

    $integrationStmt = $db->prepare("
        SELECT integration_id
        FROM chatbot_integrations
        WHERE chatbot_id = :id AND is_supported = 1
    ");
    $integrationStmt->execute(['id' => $id]);

    foreach ($features as &$feature) {
        $feature['advanced'] = (bool)$feature['advanced'];
        $feature['usage_count'] = (int)$feature['usage_count'];
    }
    unset($feature);

    $chatbot['has_affiliate_program'] = (bool)$chatbot['has_affiliate_program'];
    $chatbot['has_free_trial'] = (bool)$chatbot['has_free_trial'];

    return [
        'chatbot' => $chatbot,
        'features' => $features,
        'integrations' => $integrations,
        'selectedFeatureIds' => $featureStmt->fetchAll(PDO::FETCH_COLUMN),
        'selectedIntegrationIds' => $integrationStmt->fetchAll(PDO::FETCH_COLUMN),
        'validation' => validateChatbot($db, $id),
    ];
}

function saveSubmissionEditData(PDO $db, string $id, array $input): array {
    $fields = (array)($input['fields'] ?? []);

    $name = trim((string)($fields['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 200) {
        throw new InvalidArgumentException('Chatbot name is required and must be max 200 characters.');
    }

    $websiteUrl = trim((string)($fields['website_url'] ?? ''));
    if ($websiteUrl === '' || mb_strlen($websiteUrl) > 500 || !filter_var($websiteUrl, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('Website URL must be a valid URL.');
    }

    $description = cleanNullableString($fields['description'] ?? null, 150);
    $logoUrl = cleanNullableString($fields['logo_url'] ?? null, 500);
    $affiliateLink = cleanNullableString($fields['affiliate_link'] ?? null, 1000);
    $pricingUrl = cleanNullableString($fields['pricing_url'] ?? null, 500);

    foreach ([$affiliateLink, $pricingUrl] as $url) {
        if ($url !== null && !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Affiliate and pricing URLs must be valid URLs when filled.');
        }
    }

    $pricingModels = ['free', 'freemium', 'recurring', 'one-time', 'usage-based', 'custom'];
    $pricingModel = trim((string)($fields['pricing_model'] ?? 'free'));
    if (!in_array($pricingModel, $pricingModels, true)) {
        throw new InvalidArgumentException('Invalid pricing model.');
    }

    $currencies = ['USD', 'EUR', 'GBP'];
    $pricingCurrency = strtoupper(trim((string)($fields['pricing_currency'] ?? 'USD')));
    if (!in_array($pricingCurrency, $currencies, true)) {
        throw new InvalidArgumentException('Invalid pricing currency.');
    }

    $startingPrice = $fields['starting_price'] ?? 0;
    if ($startingPrice === '' || !is_numeric($startingPrice) || (float)$startingPrice < 0 || (float)$startingPrice > 99999999.99) {
        throw new InvalidArgumentException('Starting price must be a positive number or zero.');
    }
    $startingPrice = number_format((float)$startingPrice, 2, '.', '');
    $startingPriceEur = chatbotPriceToEur($db, $startingPrice, $pricingCurrency);

    $hasAffiliateProgram = !empty($fields['has_affiliate_program']) && $affiliateLink !== null;

    $commissionTypes = ['', 'percentage', 'flat', 'cpa'];
    $commissionType = trim((string)($fields['affiliate_commission_type'] ?? ''));
    if (!in_array($commissionType, $commissionTypes, true)) {
        throw new InvalidArgumentException('Invalid affiliate commission type.');
    }
    $commissionType = $commissionType === '' ? null : $commissionType;

    $commissionRate = $fields['affiliate_commission_rate'] ?? null;
    if ($commissionRate === '' || $commissionRate === null) {
        $commissionRate = null;
    } elseif (!is_numeric($commissionRate) || (float)$commissionRate < 0 || (float)$commissionRate > 999.99) {
        throw new InvalidArgumentException('Commission rate must be between 0 and 999.99.');
    } else {
        $commissionRate = number_format((float)$commissionRate, 2, '.', '');
    }

    $cookieDuration = $fields['affiliate_cookie_duration'] ?? null;
    if ($cookieDuration === '' || $cookieDuration === null) {
        $cookieDuration = null;
    } elseif (!filter_var($cookieDuration, FILTER_VALIDATE_INT) || (int)$cookieDuration < 0 || (int)$cookieDuration > 3650) {
        throw new InvalidArgumentException('Cookie duration must be a whole number between 0 and 3650.');
    } else {
        $cookieDuration = (int)$cookieDuration;
    }

    $hasFreeTrial = !empty($fields['has_free_trial']);
    $freeTrialDays = $fields['free_trial_days'] ?? null;
    if (!$hasFreeTrial) {
        $freeTrialDays = null;
    } elseif (!filter_var($freeTrialDays, FILTER_VALIDATE_INT) || (int)$freeTrialDays <= 0 || (int)$freeTrialDays > 3650) {
        throw new InvalidArgumentException('Free trial days must be greater than 0 when free trial is enabled.');
    } else {
        $freeTrialDays = (int)$freeTrialDays;
    }

    $featureIds = getExistingIds($db, 'features', uniqueUuidList($input['feature_ids'] ?? []));
    $integrationIds = getExistingIds($db, 'integrations', uniqueUuidList($input['integration_ids'] ?? []));
    $newFeatures = (array)($input['new_features'] ?? []);
    $newIntegrations = (array)($input['new_integrations'] ?? []);

    $db->beginTransaction();
    try {
        foreach ($newFeatures as $feature) {
            $featureName = is_array($feature) ? ($feature['name'] ?? '') : $feature;
            $advanced = is_array($feature) && !empty($feature['advanced']);
            $featureIds[] = findOrCreateNamedItem($db, 'features', (string)$featureName, ['advanced' => $advanced]);
        }

        foreach ($newIntegrations as $integration) {
            $integrationName = is_array($integration) ? ($integration['name'] ?? '') : $integration;
            $integrationIds[] = findOrCreateNamedItem($db, 'integrations', (string)$integrationName);
        }

        $featureIds = array_values(array_unique($featureIds));
        $integrationIds = array_values(array_unique($integrationIds));

        $stmt = $db->prepare("
            UPDATE chatbots SET
                name = :name,
                website_url = :website_url,
                description = :description,
                logo_url = :logo_url,
                has_affiliate_program = :has_affiliate_program,
                affiliate_link = :affiliate_link,
                affiliate_commission_rate = :affiliate_commission_rate,
                affiliate_commission_type = :affiliate_commission_type,
                affiliate_cookie_duration = :affiliate_cookie_duration,
                has_free_trial = :has_free_trial,
                free_trial_days = :free_trial_days,
                starting_price = :starting_price,
                starting_price_eur = :starting_price_eur,
                pricing_model = :pricing_model,
                pricing_currency = :pricing_currency,
                pricing_url = :pricing_url,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'website_url' => $websiteUrl,
            'description' => $description,
            'logo_url' => $logoUrl,
            'has_affiliate_program' => $hasAffiliateProgram ? 1 : 0,
            'affiliate_link' => $affiliateLink,
            'affiliate_commission_rate' => $commissionRate,
            'affiliate_commission_type' => $commissionType,
            'affiliate_cookie_duration' => $cookieDuration,
            'has_free_trial' => $hasFreeTrial ? 1 : 0,
            'free_trial_days' => $freeTrialDays,
            'starting_price' => $startingPrice,
            'starting_price_eur' => $startingPriceEur,
            'pricing_model' => $pricingModel,
            'pricing_currency' => $pricingCurrency,
            'pricing_url' => $pricingUrl,
        ]);

        $db->prepare("DELETE FROM chatbot_features WHERE chatbot_id = :id")->execute(['id' => $id]);
        $featureInsert = $db->prepare("
            INSERT INTO chatbot_features (id, chatbot_id, feature_id, boolean_value)
            VALUES (:id, :chatbot_id, :feature_id, 1)
        ");
        foreach ($featureIds as $featureId) {
            $featureInsert->execute([
                'id' => generateUUID(),
                'chatbot_id' => $id,
                'feature_id' => $featureId,
            ]);
        }

        $db->prepare("DELETE FROM chatbot_integrations WHERE chatbot_id = :id")->execute(['id' => $id]);
        $integrationInsert = $db->prepare("
            INSERT INTO chatbot_integrations (id, chatbot_id, integration_id, is_supported)
            VALUES (:id, :chatbot_id, :integration_id, 1)
        ");
        foreach ($integrationIds as $integrationId) {
            $integrationInsert->execute([
                'id' => generateUUID(),
                'chatbot_id' => $id,
                'integration_id' => $integrationId,
            ]);
        }

        syncChatbotAffiliateTag($db, $id);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    return getSubmissionEditData($db, $id);
}

function deleteUnusedFeature(PDO $db, string $featureId): void {
    $featureId = trim($featureId);
    if (!preg_match('/^[0-9a-f-]{36}$/i', $featureId)) {
        throw new InvalidArgumentException('Invalid feature ID.');
    }

    $stmt = $db->prepare("SELECT name FROM features WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $featureId]);
    $featureName = $stmt->fetchColumn();
    if (!$featureName) {
        throw new InvalidArgumentException('Feature not found.');
    }

    $usageStmt = $db->prepare("
        SELECT COUNT(DISTINCT chatbot_id)
        FROM chatbot_features
        WHERE feature_id = :id
    ");
    $usageStmt->execute(['id' => $featureId]);
    $usageCount = (int)$usageStmt->fetchColumn();

    if ($usageCount > 0) {
        throw new InvalidArgumentException("Cannot delete \"$featureName\" because it is used by $usageCount chatbot(s).");
    }

    $delete = $db->prepare("DELETE FROM features WHERE id = :id");
    $delete->execute(['id' => $featureId]);
}

// ── CREATE ADMIN CHATBOT ──────────────────────────────────────────────────────
if ($action === 'create_admin_chatbot') {
    try {
        $name = trim((string)($input['name'] ?? ''));
        $websiteUrl = trim((string)($input['website_url'] ?? ''));
        $logoUrl = cleanNullableString($input['logo_url'] ?? null, 500);
        $adminNotes = cleanNullableString($input['admin_notes'] ?? null, 5000);

        if ($name === '' || mb_strlen($name) > 200) {
            throw new InvalidArgumentException('Chatbot name is required and must be max 200 characters.');
        }

        if ($websiteUrl === '' || mb_strlen($websiteUrl) > 500 || !filter_var($websiteUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Website URL must be a valid URL.');
        }

        $chatbotId = generateUUID();
        $adminId = $GLOBALS['adminUser']['audit_user_id'] ?? null;

        $stmt = $db->prepare("
            INSERT INTO chatbots (
                id, owner_user_id, created_by_admin_id, created_source,
                name, website_url, logo_url, status, admin_notes,
                starting_price, starting_price_eur, pricing_model, pricing_currency,
                has_free_trial, has_affiliate_program
            ) VALUES (
                :id, NULL, :created_by_admin_id, 'admin',
                :name, :website_url, :logo_url, 'pending', :admin_notes,
                0.00, 0.00, 'free', 'USD',
                0, 0
            )
        ");
        $stmt->execute([
            'id' => $chatbotId,
            'created_by_admin_id' => $adminId,
            'name' => $name,
            'website_url' => $websiteUrl,
            'logo_url' => $logoUrl,
            'admin_notes' => $adminNotes,
        ]);

        AuditLog::log('chatbot', $chatbotId, 'created',
            null,
            ['source' => 'admin', 'name' => $name, 'owner_user_id' => null],
            $adminId
        );

        echo json_encode([
            'success' => true,
            'message' => 'Chatbot added. Assign an account when you are ready.',
            'submission' => getAdminSubmissionRow($db, $chatbotId),
            'counts' => getAdminSubmissionCounts($db),
        ]);
    } catch (InvalidArgumentException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } catch (Throwable $e) {
        error_log('Admin chatbot create error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Could not add chatbot.']);
    }
    exit;
}

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'Missing chatbot ID.']);
    exit;
}

// ── SEARCH USER FOR ASSIGNMENT ────────────────────────────────────────────────
if ($action === 'search_user_by_email') {
    try {
        $email = trim((string)($input['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }

        $chatbotStmt = $db->prepare("
            SELECT id, owner_user_id, created_source
            FROM chatbots
            WHERE id = :id
              AND created_source = 'admin'
            LIMIT 1
        ");
        $chatbotStmt->execute(['id' => $id]);
        $targetChatbot = $chatbotStmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetChatbot) {
            throw new InvalidArgumentException('Admin-added chatbot not found.');
        }

        if (!empty($targetChatbot['owner_user_id'])) {
            throw new InvalidArgumentException('This chatbot is already assigned to an account.');
        }

        $stmt = $db->prepare("
            SELECT id, first_name, last_name, email, status, is_email_verified
            FROM users
            WHERE email = :email
            LIMIT 1
        ");
        $stmt->execute(['email' => $email]);
        $foundUser = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'found' => (bool)$foundUser,
            'user' => $foundUser ?: null,
            'message' => $foundUser ? 'Account found.' : 'No account found with that email.',
        ]);
    } catch (InvalidArgumentException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } catch (Throwable $e) {
        error_log('Admin chatbot user search error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Could not search account.']);
    }
    exit;
}

// ── ASSIGN ADMIN CHATBOT TO ACCOUNT ───────────────────────────────────────────
if ($action === 'assign_chatbot_owner') {
    try {
        $ownerUserId = trim((string)($input['user_id'] ?? ''));
        if (!preg_match('/^[0-9a-f-]{36}$/i', $ownerUserId)) {
            throw new InvalidArgumentException('Invalid account ID.');
        }

        $chatbotStmt = $db->prepare("
            SELECT id, owner_user_id, created_source, name
            FROM chatbots
            WHERE id = :id
              AND created_source = 'admin'
            LIMIT 1
        ");
        $chatbotStmt->execute(['id' => $id]);
        $targetChatbot = $chatbotStmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetChatbot) {
            throw new InvalidArgumentException('Admin-added chatbot not found.');
        }

        if (!empty($targetChatbot['owner_user_id'])) {
            throw new InvalidArgumentException('This chatbot is already assigned to an account.');
        }

        $userStmt = $db->prepare("
            SELECT id, first_name, last_name, email
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $userStmt->execute(['id' => $ownerUserId]);
        $owner = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$owner) {
            throw new InvalidArgumentException('Account not found.');
        }

        $assignStmt = $db->prepare("
            UPDATE chatbots
            SET owner_user_id = :owner_user_id,
                updated_at = NOW()
            WHERE id = :id
              AND owner_user_id IS NULL
              AND created_source = 'admin'
        ");
        $assignStmt->execute([
            'id' => $id,
            'owner_user_id' => $ownerUserId,
        ]);

        if ($assignStmt->rowCount() < 1) {
            throw new InvalidArgumentException('This chatbot is already assigned to an account.');
        }

        AuditLog::log('chatbot', $id, 'updated',
            ['owner_user_id' => null],
            ['owner_user_id' => $ownerUserId, 'source' => 'admin_assign']
        );

        echo json_encode([
            'success' => true,
            'message' => 'Chatbot assigned to account.',
            'user' => $owner,
            'submission' => getAdminSubmissionRow($db, $id),
            'counts' => getAdminSubmissionCounts($db),
        ]);
    } catch (InvalidArgumentException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } catch (Throwable $e) {
        error_log('Admin chatbot assign error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Could not assign account.']);
    }
    exit;
}

// ── Fetch chatbot + owner ──────────────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT c.id, c.name, c.website_url, c.status, c.is_verified,
           c.owner_user_id, c.created_source,
           c.approved_at, c.rejected_at, c.deleted_at, c.last_verified_at, c.verified_at,
           u.email AS user_email, u.first_name, u.last_name
    FROM chatbots c
    LEFT JOIN users u ON c.owner_user_id = u.id
    WHERE c.id = :id
      AND (c.submitted_at IS NOT NULL OR c.created_source = 'admin')
");
$stmt->execute(['id' => $id]);
$chatbot = $stmt->fetch();

if (!$chatbot) {
    echo json_encode(['success' => false, 'message' => 'Submission not found.']);
    exit;
}

// ── ACTIONS ────────────────────────────────────────────────────────────────────

switch ($action) {

    // ── GET EDIT DATA ─────────────────────────────────────────────────────
    case 'get_edit_data':
        try {
            echo json_encode([
                'success' => true,
                'data' => getSubmissionEditData($db, $id),
            ]);
        } catch (Throwable $e) {
            error_log('Submission edit load error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Could not load submission edit data.']);
        }
        break;

    // ── SAVE EDIT DATA ────────────────────────────────────────────────────
    case 'save_edit_data':
        try {
            $editData = saveSubmissionEditData($db, $id, $input);

            AuditLog::log('chatbot', $id, 'updated',
                ['source' => 'admin_submission_edit'],
                ['source' => 'admin_submission_edit', 'name' => $editData['chatbot']['name']]
            );

            echo json_encode([
                'success' => true,
                'message' => 'Listing details saved.',
                'data' => $editData,
            ]);
        } catch (InvalidArgumentException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log('Submission edit save error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Could not save listing details.']);
        }
        break;

    // ── DELETE UNUSED FEATURE ─────────────────────────────────────────────
    case 'delete_feature':
        try {
            $featureId = trim((string)($input['feature_id'] ?? ''));
            deleteUnusedFeature($db, $featureId);

            AuditLog::log('feature', $featureId, 'deleted',
                ['source' => 'admin_submission_edit'],
                ['source' => 'admin_submission_edit']
            );

            echo json_encode([
                'success' => true,
                'message' => 'Feature deleted.',
            ]);
        } catch (InvalidArgumentException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log('Feature delete error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Could not delete feature.']);
        }
        break;

    // ── VALIDATE CHATBOT (pre-accept check, no DB change) ─────────────────────
    case 'validate_chatbot':
        $result = validateChatbot($db, $id);
        echo json_encode([
            'success'    => true,
            'valid'      => $result['valid'],
            'rules'      => $result['rules'],
            'chatbot'    => [
                'id'   => $chatbot['id'],
                'name' => $chatbot['name'],
            ],
        ]);
        break;

    // ── ACCEPT → status=active, is_verified=1, approved_at, verified_at, email ─
    case 'accept':
        if (empty($chatbot['owner_user_id']) || empty($chatbot['user_email'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Assign this admin-added chatbot to an account before accepting it.',
            ]);
            break;
        }

        $validation = validateChatbot($db, $id);
        if (!$validation['valid']) {
            echo json_encode([
                'success' => false,
                'message' => 'Chatbot does not meet all requirements.',
                'rules'   => $validation['rules'],
            ]);
            break;
        }

        $db->prepare("
            UPDATE chatbots SET
                status           = 'active',
                is_verified      = 1,
                approved_at      = NOW(),
                verified_at      = NOW(),
                last_verified_at = NOW(),
                rejected_at      = NULL,
                deleted_at       = NULL
            WHERE id = :id
        ")->execute(['id' => $id]);

        syncChatbotSystemTags($db, $id);

        AuditLog::log('chatbot', $id, 'approved',
            ['status' => $chatbot['status'], 'is_verified' => (bool)$chatbot['is_verified']],
            ['status' => 'active', 'is_verified' => true]
        );

        $userName = trim($chatbot['first_name'] . ' ' . $chatbot['last_name']) ?: 'there';
        try {
            $email = new Email();
            $email->sendChatbotApproved(
                $chatbot['user_email'],
                $userName,
                $chatbot['name']
            );
        } catch (Exception $e) {
            error_log('Accept email error: ' . $e->getMessage());
        }

        echo json_encode([
            'success'      => true,
            'message'      => 'Chatbot accepted and is now active.',
            'pendingCount' => getPendingCount($db),
        ]);
        break;

    // ── REVIEW → status=reviewed, is_verified=1, approved_at ─────────────────
    case 'review':
        // Block: if currently active, require a delist reason
        if ($chatbot['status'] === 'active') {
            $reason = trim($input['reason'] ?? '');
 
            if (mb_strlen($reason) > 2000) {
                echo json_encode(['success' => false, 'message' => 'Reason is too long (max 2000 characters).']);
                exit;
            }
 
            $db->prepare("
                UPDATE chatbots SET
                    status      = 'reviewed',
                    is_verified = 0,
                    approved_at = NULL,
                    verified_at = NULL,
                    rejected_at = NULL,
                    deleted_at  = NULL
                WHERE id = :id
            ")->execute(['id' => $id]);
 
            AuditLog::log('chatbot', $id, 'updated',
                ['status' => $chatbot['status'], 'is_verified' => (bool)$chatbot['is_verified']],
                ['status' => 'reviewed', 'is_verified' => false, 'delist_reason' => $reason]
            );
 
            if (!empty($chatbot['user_email'])) {
                $userName = trim(($chatbot['first_name'] ?? '') . ' ' . ($chatbot['last_name'] ?? '')) ?: 'there';
                try {
                    $email = new Email();
                    $email->sendChatbotDelisted(
                        $chatbot['user_email'],
                        $userName,
                        $chatbot['name'],
                        $reason
                    );
                } catch (Exception $e) {
                    error_log('Delist email error (review): ' . $e->getMessage());
                }
            }
 
            echo json_encode([
                'success'      => true,
                'message'      => 'Chatbot delisted and moved to reviewed.',
                'pendingCount' => getPendingCount($db),
            ]);
            break;
        }
 
        // Normal flow (not active)
        $db->prepare("
            UPDATE chatbots SET
                status      = 'reviewed',
                is_verified = 1,
                approved_at = NULL,
                verified_at = NOW(),
                rejected_at = NULL,
                deleted_at  = NULL
            WHERE id = :id
        ")->execute(['id' => $id]);
 
        AuditLog::log('chatbot', $id, 'approved',
            ['status' => $chatbot['status'], 'is_verified' => (bool)$chatbot['is_verified']],
            ['status' => 'reviewed', 'is_verified' => true]
        );
 
        echo json_encode([
            'success'      => true,
            'message'      => 'Chatbot marked as reviewed.',
            'pendingCount' => getPendingCount($db),
        ]);
        break;

    // ── REJECT → is_verified=0, rejected_at, clears approved/deleted/verified ──
    case 'reject':
        // Block: cannot reject an active chatbot directly
        if ($chatbot['status'] === 'active') {
            echo json_encode(['success' => false, 'message' => 'Cannot reject an active chatbot. Use "Reviewed" or "Remove" to delist it first.']);
            exit;
        }
 
        $reason = trim($input['reason'] ?? '');
 
        if (empty($reason)) {
            echo json_encode(['success' => false, 'message' => 'Rejection reason is required.']);
            exit;
        }
 
        if (mb_strlen($reason) > 2000) {
            echo json_encode(['success' => false, 'message' => 'Reason is too long (max 2000 characters).']);
            exit;
        }
 
        $db->prepare("
            UPDATE chatbots SET
                status      = 'rejected',
                is_verified = 0,
                rejected_at = NOW(),
                approved_at = NULL,
                verified_at = NULL,
                deleted_at  = NULL,
                rejection_reason = :reason
            WHERE id = :id
        ")->execute(['id' => $id, 'reason' => $reason]);
 
        AuditLog::log('chatbot', $id, 'rejected',
            ['status' => $chatbot['status'], 'is_verified' => (bool)$chatbot['is_verified']],
            ['status' => 'rejected', 'is_verified' => false, 'rejection_reason' => $reason]
        );
 
        if (!empty($chatbot['user_email'])) {
            $userName = trim(($chatbot['first_name'] ?? '') . ' ' . ($chatbot['last_name'] ?? '')) ?: 'there';
            try {
                $email = new Email();
                $email->sendChatbotRejected(
                    $chatbot['user_email'],
                    $userName,
                    $chatbot['name'],
                    $reason
                );
            } catch (Exception $e) {
                error_log('Reject email error: ' . $e->getMessage());
            }
        }
 
        echo json_encode([
            'success'      => true,
            'message'      => 'Chatbot rejected.',
            'pendingCount' => getPendingCount($db),
        ]);
        break;

    // ── REMOVE → status=removed, is_verified=0, deleted_at, clears others ─────
    case 'remove':
        $wasActive = $chatbot['status'] === 'active';
 
        if ($wasActive) {
            $reason = trim($input['reason'] ?? '');
 
            if (mb_strlen($reason) > 2000) {
                echo json_encode(['success' => false, 'message' => 'Reason is too long (max 2000 characters).']);
                exit;
            }
        }
 
        $db->prepare("
            UPDATE chatbots SET
                status      = 'removed',
                is_verified = 0,
                deleted_at  = NOW(),
                approved_at = NULL,
                verified_at = NULL,
                rejected_at = NULL
            WHERE id = :id
        ")->execute(['id' => $id]);
 
        AuditLog::log('chatbot', $id, 'deleted',
            ['status' => $chatbot['status'], 'is_verified' => (bool)$chatbot['is_verified']],
            ['status' => 'removed', 'is_verified' => false]
        );
 
        if ($wasActive && !empty($chatbot['user_email'])) {
            $userName = trim(($chatbot['first_name'] ?? '') . ' ' . ($chatbot['last_name'] ?? '')) ?: 'there';
            try {
                $email = new Email();
                $email->sendChatbotDelisted(
                    $chatbot['user_email'],
                    $userName,
                    $chatbot['name'],
                    $reason
                );
            } catch (Exception $e) {
                error_log('Delist email error (remove): ' . $e->getMessage());
            }
        }
 
        echo json_encode([
            'success'      => true,
            'message'      => 'Chatbot removed.' . ($wasActive ? ' Delist email sent.' : ''),
            'pendingCount' => getPendingCount($db),
        ]);
        break;

    // ── SAVE NOTES ────────────────────────────────────────────────────────────
    case 'save_notes':
        $notes = trim($input['notes'] ?? '');

        if (mb_strlen($notes) > 5000) {
            echo json_encode(['success' => false, 'message' => 'Notes are too long (max 5000 characters).']);
            exit;
        }

        $db->prepare("
            UPDATE chatbots SET admin_notes = :notes WHERE id = :id
        ")->execute(['notes' => $notes ?: null, 'id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Notes saved.']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
