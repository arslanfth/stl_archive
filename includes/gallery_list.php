<?php
// gallery_list.php
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/formatting.php';

function normalizeCategoryColor($color, $fallback = '#7c5cff')
{
    $color = trim((string)$color);
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        return strtolower($color);
    }

    return $fallback;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$whereArr = [];
$params = [];

if ($category) {
    $whereArr[] = "category_id = :cat";
    $params[':cat'] = $category;
}
if ($search !== '') {
    $whereArr[] = "title LIKE :search";
    $params[':search'] = "%$search%";
}
$where = $whereArr ? 'WHERE ' . implode(' AND ', $whereArr) : '';

$perPage = 16;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

$countSql = "SELECT COUNT(*) FROM images $where";
$stmt = $pdo->prepare($countSql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, $k == ':cat' ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$total = $stmt->fetchColumn();
$totalPages = ceil($total / $perPage);

$sql = "SELECT images.*, categories.name as category_name, categories.color as category_color
        FROM images
        LEFT JOIN categories ON categories.id = images.category_id
        $where
        ORDER BY images.created_at DESC
        LIMIT :start, :limit";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, $k == ':cat' ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':start', ($page - 1) * $perPage, PDO::PARAM_INT);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->execute();
$images = $stmt->fetchAll();

$mediaByImageId = [];
$imageIds = array_values(array_filter(array_map(static function ($img) {
    return isset($img['id']) ? (int)$img['id'] : 0;
}, $images)));

if ($imageIds) {
    $hasImageMediaTable = false;

    try {
        $tableCheckStmt = $pdo->query("SHOW TABLES LIKE 'image_media'");
        $hasImageMediaTable = (bool) $tableCheckStmt->fetchColumn();
    } catch (Throwable $e) {
        $hasImageMediaTable = false;
    }

    if ($hasImageMediaTable) {
        $placeholders = implode(',', array_fill(0, count($imageIds), '?'));
        $mediaStmt = $pdo->prepare(
            "SELECT id, image_id, filename, is_cover, sort_order
             FROM image_media
             WHERE image_id IN ($placeholders)
             ORDER BY image_id ASC, sort_order ASC, id ASC"
        );
        $mediaStmt->execute($imageIds);

        foreach ($mediaStmt->fetchAll() as $mediaRow) {
            $imageId = (int)($mediaRow['image_id'] ?? 0);
            if ($imageId <= 0) {
                continue;
            }

            $filename = trim((string)($mediaRow['filename'] ?? ''));
            if ($filename === '') {
                continue;
            }

            $mediaByImageId[$imageId][] = [
                'id' => (int)($mediaRow['id'] ?? 0),
                'filename' => $filename,
                'src' => 'upload/' . rawurlencode($filename),
                'is_cover' => (int)($mediaRow['is_cover'] ?? 0),
                'sort_order' => (int)($mediaRow['sort_order'] ?? 0),
            ];
        }
    }
}

$visibleCount = count($images);
$recentItems = array_slice($images, 0, 3);
$visibleCategories = [];
foreach ($images as $img) {
    $name = trim((string)($img['category_name'] ?? ''));
    if ($name !== '') {
        $visibleCategories[$name] = true;
    }
}
$visibleCategoryCount = count($visibleCategories);

$uncategorizedDistributionLabel = t('common.uncategorized', 'Kategorisiz');
$distributionSql = "SELECT COALESCE(categories.name, " . $pdo->quote($uncategorizedDistributionLabel) . ") AS category_name, categories.color AS category_color, COUNT(*) AS total
                    FROM images
                    LEFT JOIN categories ON categories.id = images.category_id
                    $where
                    GROUP BY images.category_id, categories.name, categories.color
                    ORDER BY total DESC, category_name ASC";
$distributionStmt = $pdo->prepare($distributionSql);
foreach ($params as $k => $v) {
    $distributionStmt->bindValue($k, $v, $k == ':cat' ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$distributionStmt->execute();
$distributionRows = $distributionStmt->fetchAll();

$distributionColors = ['#7c5cff', '#39a0ff', '#31c48d', '#f59e0b', '#ec4899', '#94a3b8'];
$distributionSegments = [];
$distributionLegend = [];
$distributionTotal = 0;
$distributionMax = 0;

foreach ($distributionRows as $index => $row) {
    $count = (int)($row['total'] ?? 0);
    if ($count <= 0) {
        continue;
    }
    $distributionTotal += $count;
    $distributionMax = max($distributionMax, $count);
    $fallbackColor = $distributionColors[$index % count($distributionColors)] ?? '#7c5cff';
    $distributionLegend[] = [
        'label' => (string)$row['category_name'],
        'count' => $count,
        'color' => normalizeCategoryColor($row['category_color'] ?? '', $fallbackColor),
    ];
}

if ($distributionTotal > 0) {
    $startAngle = 0;
    foreach ($distributionLegend as $item) {
        $percentage = ($item['count'] / $distributionTotal) * 100;
        $endAngle = $startAngle + $percentage;
        $distributionSegments[] = sprintf(
            '%s %.3f%% %.3f%%',
            $item['color'],
            $startAngle,
            $endAngle
        );
        $startAngle = $endAngle;
    }
}

$distributionStyle = $distributionSegments
    ? 'background: conic-gradient(' . implode(', ', $distributionSegments) . ');'
    : '';

$selectedCategoryName = '';
if ($category) {
    foreach ($images as $img) {
        if ((int)($img['category_id'] ?? 0) === $category && !empty($img['category_name'])) {
            $selectedCategoryName = trim((string)$img['category_name']);
            break;
        }
    }

    if ($selectedCategoryName === '') {
        $catStmt = $pdo->prepare("SELECT name FROM categories WHERE id = :id LIMIT 1");
        $catStmt->bindValue(':id', $category, PDO::PARAM_INT);
        $catStmt->execute();
        $selectedCategoryName = trim((string)($catStmt->fetchColumn() ?: ''));
    }
}

$sectionTitle = t('gallery.latest_title', 'Son Eklenen Modeller');
$sectionDescription = t('gallery.latest_description', 'ArÅŸivinizdeki gÃ¼ncel modeller');

if ($search !== '' && $selectedCategoryName !== '') {
    $sectionTitle = sprintf(t('gallery.search_results_in_category', '%s iÃ§inde arama sonuÃ§larÄ±'), $selectedCategoryName);
    $sectionDescription = t('gallery.search_matches', 'AramanÄ±zla eÅŸleÅŸen modeller');
} elseif ($search !== '') {
    $sectionTitle = t('gallery.search_results', 'Arama SonuÃ§larÄ±');
    $sectionDescription = t('gallery.search_matches', 'AramanÄ±zla eÅŸleÅŸen modeller');
} elseif ($selectedCategoryName !== '') {
    $sectionTitle = $selectedCategoryName;
    $sectionDescription = sprintf(t('gallery.category_models', '%s kategorisindeki modeller'), $selectedCategoryName);
}
?>

<div class="gallery-layout">
    <div class="gallery-main">
        <div class="section-panel">
            <div class="section-header">
                <div>
                    <h2><?= htmlspecialchars($sectionTitle) ?></h2>
                    <p><?= htmlspecialchars($sectionDescription) ?></p>
                </div>
            </div>

            <div class="gallery-grid">
                <?php if ($images): ?>
                    <?php foreach ($images as $img): ?>
                        <?php
                        $imageId = (int)($img['id'] ?? 0);
                        $mediaItems = $mediaByImageId[$imageId] ?? [];
                        $coverMedia = $mediaItems[0] ?? null;
                        $legacyFilename = trim((string)($img['filename'] ?? ''));

                        if ($coverMedia && !empty($coverMedia['src'])) {
                            $imageSrc = $coverMedia['src'];
                        } elseif ($legacyFilename !== '') {
                            $imageSrc = 'upload/' . rawurlencode($legacyFilename);
                        } elseif (!empty($img['drive_file_id'])) {
                            $imageSrc = "assets/drive-file.png";
                        } else {
                            $imageSrc = "assets/no-image.png";
                        }

                        $galleryImages = $mediaItems;
                        if (!$galleryImages && $legacyFilename !== '') {
                            $galleryImages[] = [
                                'id' => 0,
                                'filename' => $legacyFilename,
                                'src' => 'upload/' . rawurlencode($legacyFilename),
                                'is_cover' => 1,
                                'sort_order' => 0,
                            ];
                        }

                        $galleryImagesJson = htmlspecialchars(
                            json_encode($galleryImages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        $cardCategoryColor = normalizeCategoryColor($img['category_color'] ?? '', '#7c5cff');
                        $formattedSize = formatFileSize($img['size'] ?? '');
                        $rawSize = (string)($img['size'] ?? '');
                        ?>
                        <div class="gallery-item"
                            data-id="<?= $img['id'] ?>"
                            data-title="<?= htmlspecialchars($img['title'] ?? '') ?>"
                            data-filename="<?= htmlspecialchars($img['filename'] ?? '') ?>"
                            data-imagesrc="<?= htmlspecialchars($imageSrc) ?>"
                            data-gallery-images="<?= $galleryImagesJson ?>"
                            data-created="<?= htmlspecialchars($img['created_at'] ?? '') ?>"
                            data-category="<?= htmlspecialchars($img['category_name'] ?? '') ?>"
                            data-categoryid="<?= $img['category_id'] ?>"
                            data-size="<?= htmlspecialchars($formattedSize) ?>"
                            data-size-raw="<?= htmlspecialchars($rawSize) ?>"
                            data-download="<?= htmlspecialchars($img['download_link'] ?? '') ?>">

                            <div class="gallery-thumb-wrap">
                                <img src="<?= $imageSrc ?>"
                                    alt="<?= htmlspecialchars($img['title'] ?? '') ?>"
                                    onerror="this.onerror=null;this.src='assets/no-image.png';">
                            </div>
                            <div class="gallery-card-body">
                                <div class="gallery-meta-row">
                                    <?php if (!empty($img['category_name'])): ?>
                                        <span class="gallery-chip" style="--category-color: <?= htmlspecialchars($cardCategoryColor) ?>;"><?= htmlspecialchars($img['category_name']) ?></span>
                                    <?php endif; ?>
                                    <div class="gallery-meta">
                                        <span><?= htmlspecialchars($formattedSize) ?></span>
                                    </div>
                                </div>
                                <div class="gallery-title" title="<?= htmlspecialchars($img['title'] ?? '') ?>">
                                    <?= htmlspecialchars($img['title'] ?? '') ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="gallery-empty">
                        <?= htmlspecialchars(t('gallery.empty', 'KayÄ±t bulunamadÄ±.')) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
        <?php
            $queryBase = [];
            if ($category) {
                $queryBase['category'] = $category;
            }
            if ($search !== '') {
                $queryBase['search'] = $search;
            }

            $range = 2;
        ?>

        <?php if ($page > 1):
            $queryBase['page'] = $page - 1; ?>
            <a class="page-btn nav" href="?<?= http_build_query($queryBase) ?>">&laquo; <?= htmlspecialchars(t('gallery.previous', 'Ã–nceki')) ?></a>
        <?php else: ?>
            <span class="page-btn nav disabled">&laquo; <?= htmlspecialchars(t('gallery.previous', 'Ã–nceki')) ?></span>
        <?php endif; ?>

        <?php
        if ($page > $range + 1) {
            $queryBase['page'] = 1;
            echo '<a class="page-btn" href="?' . http_build_query($queryBase) . '">1</a>';
            echo '<span class="page-btn disabled">...</span>';
        }

        for ($i = max(1, $page - $range); $i <= min($totalPages, $page + $range); $i++) {
            $queryBase['page'] = $i;
            if ($i == $page) {
                echo '<span class="page-btn active">' . $i . '</span>';
            } else {
                echo '<a class="page-btn" href="?' . http_build_query($queryBase) . '">' . $i . '</a>';
            }
        }

        if ($page < $totalPages - $range) {
            echo '<span class="page-btn disabled">...</span>';
            $queryBase['page'] = $totalPages;
            echo '<a class="page-btn" href="?' . http_build_query($queryBase) . '">' . $totalPages . '</a>';
        }
        ?>

        <?php if ($page < $totalPages):
            $queryBase['page'] = $page + 1; ?>
            <a class="page-btn nav" href="?<?= http_build_query($queryBase) ?>"><?= htmlspecialchars(t('gallery.next', 'Sonraki')) ?> &raquo;</a>
        <?php else: ?>
            <span class="page-btn nav disabled"><?= htmlspecialchars(t('gallery.next', 'Sonraki')) ?> &raquo;</span>
        <?php endif; ?>

        </div>
        <?php endif; ?>
    </div>

    <?php if ($total || $visibleCount || $recentItems): ?>
    <aside class="insights-panel">
        <div class="insight-card drive-storage-card" id="driveStorageCard">
            <span class="insight-label"><?= htmlspecialchars(t('gallery.drive_storage', 'Depolama alanÄ±')) ?></span>
            <div class="drive-storage-card__content">
                <strong class="drive-storage-card__value"><?= htmlspecialchars(t('messages.loading', 'YÃ¼kleniyor...')) ?></strong>
                <span class="drive-storage-card__meta"><?= htmlspecialchars(t('messages.drive_storage_loading', 'Drive depolama bilgisi alÄ±nÄ±yor')) ?></span>
                <div class="drive-storage-card__progress" aria-hidden="true">
                    <span class="drive-storage-card__bar" style="width: 0%;"></span>
                </div>
            </div>
        </div>
        <?php if ($distributionLegend): ?>
        <div class="insight-card insight-card--distribution-list">
            <span class="insight-label"><?= htmlspecialchars(t('gallery.category_distribution', 'Kategori DaÄŸÄ±lÄ±mÄ±')) ?></span>
            <div class="distribution-summary">
                <strong class="distribution-summary__value"><?= $distributionTotal ?></strong>
                <span class="distribution-summary__label"><?= htmlspecialchars(t('common.total', 'Toplam')) ?></span>
            </div>
            <div class="distribution-list" role="list" aria-label="<?= htmlspecialchars(t('gallery.category_distribution', 'Kategori DaÄŸÄ±lÄ±mÄ±')) ?>">
                <?php foreach ($distributionLegend as $item): ?>
                    <?php
                    $width = $distributionMax > 0 ? max(6, ($item['count'] / $distributionMax) * 100) : 0;
                    $share = $distributionTotal > 0 ? round(($item['count'] / $distributionTotal) * 100) : 0;
                    ?>
                    <div class="distribution-row" role="listitem">
                        <div class="distribution-row__meta">
                            <span class="distribution-dot" style="background: <?= htmlspecialchars($item['color']) ?>"></span>
                            <span class="distribution-name" title="<?= htmlspecialchars($item['label']) ?>"><?= htmlspecialchars($item['label']) ?></span>
                            <span class="distribution-count"><?= $item['count'] ?></span>
                        </div>
                        <div class="distribution-track" aria-hidden="true">
                            <span class="distribution-bar" style="width: <?= number_format($width, 2, '.', '') ?>%; background: linear-gradient(90deg, <?= htmlspecialchars($item['color']) ?>, <?= htmlspecialchars($item['color']) ?>);"></span>
                        </div>
                        <div class="distribution-share"><?= $share ?>%</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>


        <?php if ($recentItems): ?>
        <div class="insight-card">
            <span class="insight-label"><?= htmlspecialchars(t('gallery.recent_items', 'Son Eklenenler')) ?></span>
            <div class="recent-list">
                <?php foreach ($recentItems as $recent): ?>
                    <?php
                    $recentImageId = (int)($recent['id'] ?? 0);
                    $recentMediaItems = $mediaByImageId[$recentImageId] ?? [];
                    $recentCoverMedia = $recentMediaItems[0] ?? null;
                    $recentLegacyFilename = trim((string)($recent['filename'] ?? ''));

                    if ($recentCoverMedia && !empty($recentCoverMedia['src'])) {
                        $recentImageSrc = $recentCoverMedia['src'];
                    } elseif ($recentLegacyFilename !== '') {
                        $recentImageSrc = 'upload/' . rawurlencode($recentLegacyFilename);
                    } elseif (!empty($recent['drive_file_id'])) {
                        $recentImageSrc = 'assets/drive-file.png';
                    } else {
                        $recentImageSrc = 'assets/no-image.png';
                    }

                    $recentCategoryLabel = trim((string)($recent['category_name'] ?? '')) ?: t('common.uncategorized', 'Kategorisiz');
                    $recentCategoryColor = normalizeCategoryColor($recent['category_color'] ?? '', '#7c5cff');
                    $recentGalleryImages = $recentMediaItems;
                    if (!$recentGalleryImages && $recentLegacyFilename !== '') {
                        $recentGalleryImages[] = [
                            'id' => 0,
                            'filename' => $recentLegacyFilename,
                            'src' => 'upload/' . rawurlencode($recentLegacyFilename),
                            'is_cover' => 1,
                            'sort_order' => 0,
                        ];
                    }

                    $recentGalleryImagesJson = htmlspecialchars(
                        json_encode($recentGalleryImages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    $recentFormattedSize = formatFileSize($recent['size'] ?? '');
                    $recentRawSize = (string)($recent['size'] ?? '');
                    ?>
                    <div class="recent-item recent-openable"
                        data-id="<?= $recent['id'] ?>"
                        data-title="<?= htmlspecialchars($recent['title'] ?? '') ?>"
                        data-filename="<?= htmlspecialchars($recent['filename'] ?? '') ?>"
                        data-imagesrc="<?= htmlspecialchars($recentImageSrc) ?>"
                        data-gallery-images="<?= $recentGalleryImagesJson ?>"
                        data-created="<?= htmlspecialchars($recent['created_at'] ?? '') ?>"
                        data-category="<?= htmlspecialchars($recent['category_name'] ?? '') ?>"
                        data-categoryid="<?= $recent['category_id'] ?>"
                        data-size="<?= htmlspecialchars($recentFormattedSize) ?>"
                        data-size-raw="<?= htmlspecialchars($recentRawSize) ?>"
                        data-download="<?= htmlspecialchars($recent['download_link'] ?? '') ?>"
                        role="button"
                        tabindex="0"
                        aria-label="<?= htmlspecialchars($recent['title'] ?? '') ?>">
                        <div class="recent-thumb">
                            <img src="<?= htmlspecialchars($recentImageSrc) ?>" alt="<?= htmlspecialchars($recent['title'] ?? '') ?>">
                        </div>
                        <div class="recent-body">
                            <span class="recent-title" title="<?= htmlspecialchars($recent['title'] ?? '') ?>">
                                <?= htmlspecialchars($recent['title'] ?? '') ?>
                            </span>
                            <div class="recent-meta">
                                <span class="recent-category-badge" style="--category-color: <?= htmlspecialchars($recentCategoryColor) ?>;" title="<?= htmlspecialchars($recentCategoryLabel) ?>">
                                    <?= htmlspecialchars($recentCategoryLabel) ?>
                                </span>
                                <span class="recent-size"><?= htmlspecialchars($recentFormattedSize) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </aside>
    <?php endif; ?>
</div>

<script>
function gotoPage(page) {
    const url = new URL(window.location.href);
    url.searchParams.set('page', page);
    window.location.href = url.toString();
}
</script>





