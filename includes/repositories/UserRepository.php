<?php

class UserRepository
{
    public static function paginatedByRole(int $schoolId, string $role, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        $count = db()->prepare('SELECT COUNT(*) FROM users WHERE school_id = ? AND role = ?');
        $count->execute([$schoolId, $role]);
        $total = (int) $count->fetchColumn();

        $stmt = db()->prepare('SELECT * FROM users WHERE school_id = ? AND role = ? ORDER BY last_name, first_name LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $schoolId, PDO::PARAM_INT);
        $stmt->bindValue(2, $role);
        $stmt->bindValue(3, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(4, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items'       => $stmt->fetchAll(),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / max(1, $perPage)),
        ];
    }

    public static function paginatedSchools(?string $status, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $where = '';
        $filterParams = [];

        if ($status && array_key_exists($status, SCHOOL_STATUSES)) {
            $where = ' WHERE s.status = ?';
            $filterParams = [$status];
        }

        $countStmt = db()->prepare("SELECT COUNT(*) FROM schools s $where");
        $countStmt->execute($filterParams);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT s.*, u.email AS admin_email, u.first_name AS admin_first, u.last_name AS admin_last
            FROM schools s
            LEFT JOIN users u ON u.school_id = s.id AND u.role = 'school_admin'
            $where
            GROUP BY s.id
            ORDER BY s.registered_at DESC
            LIMIT ? OFFSET ?";
        $params = [...$filterParams, $perPage, $offset];
        $stmt = db()->prepare($sql);
        foreach ($params as $i => $val) {
            $stmt->bindValue($i + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return [
            'items'       => $stmt->fetchAll(),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / max(1, $perPage)),
        ];
    }
}
