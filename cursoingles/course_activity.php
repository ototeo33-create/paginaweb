<?php

function intepCourseEnsureActivityTable(mysqli $conexion): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    mysqli_query(
        $conexion,
        "CREATE TABLE IF NOT EXISTS ingles_ultima_actividad (
            estudiante_id INT NOT NULL PRIMARY KEY,
            nivel VARCHAR(10) NOT NULL DEFAULT 'A1',
            modulo_num INT NOT NULL DEFAULT 1,
            page_path VARCHAR(255) NOT NULL,
            page_url VARCHAR(255) NOT NULL,
            page_title VARCHAR(255) DEFAULT NULL,
            section_id VARCHAR(120) DEFAULT NULL,
            section_title VARCHAR(255) DEFAULT NULL,
            activity_type VARCHAR(40) NOT NULL DEFAULT 'lesson',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $ensured = true;
}

function intepCourseDashboardPath(string $nivel): string
{
    return match (strtoupper($nivel)) {
        'A2' => '/intep/cursoingles/dashboard_a2.php',
        'B1', 'B2' => '/intep/cursoingles/dashboard_b1.php',
        default => '/intep/cursoingles/dashboard.php',
    };
}

function intepCourseGetLastActivity(mysqli $conexion, int $estudianteId, ?string $nivel = null): ?array
{
    intepCourseEnsureActivityTable($conexion);

    $query = "SELECT nivel, modulo_num, page_path, page_url, page_title, section_id, section_title, activity_type, updated_at
              FROM ingles_ultima_actividad
              WHERE estudiante_id = ?";
    $types = 'i';
    $params = [$estudianteId];

    if ($nivel) {
        $query .= ' AND nivel = ?';
        $types .= 's';
        $params[] = strtoupper($nivel);
    }

    $query .= " LIMIT 1";

    $st = mysqli_prepare($conexion, $query);
    if (!$st) {
        return null;
    }

    mysqli_stmt_bind_param($st, $types, ...$params);
    mysqli_stmt_execute($st);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?: null;
    mysqli_stmt_close($st);

    if (!$row) {
        return null;
    }

    if (strpos($row['page_path'] ?? '', '/intep/cursoingles/') !== 0) {
        return null;
    }

    return $row;
}
