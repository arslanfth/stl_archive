SET @db_name := DATABASE();

SET @has_image_media_table := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = @db_name
      AND table_name = 'image_media'
);

SET @has_is_cover_column := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'image_media'
      AND column_name = 'is_cover'
);

SET @add_is_cover_sql := IF(
    @has_image_media_table = 1 AND @has_is_cover_column = 0,
    'ALTER TABLE image_media ADD COLUMN is_cover TINYINT(1) NOT NULL DEFAULT 0 AFTER filename',
    'SELECT 1'
);
PREPARE add_is_cover_stmt FROM @add_is_cover_sql;
EXECUTE add_is_cover_stmt;
DEALLOCATE PREPARE add_is_cover_stmt;

SET @has_cover_index := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'image_media'
      AND index_name = 'idx_image_media_image_cover'
);

SET @add_cover_index_sql := IF(
    @has_image_media_table = 1 AND @has_cover_index = 0,
    'ALTER TABLE image_media ADD INDEX idx_image_media_image_cover (image_id, is_cover)',
    'SELECT 1'
);
PREPARE add_cover_index_stmt FROM @add_cover_index_sql;
EXECUTE add_cover_index_stmt;
DEALLOCATE PREPARE add_cover_index_stmt;

UPDATE image_media AS media
JOIN (
    SELECT
        image_id,
        COALESCE(
            MAX(CASE WHEN is_cover = 1 THEN id END),
            CAST(SUBSTRING_INDEX(GROUP_CONCAT(id ORDER BY sort_order ASC, id ASC), ',', 1) AS UNSIGNED)
        ) AS cover_id
    FROM image_media
    GROUP BY image_id
) AS selected_cover
    ON selected_cover.image_id = media.image_id
SET media.is_cover = CASE
    WHEN media.id = selected_cover.cover_id THEN 1
    ELSE 0
END;

UPDATE images AS image_row
LEFT JOIN (
    SELECT media.image_id, media.filename
    FROM image_media AS media
    WHERE media.is_cover = 1
) AS selected_cover
    ON selected_cover.image_id = image_row.id
SET image_row.filename = COALESCE(selected_cover.filename, image_row.filename);
