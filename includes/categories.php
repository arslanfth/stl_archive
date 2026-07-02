<?php

// Aktif kategori (GET ile gönderilecek)
$active = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Tüm kategorileri çek
$stmt = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC, name ASC");
$categories = $stmt->fetchAll();
?>
<div class="sidebar">
    <h2><?= htmlspecialchars(t('sidebar.title', 'Kategoriler')) ?></h2>
    <ul>
        <li class="<?= $active === 0 ? 'active' : '' ?>">
            <a href="index.php"><?= htmlspecialchars(t('sidebar.all', 'Tümü')) ?></a>
        </li>
        <?php foreach ($categories as $cat): ?>
            <li class="<?= $active === intval($cat['id']) ? 'active' : '' ?>">
                <a href="index.php?category=<?= $cat['id'] ?>">
                    <?= htmlspecialchars($cat['name']) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
