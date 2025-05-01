<?php
// components/school_selector.php

/**
 * Renders a school selector dropdown
 * 
 * @param PDO $pdo Database connection
 * @param string $currentPage Current page URL for form action
 * @param int|null $currentSchoolId Currently selected school ID
 * @return void
 */
function renderSchoolSelector($pdo, $currentPage, $currentSchoolId = null) {
    // Get all schools
    $stmt = $pdo->query("SELECT id, name FROM schools ORDER BY name ASC");
    $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no school ID is provided, use the one from session or default to first school
    if ($currentSchoolId === null) {
        $currentSchoolId = $_SESSION['school_id'] ?? null;
        if (!$currentSchoolId && !empty($schools)) {
            $currentSchoolId = $schools[0]['id'];
        }
    }
    
    // Build the URL with preserved query parameters except school_id
    $queryParams = $_GET;
    unset($queryParams['school_id']);
    $queryString = http_build_query($queryParams);
    $baseUrl = $currentPage . ($queryString ? "?$queryString&" : "?");
?>
<div class="school-selector">
    <form method="get" action="" class="d-flex align-items-center">
        <!-- Preserve existing query parameters -->
        <?php foreach ($_GET as $key => $value): ?>
            <?php if ($key !== 'school_id'): ?>
                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        
        <label for="school_id">School:</label>
        <select name="school_id" id="school_id" class="form-select" style="width: auto; margin-left: 10px;" onchange="this.form.submit()">
            <?php foreach ($schools as $school): ?>
                <option value="<?= $school['id'] ?>" <?= $currentSchoolId == $school['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($school['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>
<?php
}
?>