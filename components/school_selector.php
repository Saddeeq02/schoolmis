// components/school_selector.php
<?php
function renderSchoolSelector($pdo, $currentPage) {
    // Get all schools
    $stmt = $pdo->query("SELECT id, name FROM schools ORDER BY name ASC");
    $schools = $stmt->fetchAll();
    
    // Get current school ID
    $currentSchoolId = $_SESSION['school_id'] ?? 1;
    
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
        <label for="school_id" class="me-2">School:</label>
        <select name="school_id" id="school_id" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
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